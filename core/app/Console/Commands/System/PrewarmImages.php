<?php

namespace App\Console\Commands\System;

use App\System;
use App\Lib\Deploy\CacheManager\BuiltImage;
use App\Lib\Deploy\CacheManager\PhpBaseImage;
use App\Lib\Deploy\CacheManager\HostPrewarmPlan;
use App\Lib\Deploy\CacheManager\ImageTransfer;
use App\Lib\Deploy\Engine\EngineFactory;
use Illuminate\Console\Command;

/**
 * Warm the host image cache so no customer deploy pays a first-use penalty.
 *
 * What to warm and in what order comes from `config/core/images.yaml`. Disk
 * is the binding constraint on a VPS, so the plan is cut off by a budget and a
 * reserve, and both are enforced against measurements rather than estimates:
 * an image's download size decides whether to attempt it, and the free space
 * it actually consumed decides whether to attempt the next one.
 */
class PrewarmImages extends Command
{
    /** The old spelling still answers, so nothing scripted against it breaks. */
    protected $aliases = ['system:prewarm-images'];

    protected $signature = 'system:image:prewarm
        {--dry-run : Print the plan and exit}
        {--budget= : Max disk to spend, e.g. 6G ("none" for unlimited; default from images.yaml)}
        {--reserve= : Free space to leave untouched, e.g. 10G (default from images.yaml)}
        {--runtimes= : Comma-separated subset, e.g. php,node,static}
        {--keep-build-cache : Do not prune the buildx cache afterwards}
        {--keep-unused : Never drop a current image, even when free space is under the reserve}';

    protected $description = 'Prebuild and pull the base images deploys need, within a disk budget';

    public function handle(): int
    {
        $system = new System();

        try {
            [$budget, $reserve] = $this->diskLimits();
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $runtimes = $this->requestedRuntimes();

        $available = $this->availableBytes($system);
        if ($available === null) {
            $this->error('Could not read free space on the Docker data root.');

            return 1;
        }

        $present = $this->presentImages($system);
        $present = $this->dropStaleBaseImages($system, $present);
        $available = $this->availableBytes($system) ?? $available;
        $present = $this->reclaimUnderPressure(
            $system,
            $present,
            $available,
            $reserve,
            HostPrewarmPlan::catalog($runtimes)
        );
        $available = $this->availableBytes($system) ?? $available;

        $catalog = HostPrewarmPlan::catalog($runtimes);
        $plan = HostPrewarmPlan::select(
            $catalog,
            $this->plannedSizes($system, $catalog, $present),
            $available,
            $reserve,
            $budget,
            $present
        );

        $this->line(sprintf(
            'Free %s, reserve %s, budget %s → spendable %s',
            HostPrewarmPlan::formatBytes($available),
            HostPrewarmPlan::formatBytes($reserve),
            $budget === null ? 'none' : HostPrewarmPlan::formatBytes($budget),
            HostPrewarmPlan::formatBytes($plan['spendable'])
        ));

        $this->renderPlan($plan);

        if ($plan['selected'] === []) {
            $this->info('Nothing to warm.');

            return 0;
        }
        if ($this->option('dry-run')) {
            return 0;
        }

        [$built, $failed] = $this->warmAll($system, $plan['selected'], $reserve, $budget);

        if (!$this->option('keep-build-cache')) {
            $this->pruneBuildCache($system);
        }

        $this->info("Warmed {$built} image(s)" . ($failed > 0 ? ", {$failed} failed" : ''));

        return 0;
    }

    /**
     * @return array{?int, int} budget (null = unlimited) and reserve, in bytes
     * @throws \InvalidArgumentException when either option is unparseable
     */
    private function diskLimits(): array
    {
        $budgetOption = $this->stringOption('budget');
        $budget = $budgetOption !== null && strtolower($budgetOption) === 'none'
            ? null
            : (HostPrewarmPlan::parseBytes($budgetOption) ?? HostPrewarmPlan::budgetBytes());
        $reserve = HostPrewarmPlan::parseBytes($this->stringOption('reserve'))
            ?? HostPrewarmPlan::reserveBytes();

        return [$budget, $reserve];
    }

    /**
     * @return list<string>
     */
    private function requestedRuntimes(): array
    {
        $option = $this->stringOption('runtimes');

        return $option === null
            ? []
            : array_values(array_filter(array_map('trim', explode(',', $option))));
    }

    /**
     * Warm each selected image, stopping on the first one the host cannot
     * afford.
     *
     * Both limits are checked against the filesystem rather than against the
     * plan: free space is re-read before every item, and what an image cost is
     * the space that actually disappeared when it landed. The download sizes
     * that shaped the plan are compressed and understate the cost by roughly
     * three times, so a budget enforced on them would overspend silently.
     *
     * @param list<array<string, mixed>> $selected
     * @return array{int, int} images warmed and images that failed
     */
    private function warmAll(System $system, array $selected, int $reserve, ?int $budget): array
    {
        $built = 0;
        $failed = 0;
        $spent = 0;
        foreach ($selected as $item) {
            $ref = (string) $item['ref'];

            $before = $this->availableBytes($system);
            if ($before !== null && $before <= $reserve) {
                $this->warn(sprintf(
                    'Stopping before %s: %s free against a %s reserve',
                    $ref,
                    HostPrewarmPlan::formatBytes($before),
                    HostPrewarmPlan::formatBytes($reserve)
                ));
                break;
            }
            if ($budget !== null && $spent >= $budget) {
                $this->warn(sprintf(
                    'Stopping before %s: spent %s of a %s budget',
                    $ref,
                    HostPrewarmPlan::formatBytes($spent),
                    HostPrewarmPlan::formatBytes($budget)
                ));
                break;
            }

            $this->line("→ {$item['kind']} {$ref}");
            $started = microtime(true);
            if ($this->warmOne($system, $item)) {
                $built++;
                $after = $this->availableBytes($system);
                $cost = $before !== null && $after !== null ? max(0, $before - $after) : 0;
                $spent += $cost;
                $this->info(sprintf(
                    '  done in %ds, %s of disk',
                    (int) round(microtime(true) - $started),
                    HostPrewarmPlan::formatBytes($cost)
                ));
            } else {
                $failed++;
                $this->warn('  failed, leaving it to the first deploy that needs it');
            }
        }

        return [$built, $failed];
    }

    /**
     * What each image the plan might warm is expected to cost, keyed by ref.
     *
     * Two measurements, never an estimate. A pull is asked of the registry.
     * A build has no manifest until it exists, so it is priced at whatever an
     * earlier image of the same repository actually took on this host -- a
     * previous PHP base under an older recipe date is the same image with a
     * different extension list, and its size is the honest answer.
     *
     * Anything neither measurement reaches is absent from the result, which
     * {@see HostPrewarmPlan::select()} treats as "warm it, uncharged".
     *
     * @param list<array<string, mixed>> $catalog
     * @param list<string> $present
     * @return array<string, int>
     */
    private function plannedSizes(System $system, array $catalog, array $present): array
    {
        $have = array_flip($present);
        $onDisk = $this->presentImageSizes($system, $present);
        $sizes = [];
        foreach ($catalog as $item) {
            $ref = (string) $item['ref'];
            if (isset($have[$ref])) {
                continue;
            }
            if ($item['kind'] === HostPrewarmPlan::KIND_BUILD) {
                $previous = $this->sizeOfSiblingImage($ref, $onDisk);
                if ($previous !== null) {
                    $sizes[$ref] = $previous;
                }
                continue;
            }
            try {
                $out = $system->exec(
                    ['sudo', 'docker', 'manifest', 'inspect', '--verbose', $ref],
                    [],
                    60
                );
            } catch (\Exception $e) {
                continue;
            }
            $bytes = ImageTransfer::parseManifestSize(is_string($out) ? $out : '');
            if ($bytes !== null) {
                $sizes[$ref] = $bytes;
            }
        }

        return $sizes;
    }

    /**
     * The largest image already on the host under the same repository, which
     * is the closest thing to a measurement of what building $ref will cost.
     *
     * Largest rather than newest: `docker images` gives no ordering worth
     * trusting here, and over-stating the cost of a build only makes the plan
     * stop one image earlier than it had to.
     *
     * @param array<string, int> $onDisk
     */
    private function sizeOfSiblingImage(string $ref, array $onDisk): ?int
    {
        $repository = explode(':', $ref, 2)[0];
        $best = null;
        foreach ($onDisk as $have => $bytes) {
            if (explode(':', $have, 2)[0] === $repository && ($best === null || $bytes > $best)) {
                $best = $bytes;
            }
        }

        return $best;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function warmOne(System $system, array $item): bool
    {
        $ref = (string) $item['ref'];

        try {
            if ($item['kind'] === HostPrewarmPlan::KIND_BUILD) {
                $dockerfile = $this->dockerfileFor($ref, is_array($item['extensions'] ?? null)
                    ? $item['extensions'] : []);
                if ($dockerfile === null) {
                    return false;
                }
                $system->exec(
                    EngineFactory::default()->images()->hostBuildCommand($ref, $dockerfile),
                    [],
                    2400
                );

                return true;
            }

            $system->exec(['sudo', 'docker', 'pull', $ref], [], 900);

            return true;
        } catch (\Exception $e) {
            $this->warn('  ' . $e->getMessage());

            return false;
        }
    }

    /**
     * The Dockerfile that produces this tag, or null when nothing here can
     * produce it.
     *
     * Dispatched on the catalogue's repository table rather than assumed to be
     * PHP, so what the engine warms is what the config says the engine builds.
     *
     * A variant tag — the `-x…` suffix — hashes a set of extensions, and a
     * hash does not go backwards, so the tag alone cannot say what to build.
     * $extensions is that set, and it comes from the catalogue entry rather
     * than from the tag: an operator lists the sets their apps ask for, and the
     * engine warms them. Without it a variant is only ever built by the deploy
     * that needed it, which is how a required extension failed a first deploy.
     *
     * Ruby's and Python's bases fingerprint an apt package set that comes from
     * the project's own manifest, so there is genuinely nothing to warm.
     *
     * @param list<string> $extensions
     */
    private function dockerfileFor(string $ref, array $extensions = []): ?string
    {
        $source = BuiltImage::sourceImage($ref);
        if ($source === null) {
            return null;
        }
        // A plain base is rebuildable from its own tag; a variant only when the
        // catalogue still says which extensions it carries.
        if (!BuiltImage::isRebuildableFromTag($ref) && $extensions === []) {
            return null;
        }

        return match (BuiltImage::runtimeFor($ref)) {
            'php' => PhpBaseImage::dockerfile($source, $extensions),
            default => null,
        };
    }

    /**
     * Give up current images we can rebuild, when the host is under its
     * reserve and only then.
     *
     * Everything {@see dropStaleBaseImages()} removes is obsolete and its
     * removal costs nothing. This removes images that are still perfectly
     * good, so it asks a harder question: the disk is short, these can all be
     * rebuilt from the catalogue, which do we least mind rebuilding?
     *
     * Least mind is the same ranking the warm plan spends by, and an image the
     * catalog does not rank at all -- a variant built on demand for one
     * account -- goes first. Dropping stops the moment the reserve is met, so
     * mild pressure gives up one variant rather than a whole PHP set.
     *
     * Only ever touches images the catalogue says we build. A pulled upstream
     * image is somebody else's to keep, and deleting it here would just make
     * the next deploy pull it again from a registry that still has it -- the
     * space is real but the trade is worse.
     *
     * @param list<string> $present
     * @param list<string> $runtimes
     * @return list<string> $present minus what was removed
     */
    private function reclaimUnderPressure(
        System $system,
        array $present,
        int $available,
        int $reserve,
        array $catalog
    ): array {
        if ($this->option('keep-unused') || $available >= $reserve) {
            return $present;
        }

        $drop = HostPrewarmPlan::reclaimableBuiltImages(
            $this->presentImageSizes($system, $present),
            $available,
            $reserve,
            $catalog
        );
        if ($drop === []) {
            $this->warn(sprintf(
                'Only %s free against a %s reserve, and nothing of ours is safe to drop.',
                HostPrewarmPlan::formatBytes($available),
                HostPrewarmPlan::formatBytes($reserve)
            ));

            return $present;
        }

        $this->warn(sprintf(
            'Only %s free against a %s reserve; giving up %d rebuildable image(s).',
            HostPrewarmPlan::formatBytes($available),
            HostPrewarmPlan::formatBytes($reserve),
            count($drop)
        ));

        return $this->removeImages($system, $present, $drop, 'reclaimed');
    }

    /**
     * What each present image costs on disk, keyed by ref.
     *
     * `docker images` prints human sizes and has no raw-byte format, so they
     * come back through the same parser the --budget option uses. A size that
     * will not parse is dropped rather than guessed at: an image of unknown
     * cost must not be chosen for deletion on the strength of a zero.
     *
     * @param list<string> $present
     * @return array<string, int>
     */
    private function presentImageSizes(System $system, array $present): array
    {
        $wanted = array_flip($present);
        try {
            $out = $system->exec(
                ['sudo', 'docker', 'images', '--format', '{{.Repository}}:{{.Tag}}\t{{.Size}}'],
                [],
                60
            );
        } catch (\Exception $e) {
            return [];
        }

        $sizes = [];
        foreach (preg_split('/\r?\n/', $out) ?: [] as $line) {
            $parts = explode("\t", trim($line), 2);
            if (count($parts) !== 2 || !isset($wanted[$parts[0]])) {
                continue;
            }
            try {
                $bytes = HostPrewarmPlan::parseBytes(trim($parts[1]));
            } catch (\InvalidArgumentException $e) {
                continue;
            }
            if ($bytes !== null) {
                $sizes[$parts[0]] = $bytes;
            }
        }

        return $sizes;
    }

    /**
     * @param list<string> $present
     * @param list<string> $refs
     * @return list<string>
     */
    private function removeImages(System $system, array $present, array $refs, string $verb): array
    {
        if ($this->option('dry-run')) {
            foreach ($refs as $ref) {
                $this->line("would have {$verb} base image {$ref}");
            }

            return $present;
        }

        $removed = [];
        foreach ($refs as $ref) {
            try {
                $system->exec(['sudo', 'docker', 'rmi', '-f', $ref], [], 120);
                $this->line("{$verb} base image {$ref}");
                $removed[] = $ref;
            } catch (\Exception $e) {
                $this->warn("Could not remove {$ref}: " . $e->getMessage());
            }
        }

        return array_values(array_diff($present, $removed));
    }

    /**
     * Intermediate layers from the PHP base builds are dead weight once the
     * images exist — on a measured host that is ~1.5GB of the budget back.
     */
    private function pruneBuildCache(System $system): void
    {
        try {
            $system->exec(['sudo', 'docker', 'buildx', 'prune', '-af'], [], 300);
        } catch (\Exception $e) {
            $this->warn('Could not prune the build cache: ' . $e->getMessage());
        }
    }

    /**
     * Remove images we built that a current recipe would no longer produce,
     * before measuring the budget - they are dead weight and their space is
     * exactly what the new build needs.
     *
     * Every runtime the catalogue says the engine builds for, not just PHP:
     * Ruby's bases accumulate one per distinct apt package set and nothing
     * used to remove any of them.
     *
     * @param list<string> $present
     * @return list<string> $present minus what was removed
     */
    private function dropStaleBaseImages(System $system, array $present): array
    {
        $stale = HostPrewarmPlan::staleBuiltImages($present);
        if ($stale === []) {
            return $present;
        }

        if ($this->option('dry-run')) {
            foreach ($stale as $ref) {
                $this->line("would remove superseded base image {$ref}");
            }

            return $present;
        }

        foreach ($stale as $ref) {
            try {
                $system->exec(['sudo', 'docker', 'rmi', '-f', $ref], [], 120);
                $this->line("removed superseded base image {$ref}");
            } catch (\Exception $e) {
                $this->warn("Could not remove {$ref}: " . $e->getMessage());
            }
        }

        return array_values(array_diff($present, $stale));
    }

    /**
     * @return list<string>
     */
    private function presentImages(System $system): array
    {
        try {
            $out = $system->exec(
                ['sudo', 'docker', 'images', '--format', '{{.Repository}}:{{.Tag}}'],
                [],
                60
            );
        } catch (\Exception $e) {
            return [];
        }

        $refs = [];
        foreach (preg_split('/\r?\n/', $out) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && !str_ends_with($line, ':<none>')) {
                $refs[] = $line;
            }
        }

        return $refs;
    }

    /**
     * Free space on the filesystem holding the images. Falls back to / so the
     * command still works where the data root is elsewhere or not yet created.
     */
    private function availableBytes(System $system): ?int
    {
        foreach (['/var/lib/docker', '/'] as $path) {
            try {
                // No sudo: df is a read-only stat and works as any user.
                $out = $system->exec(['df', '-Pk', $path], [], 60);
            } catch (\Exception $e) {
                continue;
            }

            $lines = preg_split('/\r?\n/', trim($out)) ?: [];
            if (count($lines) < 2) {
                continue;
            }
            $fields = preg_split('/\s+/', trim($lines[count($lines) - 1])) ?: [];
            if (isset($fields[3]) && ctype_digit($fields[3])) {
                return ((int) $fields[3]) * 1024;
            }
        }

        return null;
    }

    /**
     * @param array{selected: list<array<string, mixed>>, skipped: list<array<string, mixed>>} $plan
     */
    private function renderPlan(array $plan): void
    {
        $rows = [];
        foreach ($plan['selected'] as $item) {
            $rows[] = $this->planRow('warm', $item);
        }
        foreach ($plan['skipped'] as $item) {
            $rows[] = $this->planRow((string) ($item['reason'] ?? 'skip'), $item);
        }

        $this->table(['', 'Image', 'Kind', 'Priority', 'Download', 'Why'], $rows);
    }

    /**
     * @param array<string, mixed> $item
     * @return list<string>
     */
    private function planRow(string $verdict, array $item): array
    {
        $bytes = $item['bytes'] ?? null;

        return [
            $verdict,
            (string) $item['ref'],
            (string) $item['kind'],
            (string) ($item['prewarm'] ?? ''),
            HostPrewarmPlan::formatBytes(is_int($bytes) ? $bytes : null),
            (string) $item['why'],
        ];
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}

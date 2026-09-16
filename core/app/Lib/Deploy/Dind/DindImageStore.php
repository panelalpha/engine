<?php

namespace App\Lib\Deploy\Dind;

use App\Lib\Deploy\Template\Template;

use App\Lib\Deploy\CacheManager\ImageTransfer;
use App\Lib\Deploy\Engine\EngineAccount;
use App\Lib\Deploy\Engine\ImageStore;

/**
 * Seeds an account's nested Docker daemon from the host's image cache.
 *
 * Baking base images into the account template does not work: the nested
 * daemon's data-root is `~/docker`, not the outer image's `/var/lib/docker`,
 * so anything baked in is not there when the daemon starts. The host pulls each
 * image once and every account gets it across the compose exec, which beats a
 * pull through the inner daemon's nested NAT even for an unshared image.
 *
 * Reference validation and the seeding heuristics live in {@see ImageTransfer};
 * this class is only the Docker spelling of them. Builds commands only.
 */
final class DindImageStore implements ImageStore
{
    /**
     * The local registry as an **account** addresses it.
     *
     * Every account's daemon is created with this in `insecure-registries`
     * ({@see \App\System\Project\Dind}), and the name resolves on
     * the engine network, so a pull needs no further setup.
     */
    public const CACHE_REGISTRY = 'panelalpha-cache-registry:5000';

    /**
     * The same registry as the **host** addresses it, which is not the same
     * string and cannot be.
     *
     * `panelalpha-cache-registry` is a name on the engine's docker network and
     * the host is not on it: `docker push` there resolves against the host's
     * own DNS, misses, and falls back to HTTPS against a plain-HTTP registry.
     * Every push failed that way, the guard below caught it, and the whole
     * registry path never ran — which looked like the registry not being
     * installed.
     *
     * Loopback needs no daemon configuration: docker treats 127.0.0.0/8 as
     * insecure by default. The repository path is what the registry stores, so
     * an image pushed to one address is pulled from the other.
     */
    public const HOST_CACHE_REGISTRY = '127.0.0.1:5000';

    /**
     * `docker build -` takes the Dockerfile on stdin, which is all a base image
     * needs — nothing is COPYed in. Idempotent, so safe to call per deploy.
     *
     * buildx attaches provenance and SBOM manifests by default, turning the
     * result into an OCI index that `docker load` in an account cannot import;
     * suppressing them is what makes the image seedable. The retry without the
     * flags covers hosts that only have the classic builder.
     */
    public function hostBuildCommand(string $tag, string $dockerfile): string
    {
        $img = escapeshellarg($tag);
        $doc = escapeshellarg($dockerfile);
        $build = "printf '%s' {$doc} | sudo docker build --pull";

        return "sudo docker image inspect {$img} >/dev/null 2>&1"
            . " || { {$build} --provenance=false --sbom=false -t {$img} - || {$build} -t {$img} -; }";
    }

    public function importFromHostCommand(EngineAccount $account, string $image): string
    {
        $img = escapeshellarg($image);

        return "sudo docker image inspect -- {$img} >/dev/null 2>&1 || sudo docker pull -- {$img}"
            . ' && ' . $this->loadFromHostCommand($account, $image);
    }

    /**
     * Put a host image into an account's Docker daemon.
     *
     * Through the shared registry when it is running, falling back to
     * `save | load`. The registry is not faster for one image into an empty
     * account — measured 56s against 57s for a 1GB base, both paths
     * decompressing the same bytes into overlay2 over loopback. What it buys is
     * **layer reuse**: `save` streams every layer whatever the target holds,
     * `pull` fetches only what is missing. Second image sharing a base:
     * **1s against 17s**, which is the common case — the plain and imagick PHP
     * bases differ by one layer.
     */
    public function loadFromHostCommand(EngineAccount $account, string $image): string
    {
        $img = escapeshellarg($image);
        $file = escapeshellarg($account->controlFileOrFail());
        $service = DindEngine::SERVICE;
        // Two addresses for one registry: the host pushes to loopback, the
        // account pulls by the network name it already trusts.
        $pushRef = escapeshellarg(self::HOST_CACHE_REGISTRY . '/' . $image);
        $pullRef = escapeshellarg(self::CACHE_REGISTRY . '/' . $image);

        $exec = "sudo docker compose -f {$file} exec -T {$service}";
        $saveLoad = "sudo docker save -- {$img} | {$exec} docker load";

        // Every step is guarded: a registry up but unpushable, or an account
        // that cannot reach it, falls back instead of failing the deploy. The
        // tag inside the account is restored to the plain name so nothing
        // downstream needs to know which path ran.
        $viaRegistry = "sudo docker tag {$img} {$pushRef}"
            . " && sudo docker push -q {$pushRef} >/dev/null 2>&1"
            . " && {$exec} docker pull -q {$pullRef} >/dev/null 2>&1"
            . " && {$exec} docker tag {$pullRef} {$img}";

        // Probed from the host, so on the host's address. The network name
        // cannot resolve from this side and probing it only gave false negatives.
        return "if sudo curl -sf --max-time 3 http://" . self::HOST_CACHE_REGISTRY . "/v2/ >/dev/null 2>&1; then"
            . " { {$viaRegistry}; } || { {$saveLoad}; };"
            . " else {$saveLoad}; fi";
    }

    /**
     * Needs bash for `wait -n`; run it as ['bash', '-c', $script]. Every step
     * is best-effort — `compose up` pulls whatever is still missing.
     *
     * @param list<string> $images
     */
    public function parallelImportCommand(EngineAccount $account, array $images, int $concurrency): string
    {
        $refs = self::safeRefs($images);
        if ($refs === []) {
            return 'true';
        }

        return Template::named('script/seed-images')->render([
            'compose' => escapeshellarg($account->controlFileOrFail()),
            'service' => DindEngine::SERVICE,
            'concurrency' => max(1, min($concurrency, ImageTransfer::MAX_CONCURRENCY)),
            'images' => implode(' ', $refs),
        ]);
    }

    /**
     * @param list<string> $images
     * @return list<string> shell-quoted, unsafe references dropped
     */
    private static function safeRefs(array $images): array
    {
        $refs = [];
        foreach ($images as $image) {
            if (is_string($image) && $image !== '' && ImageTransfer::isSafeImageRef($image)) {
                $refs[] = escapeshellarg($image);
            }
        }

        return $refs;
    }

    /**
     * @return list<string>
     */
    public function listImagesArgv(): array
    {
        return ['docker', 'images', '--format', '{{.Repository}}:{{.Tag}}'];
    }

    /**
     * @return list<string>
     */
    public function imageIdArgv(string $image): array
    {
        return ['docker', 'images', '-q', $image];
    }

    /**
     * @return list<string>
     */
    public function pullArgv(string $image): array
    {
        return ['docker', 'pull', $image];
    }

    /**
     * @return list<string>
     */
    public function imageExposedPortsArgv(string $image): array
    {
        return ['docker', 'image', 'inspect', $image, '--format', '{{json .Config.ExposedPorts}}'];
    }

    /**
     * No sudo: this one goes through System::execOnHost(), which already
     * enters the host namespace as root.
     *
     * @return list<string>
     */
    public function hostImageInspectArgv(string $image): array
    {
        return ['docker', 'image', 'inspect', '--', $image];
    }

    /**
     * @return list<string>
     */
    public function hostPullArgv(string $image): array
    {
        return ['docker', 'pull', '--', $image];
    }

    /**
     * @return list<string>
     */
    public function hostImageExposedPortsArgv(string $image): array
    {
        return [
            'sudo', 'docker', 'image', 'inspect', '--format', '{{json .Config.ExposedPorts}}', '--', $image,
        ];
    }
}

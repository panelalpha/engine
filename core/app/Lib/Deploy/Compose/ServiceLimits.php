<?php

namespace App\Lib\Deploy\Compose;

use App\Lib\Deploy\Sidecar\SidecarEngine;

/**
 * How much of the account a service is allowed to use. A datastore keeps its
 * catalogue entry; everything else is the application, and Node production
 * images OOM under 256m, so the default is generous.
 */
final class ServiceLimits
{
    /**
     * Roles, not product names: what a service does in a stack.
     *
     * @var list<string>
     */
    private const CAPPED_APPLICATION_ROLES = [
        'worker', 'sidekiq', 'celery', 'queue', 'jobs', 'horizon', 'scheduler', 'cron',
        'app', 'web', 'api', 'backend', 'frontend', 'server', 'application', 'httpd', 'console',
    ];

    private const CAPPED_ROLE_MEMORY = '384m';

    private const DEFAULT_MEMORY = '512m';

    /** Held back from the account's budget for dockerd, sidecars and cache. */
    private const ACCOUNT_RESERVE_SHARE = 0.125;

    private const MIN_ACCOUNT_RESERVE_MB = 64;

    private const MAX_ACCOUNT_RESERVE_MB = 512;

    /** Below this an account is too small to run anything; give it the floor. */
    private const MIN_SERVICE_MEMORY_MB = 128;

    private const DEFAULT_HEAP_MB = 384;

    private const MIN_HEAP_MB = 128;

    /**
     * A third of the host's RAM for one build container: Chamilo 2.x's Encore
     * build OOMs at 2 GB/1433 MB heap and builds at ~6g/4300 MB. Safe because
     * `HostBuildSlot` holds builds to one, while `maxProcesses: 8` and
     * `DeployLock` being per account would otherwise let eight run together.
     */
    private const BUILD_MEMORY_DIVISOR = 3;

    /** Never below the number every engine has run with, whatever the host says. */
    private const MIN_BUILD_MEMORY_MB = 2048;

    /** Above this the operator has to say so with `DEPLOY_BUILD_MEMORY`. */
    private const MAX_BUILD_MEMORY_MB = 8192;

    private const PROC_MEMTOTAL_PATTERN = '/^MemTotal:\s+(\d+)\s*kB/mi';

    private const HEAP_SHARE = 0.70;

    private const HEAP_HEADROOM_MB = 64;

    private const BYTES_PER_MB = 1048576;

    /** @var array<string, float> */
    private const UNIT_TO_MB = ['k' => 1 / 1024, 'm' => 1, 'g' => 1024, 't' => 1048576];

    private const SIZE_PATTERN = '/^(\d+(?:\.\d+)?)\s*([kmgt])i?b?$/i';

    /**
     * A datastore keeps its catalogue size. Everything else answers to the
     * project's `memory_limit` when set, else the role default. The account
     * container carries the same limit and is the parent cgroup of the services,
     * so handing the figure to each of them is not additive.
     *
     * @param array<string, mixed> $service
     * @param int|null $accountMemoryMb the project's own limit, in MB
     */
    public static function memoryFor(string $name, array $service = [], ?int $accountMemoryMb = null): string
    {
        $engine = SidecarEngine::resolve($name, $service);
        $catalogued = $engine === null ? null : SidecarEngine::memoryLimitFor($engine);
        if ($catalogued !== null) {
            return $catalogued;
        }

        if ($accountMemoryMb !== null && $accountMemoryMb > 0) {
            return self::underAccountCeiling($accountMemoryMb) . 'm';
        }

        return in_array(strtolower($name), self::CAPPED_APPLICATION_ROLES, true)
            ? self::CAPPED_ROLE_MEMORY
            : self::DEFAULT_MEMORY;
    }

    /**
     * What one service may take out of the account's budget: an eighth, floored
     * at 64 MB and capped at 512. The rest is dockerd, the sidecars and page
     * cache sharing the account's cgroup, so an application holding the whole
     * budget can starve the daemon supervising it. Limits are ceilings, so
     * sidecars summing past the remainder is normal.
     */
    private static function underAccountCeiling(int $accountMemoryMb): int
    {
        $reserve = (int) round($accountMemoryMb * self::ACCOUNT_RESERVE_SHARE);
        $reserve = max(self::MIN_ACCOUNT_RESERVE_MB, min(self::MAX_ACCOUNT_RESERVE_MB, $reserve));

        // A very small account still gets the floor.
        return max(self::MIN_SERVICE_MEMORY_MB, $accountMemoryMb - $reserve);
    }

    /**
     * The memory a host build container gets when the operator has not said: a
     * third of MemTotal, floored at 2g and capped at 8g. `MemTotal:` is not
     * namespaced, so inside a container it reports the host's RAM, which is the
     * number that matters for a host build. Unreadable falls back to the floor.
     *
     * Measured on the 15 GB host: 2g/1433 MB heap OOMs Chamilo 2.x's Encore
     * build, ~6g/4300 MB builds it. nextcloud/server's webpack pass is the same
     * shape -- TerserPlugin forks one minifier per CPU and their heaps sum.
     */
    public static function hostBuildMemoryMb(string $procMeminfo): int
    {
        if (preg_match(self::PROC_MEMTOTAL_PATTERN, $procMeminfo, $m) !== 1) {
            return self::MIN_BUILD_MEMORY_MB;
        }
        $totalMb = (int) floor((int) $m[1] / 1024);
        if ($totalMb <= 0) {
            return self::MIN_BUILD_MEMORY_MB;
        }

        // intdiv, not a float share: a floor, without the floating-point boundary.
        $share = intdiv($totalMb, self::BUILD_MEMORY_DIVISOR);

        return max(self::MIN_BUILD_MEMORY_MB, min(self::MAX_BUILD_MEMORY_MB, $share));
    }

    /**
     * The V8 heap for a container of this size. Without a cap Node sizes its
     * heap from the host and is OOM-killed before a collection happens.
     *
     * @param mixed $memoryLimit
     */
    public static function nodeHeapMbFor($memoryLimit): int
    {
        $limitMb = self::toMegabytes($memoryLimit) ?? self::DEFAULT_HEAP_MB;

        return max(
            self::MIN_HEAP_MB,
            min($limitMb - self::HEAP_HEADROOM_MB, (int) floor($limitMb * self::HEAP_SHARE))
        );
    }

    /**
     * The JVM heap for a container of this size, in MB. HotSpot's
     * `MaxRAMPercentage` defaults to 25, so a JVM sizing itself inside an N-MB
     * cgroup gets N/4: the 2g build container gave Maven 512 MB and a
     * multi-module reactor died with `[ERROR] Java heap space` while three
     * quarters of the cgroup sat unused.
     *
     * @param mixed $memoryLimit
     */
    public static function javaHeapMbFor($memoryLimit): int
    {
        return self::nodeHeapMbFor($memoryLimit);
    }

    /**
     * The V8 heap for a build inside the account's own container, or null when
     * there is no ceiling to size it against: a cap of ours would be smaller
     * than the host-sized default Node already takes. A cap larger than the
     * cgroup is worse than none -- the kernel kills before any collection and
     * the only trace is a bare `Killed`.
     */
    public static function nodeHeapMbForAccount(?int $accountMemoryMb): ?int
    {
        if ($accountMemoryMb === null || $accountMemoryMb <= 0) {
            return null;
        }

        return self::nodeHeapMbFor($accountMemoryMb);
    }

    /**
     * @param mixed $limit a byte count or a Compose size string ("512m")
     */
    public static function toMegabytes($limit): ?int
    {
        if (is_int($limit) || (is_string($limit) && ctype_digit($limit))) {
            return self::bytesToMegabytes((int) $limit);
        }
        if (!is_string($limit) || preg_match(self::SIZE_PATTERN, trim($limit), $m) !== 1) {
            return null;
        }

        return max(1, (int) floor((float) $m[1] * (self::UNIT_TO_MB[strtolower($m[2])] ?? 1)));
    }

    private static function bytesToMegabytes(int $bytes): ?int
    {
        if ($bytes <= 0) {
            return null;
        }

        return $bytes >= self::BYTES_PER_MB ? max(1, (int) floor($bytes / self::BYTES_PER_MB)) : $bytes;
    }
}

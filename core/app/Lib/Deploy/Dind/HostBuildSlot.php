<?php

namespace App\Lib\Deploy\Dind;

use Illuminate\Support\Facades\Log;

/**
 * One host build at a time: each build is entitled to a third of MemTotal, which
 * Horizon's 8 workers would multiply past the host's RAM. The rest of the deploy
 * runs eight wide. Fails open after WAIT_SECONDS.
 */
final class HostBuildSlot
{
    /**
     * Long enough for a real build ahead of this one (PHP and Node host builds
     * cap at 1800s and 3600s). Passing it is not fatal.
     */
    private const WAIT_SECONDS = 1800;

    private const POLL_MICROSECONDS = 250000;

    private const LOCK_FILE = 'host-build.lock';

    /**
     * Run `$build` with the host's build slot held, releasing it either way.
     *
     * @template T
     * @param callable(): T $build
     * @param ?callable(): void $onWait called once when the slot is busy
     * @return T
     */
    public static function run(callable $build, ?callable $onWait = null): mixed
    {
        $handle = self::acquire($onWait);

        try {
            return $build();
        } finally {
            if (is_resource($handle)) {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        }
    }

    /**
     * @param ?callable(): void $onWait
     * @return resource|null the held lock, or null when the build proceeds unslotted
     */
    private static function acquire(?callable $onWait)
    {
        $handle = @fopen(storage_path(self::LOCK_FILE), 'c');
        if ($handle === false) {
            // Nowhere to put the lock is not a reason to refuse to build.
            Log::warning('Host build slot unavailable; building without it.');

            return null;
        }

        $deadline = microtime(true) + self::WAIT_SECONDS;
        $waited = false;
        while (!flock($handle, LOCK_EX | LOCK_NB)) {
            if (!$waited) {
                $waited = true;
                if ($onWait !== null) {
                    $onWait();
                }
            }
            if (microtime(true) >= $deadline) {
                fclose($handle);
                Log::warning('Host build slot still held after ' . self::WAIT_SECONDS . 's; building anyway.');

                return null;
            }
            usleep(self::POLL_MICROSECONDS);
        }

        return $handle;
    }
}

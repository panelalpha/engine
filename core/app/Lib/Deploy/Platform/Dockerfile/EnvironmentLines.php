<?php

namespace App\Lib\Deploy\Platform\Dockerfile;

use App\Lib\Deploy\Platform\PlatformStage;

/**
 * `ENV key=value`, one per entry, in the order the caller settled on.
 *
 * `PA_DEPLOY_PHASE` is excluded: it is a runtime value compose passes, and as an
 * `ENV` above the dependency install its switch to `upgrade` invalidated that
 * layer and below -- a Django rebuild of an unchanged commit re-ran `pip install`.
 */
final class EnvironmentLines
{
    /**
     * @param array<string, string> $env
     * @return list<string>
     */
    public static function of(array $env): array
    {
        $lines = [];
        foreach ($env as $key => $value) {
            if ($key === PlatformStage::PHASE_ENV) {
                continue;
            }
            $lines[] = 'ENV ' . $key . '=' . $value;
        }

        return $lines;
    }

    /**
     * `ARG key=value`, one per entry: a build-time default absent from the
     * resulting image. For `NODE_OPTIONS` that matters: a heap sized for the
     * engine's build container exceeds the account's cgroup, and V8 reaches
     * for it and is OOM-killed. Reaches every process the build spawns.
     *
     * @param array<string, string> $env
     * @return list<string>
     */
    public static function buildArgs(array $env): array
    {
        $lines = [];
        foreach ($env as $key => $value) {
            $lines[] = 'ARG ' . $key . '=' . $value;
        }

        return $lines;
    }
}

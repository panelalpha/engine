<?php

namespace App\Lib\Deploy\Platform\Dockerfile;

/**
 * A package manager's download cache is not needed at runtime but lands in the
 * image layer; a BuildKit `RUN --mount` cache keeps it out of the layer and
 * persists it in the account's own daemon between builds.
 */
final class PackageManagerCache
{
    /**
     * Each package manager's download cache path, as root, in the official
     * images the recipes build on. A wrong path here silently caches nothing.
     *
     * @var array<string, string>
     */
    private const DIRECTORIES = [
        'npm' => '/root/.npm',
        // Measured: 1.6 GB for a Next.js starter's yarn install.
        'yarn' => '/usr/local/share/.cache/yarn',
        'pnpm' => '/root/.local/share/pnpm/store',
        'bun' => '/root/.bun/install/cache',
        'pip' => '/root/.cache/pip',
    ];

    /**
     * `--mount=type=cache,target=<dir>,sharing=locked ` for one package
     * manager, or '' for one this class does not know, so a caller can
     * concatenate unconditionally. `sharing=locked` is required: npm and yarn
     * are not safe against a concurrent process mutating their cache dir.
     */
    public static function mountFor(string $packageManager): string
    {
        $directory = self::DIRECTORIES[$packageManager] ?? null;
        if ($directory === null) {
            return '';
        }

        // Part of the RUN line: adding or removing it changes that layer's cache key.
        return sprintf('--mount=type=cache,target=%s,sharing=locked ', $directory);
    }

    /** The cache directory for a package manager, for tests and diagnostics. */
    public static function directoryFor(string $packageManager): ?string
    {
        return self::DIRECTORIES[$packageManager] ?? null;
    }
}

<?php

namespace App\Lib\Deploy\Platform\Runtime;

use App\Lib\Deploy\Platform\ProjectContext;

/**
 * .NET.
 *
 * Version source: `global.json` sdk.version, then the highest `netX.Y`
 * TargetFramework; the SDK is backwards compatible. One image builds and runs.
 */
final class DotnetRuntime implements Runtime
{
    /** Compiled-in fallback for the catalogue's `image.from`. */
    public const IMAGE = 'mcr.microsoft.com/dotnet/sdk:8.0';

    public const VERSION = '8.0';

    /** `dotnet publish` output directory, relative to the project. */
    public const PUBLISH_DIR = 'out';

    public static function defaultImage(): string
    {
        return self::imageTag(self::VERSION);
    }

    public static function imageTag(string $version): string
    {
        $spec = RuntimeImageCatalog::spec('dotnet', $version);

        return $spec?->from ?? self::IMAGE;
    }

    public function id(): string
    {
        return 'dotnet';
    }

    public function resolve(ProjectContext $context): ?Requirement
    {
        $global = $context->json('global.json');
        if (is_array($global)) {
            $sdk = $global['sdk']['version'] ?? null;
            if (is_string($sdk) && preg_match('/^(\d+)\.(\d+)/', $sdk, $m) === 1) {
                return new Requirement('dotnet', $m[1] . '.0', $sdk, 'global.json sdk.version');
            }
        }

        $target = self::targetFramework($context->projectDir);
        if ($target !== null) {
            return new Requirement('dotnet', $target, 'net' . $target, 'TargetFramework');
        }

        return self::hasProject($context->projectDir)
            ? new Requirement('dotnet', self::VERSION, '', 'engine default')
            : null;
    }

    public function defaultRequirement(): Requirement
    {
        return new Requirement('dotnet', self::VERSION, '', 'engine default');
    }

    /**
     * @return list<string>
     */
    public function supportedVersions(): array
    {
        return ['8.0', '9.0'];
    }

    public function image(Requirement $requirement): string
    {
        return self::imageTag($requirement->version);
    }

    /**
     * A solution listing a C#, F# or VB project, or such a project file.
     * Searched three levels down: many repositories keep sources under src/.
     */
    public static function hasProject(string $projectDir): bool
    {
        return self::findProjectFiles($projectDir) !== [];
    }

    /**
     * @return list<string> paths relative to the project root
     */
    public static function findProjectFiles(string $projectDir): array
    {
        $projectDir = rtrim($projectDir, '/');
        $found = [];

        foreach (['*.sln', '*.slnx', '*.csproj', '*.fsproj', '*.vbproj'] as $pattern) {
            // root, Jellyfin.Server/, src/Foo/
            foreach (['/', '/*/', '/*/*/'] as $depth) {
                foreach (glob($projectDir . $depth . $pattern) ?: [] as $path) {
                    if (str_contains($pattern, '.sln') && !self::solutionIsManaged($path)) {
                        continue;
                    }
                    $relative = ltrim(substr($path, strlen($projectDir)), '/');
                    $found[$relative] = true;
                }
            }
        }

        $names = array_keys($found);
        sort($names);

        return $names;
    }

    /**
     * A C++-only solution (Seafile's holds just a .vcxproj) is not .NET and
     * cannot be published.
     */
    private static function solutionIsManaged(string $path): bool
    {
        $contents = @file_get_contents($path);
        if (!is_string($contents)) {
            return false;
        }

        return str_ends_with($path, '.slnx')
            ? preg_match('/Path\s*=\s*"[^"]*\.(cs|fs|vb)proj"/i', $contents) === 1
            : preg_match('/^\s*Project\("[^"]*"\)\s*=\s*"[^"]*"\s*,\s*"[^"]*\.(cs|fs|vb)proj"/mi', $contents) === 1;
    }

    /**
     * The one project to publish: a web SDK project, else one declaring
     * `OutputType Exe`. Publishing a solution instead fails with `NETSDK1194`
     * (test projects error and MSBuild's exit code is the build's). Tests are
     * excluded by path and name. Null when nothing looks like an application.
     */
    public static function entryProject(string $projectDir): ?string
    {
        $webSdk = null;
        $executable = null;

        foreach (self::findProjectFiles($projectDir) as $relative) {
            if (!preg_match('/\.(cs|fs|vb)proj$/', $relative) || self::looksLikeTests($relative)) {
                continue;
            }
            $contents = @file_get_contents(rtrim($projectDir, '/') . '/' . $relative);
            if (!is_string($contents)) {
                continue;
            }
            if (stripos($contents, 'Microsoft.NET.Sdk.Web') !== false) {
                $webSdk ??= $relative;
                continue;
            }
            if (preg_match('/<OutputType>\s*Exe\s*<\/OutputType>/i', $contents) === 1) {
                $executable ??= $relative;
            }
        }

        return $webSdk ?? $executable;
    }

    /**
     * A test project, by the two conventions every .NET repository follows.
     */
    private static function looksLikeTests(string $relative): bool
    {
        $lower = strtolower($relative);

        return str_contains($lower, 'tests/')
            || str_contains($lower, 'test/')
            || (bool) preg_match('/\.tests?\.(cs|fs|vb)proj$/', $lower);
    }

    /**
     * The highest `net<major>.<minor>` any project file targets, solution-wide
     * -- the SDK that builds the newest builds the rest. `netstandard2.0` and
     * `net48` are library targets and are ignored.
     */
    public static function targetFramework(string $projectDir): ?string
    {
        $best = null;
        foreach (self::findProjectFiles($projectDir) as $relative) {
            if (str_ends_with($relative, '.sln') || str_ends_with($relative, '.slnx')) {
                continue;
            }
            $contents = @file_get_contents(rtrim($projectDir, '/') . '/' . $relative);
            if (!is_string($contents)) {
                continue;
            }
            if (preg_match_all('/net(\d+)\.(\d+)/i', $contents, $matches, PREG_SET_ORDER) < 1) {
                continue;
            }
            foreach ($matches as $m) {
                $version = $m[1] . '.' . $m[2];
                if ($best === null || version_compare($version, $best, '>')) {
                    $best = $version;
                }
            }
        }

        return $best;
    }

    /**
     * `dotnet build` scatters output across each project's bin/; publish
     * gathers one runnable directory. No `--no-restore`: restore is skipped
     * nowhere and omitting it fails a clean checkout.
     */
    public static function buildCommand(string $projectDir = ''): string
    {
        $target = $projectDir === '' ? null : self::entryProject($projectDir);

        return 'dotnet publish' . ($target === null ? '' : ' ' . escapeshellarg($target))
            . ' -c Release -o ' . self::PUBLISH_DIR . ' --nologo';
    }

    /**
     * The entry assembly is the one with a runtimeconfig beside it -- a publish
     * directory holds dozens of library DLLs and no name distinguishes them.
     * With nothing runnable this exits instead of restart-looping behind a 502.
     */
    public static function startCommand(): string
    {
        $dir = self::PUBLISH_DIR;

        return 'cfg=$(ls ' . $dir . '/*.runtimeconfig.json 2>/dev/null | head -n 1);'
            . ' [ -n "$cfg" ]'
            . ' || { echo "PANELALPHA: dotnet publish produced no runnable assembly in ' . $dir . '/";'
            . ' echo "PANELALPHA: published files: $(ls ' . $dir . ' 2>/dev/null | head -n 20)";'
            . ' exit 1; };'
            . ' exec dotnet "${cfg%.runtimeconfig.json}.dll"';
    }
}

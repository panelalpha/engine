<?php

namespace App\Lib\Deploy\CacheManager;

use App\Lib\Deploy\Platform\Runtime\RuntimeImageCatalog;
use App\Lib\Deploy\Template\Template;

/**
 * A PanelAlpha-owned `python:*-slim` derivative carrying the headers a source
 * build needs — apt packages baked once on the host instead of failed on in
 * every account.
 *
 * A deploy waits for this image (`runnable: true` in the catalogue) where
 * Ruby's defers: Ruby's per-project Dockerfile installs the same packages
 * itself, while Python has no per-project Dockerfile and this is the only
 * place the headers exist.
 */
class PythonBaseImage
{
    /** Beyond this, another ~250 MB image is not worth it and the project installs its own. */
    public const MAX_PACKAGES = 12;

    /**
     * Where the engine puts this image, per the catalogue. Null when it
     * describes no python build, and the account uses the stock image.
     */
    public static function repository(): ?string
    {
        return RuntimeImageCatalog::repository('python');
    }

    public static function stubName(): ?string
    {
        return RuntimeImageCatalog::stub('python');
    }

    private static function upstreamRepository(): ?string
    {
        return RuntimeImageCatalog::upstreamRepository('python');
    }

    /**
     * Null for anything we cannot prebuild — not a plain official python tag,
     * or a package set that is empty or over {@see MAX_PACKAGES} — so the
     * caller keeps the stock path.
     *
     * @param list<string> $packages
     */
    public static function tag(string $pythonImage, array $packages): ?string
    {
        $repository = self::repository();
        $upstream = self::upstreamRepository();
        // No catalogue entry, no image to name: a tag {@see BuiltImage} could
        // not recognise back would go to a registry that never published it.
        if ($repository === null || $upstream === null || self::stubName() === null) {
            return null;
        }
        if (preg_match('#^' . preg_quote($upstream, '#') . ':([a-z0-9._-]+)$#i', trim($pythonImage), $matches) !== 1) {
            return null;
        }
        $packages = self::normalizePackages($packages);
        if ($packages === [] || count($packages) > self::MAX_PACKAGES) {
            return null;
        }

        return $repository . ':' . $matches[1] . '-pa' . self::fingerprint($packages);
    }

    /**
     * Sorted and de-duplicated, so the same set resolves to the same tag
     * whatever order the requirements mentioned its packages in.
     *
     * @param list<string> $packages
     * @return list<string>
     */
    public static function normalizePackages(array $packages): array
    {
        $clean = [];
        foreach ($packages as $name) {
            // apt names are letters, digits and . + - only; the rest is not
            // going near a shell.
            if (is_string($name) && preg_match('/^[a-z0-9][a-z0-9.+-]*$/i', $name) === 1) {
                $clean[strtolower($name)] = true;
            }
        }
        $names = array_keys($clean);
        sort($names);

        return $names;
    }

    /**
     * @param list<string> $packages
     */
    public static function fingerprint(array $packages): string
    {
        return substr(sha1(implode(' ', self::normalizePackages($packages))), 0, 8);
    }

    /**
     * The official python tag this was built from, so a host that pruned the
     * image can rebuild it. Null for anything that is not one of ours.
     */
    public static function sourceImage(string $tag): ?string
    {
        $source = BuiltImage::sourceImage($tag);

        return $source !== null && BuiltImage::runtimeFor($tag) === 'python' ? $source : null;
    }

    /**
     * @param list<string> $packages
     */
    public static function dockerfile(string $pythonImage, array $packages): ?string
    {
        $stub = self::stubName();
        if ($stub === null) {
            return null;
        }

        return Template::named($stub)->render([
            'python_image' => $pythonImage,
            'packages' => implode(' ', self::normalizePackages($packages)),
        ]);
    }
}

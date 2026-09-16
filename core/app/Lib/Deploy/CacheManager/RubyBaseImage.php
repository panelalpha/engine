<?php

namespace App\Lib\Deploy\CacheManager;

use App\Lib\Deploy\Platform\Runtime\RuntimeImageCatalog;
use App\Lib\Deploy\Template\Template;

/**
 * A PanelAlpha-owned `ruby:*-slim-bookworm` derivative with the apt packages
 * native gems need already installed.
 *
 * Same trade as {@see PhpBaseImage}: `apt-get install build-essential
 * libpq-dev …` costs ~14s inside every account's build, produces a
 * byte-identical result every time, and cannot be shared through the registry
 * cache because a tenant build must never export layers. Building it once on
 * the host takes that off the deploy path for every account that follows;
 * {@see \App\Lib\Deploy\Engine\ImageStore::hostBuildCommand()} builds it.
 *
 * The package set varies by which database gem the Gemfile asks for, so the
 * tag carries a fingerprint of it — a Postgres app and a MySQL app get
 * different bases and neither serves the other a wrong one.
 */
class RubyBaseImage
{
    /**
     * Where the engine puts this image, per the catalogue. Null when it
     * describes no ruby build, and the account uses the stock image.
     */
    public static function repository(): ?string
    {
        return RuntimeImageCatalog::repository('ruby');
    }

    public static function stubName(): ?string
    {
        return RuntimeImageCatalog::stub('ruby');
    }

    private static function upstreamRepository(): ?string
    {
        return RuntimeImageCatalog::upstreamRepository('ruby');
    }

    /** Beyond this, another ~250 MB image is not worth it and the project installs its own. */
    public const MAX_PACKAGES = 8;

    /**
     * Null when the image is not a plain official ruby tag — a custom base is
     * nothing we can prebuild, so the caller keeps the stock path.
     *
     * @param list<string> $packages
     */
    public static function tag(string $rubyImage, array $packages): ?string
    {
        $repository = self::repository();
        $upstream = self::upstreamRepository();
        // No catalogue entry, no image to name: a tag {@see BuiltImage} could
        // not recognise back would go to a registry that never published it.
        if ($repository === null || $upstream === null || self::stubName() === null) {
            return null;
        }
        if (preg_match('#^' . preg_quote($upstream, '#') . ':([a-z0-9._-]+)$#i', trim($rubyImage), $matches) !== 1) {
            return null;
        }
        $packages = self::normalizePackages($packages);
        if ($packages === [] || count($packages) > self::MAX_PACKAGES) {
            return null;
        }

        return $repository . ':' . $matches[1] . '-pa' . self::fingerprint($packages);
    }

    /**
     * Sorted and de-duplicated, so the same set always resolves to the same
     * tag whatever order the Gemfile happened to mention its gems in.
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
     * The official ruby tag this was built from, so a host that pruned the
     * image can rebuild it. Null for anything that is not one of ours.
     */
    public static function sourceImage(string $tag): ?string
    {
        $source = BuiltImage::sourceImage($tag);

        return $source !== null && BuiltImage::runtimeFor($tag) === 'ruby' ? $source : null;
    }

    /**
     * @param list<string> $packages
     */
    public static function dockerfile(string $rubyImage, array $packages): ?string
    {
        $stub = self::stubName();
        if ($stub === null) {
            return null;
        }

        return Template::named($stub)->render([
            'ruby_image' => $rubyImage,
            'packages' => implode(' ', self::normalizePackages($packages)),
        ]);
    }
}

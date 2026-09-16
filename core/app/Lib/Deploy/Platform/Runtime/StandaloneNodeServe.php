<?php

namespace App\Lib\Deploy\Platform\Runtime;

use App\Lib\Deploy\Template\Template;

/**
 * Serving a host-compiled JS server out of stock Node: Nitro (Nuxt, TanStack
 * Start) compiles to `.output/server` with dependencies inlined, and the serve
 * script starts whichever entry exists on disk.
 */
class StandaloneNodeServe
{
    public const FILENAME = 'panelalpha.serve.mjs';

    /**
     * Server files a host compile might produce, first match wins; the serve
     * script is rendered with the same list.
     *
     * @var list<string>
     */
    public const ENTRIES = [
        '.output/server/index.mjs',
        '.output/server/index.js',
        'dist/server/server.js',
        'dist/server/index.js',
        'dist/server/index.mjs',
    ];

    /**
     * Strategies whose build emits a self-contained server folder.
     *
     * @var list<string>
     */
    public const STRATEGIES = ['nuxt', 'tanstack-start'];

    /**
     * Output directories the serve script may read; all are bind-mounted so
     * the runtime probe works whichever the build emitted.
     *
     * @var list<string>
     */
    private const VOLUME_SOURCES = ['.output', 'dist'];

    private const BUN = 'bun';

    private const NODE = 'node';

    /**
     * @param callable(string): bool $exists
     */
    public static function firstEntry(string $projectDir, callable $exists): ?string
    {
        $root = rtrim($projectDir, '/');
        foreach (self::ENTRIES as $relative) {
            if ($exists($root . '/' . $relative)) {
                return $relative;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function volumeSources(): array
    {
        return self::VOLUME_SOURCES;
    }

    public static function missingEntryMessage(): string
    {
        return 'Build finished but no server entry was produced (' . implode(', ', self::ENTRIES) . ')';
    }

    /**
     * bun.lock projects compile and run under bun, everything else on node:
     * mixing the two leaves bun-only packages unresolved at request time.
     */
    public static function startCommand(string $image): string
    {
        $binary = $image === HostNodeBuild::BUN_IMAGE ? self::BUN : self::NODE;

        return $binary . ' ' . self::FILENAME;
    }

    public static function script(): string
    {
        return Template::named('script/standalone-serve')->render([
            'entries' => self::entriesLiteral(),
        ]);
    }

    public static function isStandaloneStrategy(?string $strategy): bool
    {
        return $strategy !== null && in_array($strategy, self::STRATEGIES, true);
    }

    /** The entry list as the JS array literal the script declares. */
    private static function entriesLiteral(): string
    {
        $items = array_map(
            static fn (string $entry): string => "  '" . $entry . "',",
            self::ENTRIES
        );

        return implode("\n", array_merge(['['], $items, [']']));
    }
}

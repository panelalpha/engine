<?php

namespace App\Lib\Deploy\Platform\AppConfig;

/**
 * Reads descriptor files, wherever they happen to live.
 *
 * An app config is looked for in two places with different access rules: inside
 * the account's project directory, which on a real host is root-owned and
 * reached through sudo, and in the engine's own pages directory, which is
 * ordinary local filesystem. Both are "read this path" to the locator and
 * neither should be its problem, so the reading is injected.
 *
 * It is also what keeps this namespace testable without a host: a test hands
 * in {@see LocalAppConfigSource} over a temp directory.
 *
 * Listing is part of it because an app config is a directory now, not one file.
 */
interface AppConfigSource
{
    public function exists(string $path): bool;

    /** Contents, or null when the path is unreadable. */
    public function read(string $path): ?string;

    public function isDirectory(string $path): bool;

    /**
     * Paths of every file under $dir, relative to it, recursively.
     *
     * @return list<string>
     */
    public function listFiles(string $dir): array;
}

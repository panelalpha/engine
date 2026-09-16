<?php

namespace App\Lib\Deploy\Source;

/**
 * Validate archive contents before extraction into a user home.
 *
 * Two independent checks, deliberately fed from two different listings so
 * neither has to parse the other's format:
 *
 *  - {@see assertSafeListing()} takes a names-only listing (`unzip -Z1`,
 *    `tar -tzf`) and rejects absolute paths and `..` traversal. Names-only
 *    means filenames containing spaces survive intact.
 *  - {@see assertRegularMembersOnly()} takes a verbose listing (`unzip -Z`,
 *    `tar -tvzf`), reads nothing but the leading mode column, and rejects
 *    anything that is not a regular file or a directory. Symlinks are the
 *    reason: a `link -> /etc` member followed by `link/passwd` passes every
 *    name-based check and still writes outside the extraction directory.
 *
 * The caller must also make the archive immutable before listing it — see
 * Dind::importProjectArchive(), which stages a root-owned copy. Otherwise the
 * account user can swap the file between the check and the extraction.
 *
 * No Laravel dependencies — unit-testable.
 */
class ArchiveSafety
{
    private const MAX_ENTRIES = 100000;

    public static function assertSafeListing(string $listing): void
    {
        $entries = preg_split('/\r?\n/', $listing);
        if ($entries === false) {
            throw new \InvalidArgumentException('Could not inspect archive contents.');
        }

        $count = 0;
        foreach ($entries as $entry) {
            $entry = rtrim($entry, "\r");
            if ($entry === '') {
                continue;
            }
            $count++;
            if ($count > self::MAX_ENTRIES) {
                throw new \InvalidArgumentException('Archive contains too many entries.');
            }

            self::assertSafePath($entry);
        }
    }

    /**
     * Reject members that are not regular files or directories.
     *
     * Reads only the mode column at the start of a line, which both `unzip -Z`
     * and `tar -tvzf` emit in the same `-rw-r--r--` / `lrwxrwxrwx` shape. Lines
     * that do not start with a mode column are archive headers and summaries,
     * and are skipped.
     */
    public static function assertRegularMembersOnly(string $listing): void
    {
        $lines = preg_split('/\r?\n/', $listing);
        if ($lines === false) {
            throw new \InvalidArgumentException('Could not inspect archive contents.');
        }

        foreach ($lines as $line) {
            if (preg_match('/^([bcdlpsD?-])[rwxsStT-]{9}[.+@]?\s/', $line, $match) !== 1) {
                continue;
            }
            if ($match[1] === '-' || $match[1] === 'd') {
                continue;
            }
            if ($match[1] === 'l') {
                throw new \InvalidArgumentException(
                    'Archive contains a symbolic link, which could redirect extraction outside the project directory.'
                );
            }

            throw new \InvalidArgumentException('Archive contains a special file, which cannot be hosted.');
        }
    }

    public static function assertSafePath(string $path): void
    {
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Archive contains an invalid path.');
        }

        $path = str_replace('\\', '/', $path);
        if (str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:\//', $path) === 1) {
            throw new \InvalidArgumentException('Archive contains an absolute path.');
        }

        foreach (explode('/', $path) as $part) {
            if ($part === '..') {
                throw new \InvalidArgumentException('Archive contains a path outside the project directory.');
            }
        }
    }
}

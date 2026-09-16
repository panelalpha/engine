<?php

namespace App\Lib\Deploy\Source;

/**
 * Locate the project root inside an extracted archive.
 *
 * If the extract directory contains exactly one subdirectory (and no files),
 * that subdirectory is the project root — the usual "zip of a folder" case.
 * Otherwise the extract directory itself is the root (flat archive).
 *
 * No Laravel dependencies — unit-testable with a temp directory.
 */
class ProjectArchive
{
    public static function findSingleDir(string $tmpExtractDir): ?string
    {
        if (!is_dir($tmpExtractDir)) {
            return null;
        }

        $dir = null;
        foreach (scandir($tmpExtractDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.DS_Store') {
                continue;
            }
            $path = $tmpExtractDir . '/' . $entry;
            if (!is_dir($path)) {
                return null;
            }
            if ($dir !== null) {
                return null;
            }
            $dir = $path;
        }

        return $dir;
    }

    public static function resolveProjectRoot(string $tmpExtractDir): string
    {
        return self::findSingleDir($tmpExtractDir) ?? $tmpExtractDir;
    }
}

<?php

namespace App\Integrations\Storage;

use InvalidArgumentException;

abstract class AbstractStorage implements BackupStorage
{
    protected const PROBE_FILE = '.panelalpha-backup-probe';

    abstract public function put(string $key, $stream): void;

    /** @return resource */
    abstract public function readStream(string $key);

    abstract public function delete(string $key): void;

    abstract public function deletePrefix(string $prefix): void;

    abstract public function exists(string $key): bool;

    abstract public function size(string $key): int;

    abstract public function test(): void;

    /**
     * Relative path without leading/trailing slashes. May be empty.
     * Throws on ".." segments; NUL yields an empty string (caller decides).
     */
    protected function normalizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $path = trim($path, '/');
        if ($path === '' || str_contains($path, "\0")) {
            return '';
        }

        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                throw new InvalidArgumentException('Invalid key.');
            }
            $parts[] = $part;
        }

        return implode('/', $parts);
    }
}

<?php

namespace App\Integrations\Storage;

interface BackupStorage
{
    public function put(string $key, $stream): void;

    /** @return resource */
    public function readStream(string $key);

    public function delete(string $key): void;

    public function deletePrefix(string $prefix): void;

    public function exists(string $key): bool;

    public function size(string $key): int;

    public function test(): void;
}

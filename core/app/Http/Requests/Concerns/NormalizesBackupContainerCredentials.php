<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\ValidationException;

trait NormalizesBackupContainerCredentials
{
    public function assertLocalLocationIsSafe(string $location): void
    {
        $normalized = str_replace('\\', '/', trim($location));
        if ($normalized === '' || $normalized === '/' || $normalized === '/home') {
            throw ValidationException::withMessages([
                'location' => 'Local backup location must not be empty, filesystem root, or /home.',
            ]);
        }
        if (str_contains($normalized, "\0") || str_contains($normalized, '..')) {
            throw ValidationException::withMessages([
                'location' => 'Local backup location must not contain .. segments.',
            ]);
        }
    }

    /**
     * @param ?array<string, mixed> $credentials
     * @return ?array<string, mixed>
     */
    public function normalizeCredentials(string $driver, ?array $credentials): ?array
    {
        if ($driver === 'local') {
            return null;
        }

        if (!is_array($credentials)) {
            throw ValidationException::withMessages([
                'credentials' => 'Credentials are required for non-local backup storage.',
            ]);
        }

        if ($driver === 's3') {
            $this->requireCredentialKeys($credentials, ['access_key_id', 'secret_access_key'], 'credentials');

            return array_filter([
                'access_key_id' => $credentials['access_key_id'] ?? null,
                'secret_access_key' => $credentials['secret_access_key'] ?? null,
                'region' => $credentials['region'] ?? null,
                'endpoint' => $credentials['endpoint'] ?? null,
                'use_path_style_endpoint' => $credentials['use_path_style_endpoint'] ?? null,
                'prefix' => $credentials['prefix'] ?? null,
            ], static fn ($value) => $value !== null && $value !== '');
        }

        if (in_array($driver, ['ftp', 'ftps'], true)) {
            $this->requireCredentialKeys($credentials, ['host', 'username'], 'credentials');

            return array_filter([
                'host' => $credentials['host'] ?? null,
                'username' => $credentials['username'] ?? null,
                'password' => $credentials['password'] ?? null,
                'port' => $credentials['port'] ?? null,
            ], static fn ($value) => $value !== null && $value !== '');
        }

        if ($driver === 'sftp') {
            $this->requireCredentialKeys($credentials, ['host', 'username'], 'credentials');

            return array_filter([
                'host' => $credentials['host'] ?? null,
                'username' => $credentials['username'] ?? null,
                'password' => $credentials['password'] ?? null,
                'private_key' => $credentials['private_key'] ?? null,
                'passphrase' => $credentials['passphrase'] ?? null,
                'port' => $credentials['port'] ?? null,
            ], static fn ($value) => $value !== null && $value !== '');
        }

        throw ValidationException::withMessages([
            'driver' => 'Unsupported backup storage driver.',
        ]);
    }

    /**
     * @param array<string, mixed> $credentials
     * @param list<string> $keys
     */
    private function requireCredentialKeys(array $credentials, array $keys, string $field): void
    {
        $messages = [];
        foreach ($keys as $key) {
            $value = $credentials[$key] ?? null;
            if (!is_string($value) || $value === '') {
                $messages["{$field}.{$key}"] = "The {$key} field is required.";
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }
}

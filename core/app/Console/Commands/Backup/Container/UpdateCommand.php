<?php

namespace App\Console\Commands\Backup\Container;

use App\Models\BackupContainer;
use Illuminate\Console\Command;

class UpdateCommand extends Command
{
    protected $signature = 'backup:container:update {container : Container ID or name}
        {--name= : Container name}
        {--driver= : Storage driver (local, s3, ftp, ftps, sftp)}
        {--location= : Storage location}
        {--s3-access-key-id= : S3 access key ID}
        {--s3-secret-access-key= : S3 secret access key}
        {--s3-region= : S3 region}
        {--s3-endpoint= : S3 endpoint}
        {--s3-use-path-style : S3 path-style endpoint}
        {--s3-prefix= : S3 key prefix}
        {--ftp-host= : FTP/FTPS host}
        {--ftp-port= : FTP/FTPS port}
        {--ftp-username= : FTP/FTPS username}
        {--ftp-password= : FTP/FTPS password}
        {--sftp-host= : SFTP host}
        {--sftp-port= : SFTP port}
        {--sftp-username= : SFTP username}
        {--sftp-password= : SFTP password}
        {--sftp-private-key= : SFTP private key}
        {--sftp-passphrase= : SFTP private key passphrase}
        {--force : Skip confirmation}';

    protected $description = 'Update a backup container';

    public function handle(): int
    {
        $containerArg = $this->argument('container');
        if (!is_string($containerArg)) {
            $this->error('Invalid container identifier.');
            return 1;
        }

        $container = BackupContainer::findByIdOrName($containerArg);
        if ($container === null) {
            $this->error("Backup container '{$containerArg}' not found.");
            return 1;
        }

        $updates = [];

        $name = $this->stringOption('name');
        if ($name !== null) {
            if (BackupContainer::query()->where('name', $name)->where('id', '!=', $container->id)->exists()) {
                $this->error("A backup container named '{$name}' already exists.");
                return 1;
            }
            $updates['name'] = $name;
        }

        $driver = $this->stringOption('driver');
        if ($driver !== null) {
            if (!in_array($driver, ['local', 's3', 'ftp', 'ftps', 'sftp'], true)) {
                $this->error('Driver must be one of: local, s3, ftp, ftps, sftp.');
                return 1;
            }
            $updates['driver'] = $driver;
        }

        $location = $this->stringOption('location');
        if ($location !== null) {
            $updates['location'] = $location;
        }

        $effectiveDriver = $updates['driver'] ?? $container->driver;
        $credentialFlagsProvided = $this->credentialFlagsProvided($effectiveDriver);
        if ($credentialFlagsProvided) {
            try {
                $merged = array_merge($container->credentials ?? [], $this->buildCredentials($effectiveDriver) ?? []);
                $updates['credentials'] = $merged === [] ? null : $merged;
            } catch (\InvalidArgumentException $e) {
                $this->error($e->getMessage());
                return 1;
            }
        } elseif (($updates['driver'] ?? null) === 'local') {
            $updates['credentials'] = null;
        }

        if ($updates === []) {
            $this->error('No updates provided.');
            return 1;
        }

        $resultingDriver = $updates['driver'] ?? $container->driver;
        $resultingCredentials = array_key_exists('credentials', $updates)
            ? $updates['credentials']
            : $container->credentials;

        if ($resultingDriver !== 'local' && ($resultingCredentials === null || $resultingCredentials === [])) {
            $this->error('Credentials are required for non-local backup storage.');
            return 1;
        }

        try {
            $this->assertCredentialsComplete($resultingDriver, $resultingCredentials);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return 1;
        }

        $this->info('Current values:');
        $this->line("  Name: {$container->name}");
        $this->line("  Driver: {$container->driver}");
        $this->line("  Location: {$container->location}");
        $this->line('  Has credentials: ' . ($container->credentials !== null ? 'yes' : 'no'));

        $this->info('New values:');
        foreach ($updates as $key => $value) {
            if ($key === 'credentials') {
                $this->line('  Credentials: ' . ($value === null ? 'none' : 'updated'));
                continue;
            }
            $this->line('  ' . ucfirst($key) . ": {$value}");
        }

        if (!$this->option('force') && !$this->confirm('Apply these changes?')) {
            $this->info('Cancelled.');
            return 0;
        }

        $container->update($updates);
        $this->info('Backup container updated successfully.');

        return 0;
    }

    private function credentialFlagsProvided(string $driver): bool
    {
        if ($driver === 's3') {
            return $this->stringOption('s3-access-key-id') !== null
                || $this->stringOption('s3-secret-access-key') !== null
                || $this->stringOption('s3-region') !== null
                || $this->stringOption('s3-endpoint') !== null
                || $this->option('s3-use-path-style')
                || $this->stringOption('s3-prefix') !== null;
        }

        if (in_array($driver, ['ftp', 'ftps'], true)) {
            return $this->stringOption('ftp-host') !== null
                || $this->stringOption('ftp-port') !== null
                || $this->stringOption('ftp-username') !== null
                || $this->stringOption('ftp-password') !== null;
        }

        if ($driver === 'sftp') {
            return $this->stringOption('sftp-host') !== null
                || $this->stringOption('sftp-port') !== null
                || $this->stringOption('sftp-username') !== null
                || $this->stringOption('sftp-password') !== null
                || $this->stringOption('sftp-private-key') !== null
                || $this->stringOption('sftp-passphrase') !== null;
        }

        return false;
    }

    /**
     * @return ?array<string, mixed>
     */
    private function buildCredentials(string $driver): ?array
    {
        if ($driver === 'local') {
            return null;
        }

        if ($driver === 's3') {
            return array_filter([
                'access_key_id' => $this->stringOption('s3-access-key-id'),
                'secret_access_key' => $this->stringOption('s3-secret-access-key'),
                'region' => $this->stringOption('s3-region'),
                'endpoint' => $this->stringOption('s3-endpoint'),
                'use_path_style_endpoint' => $this->option('s3-use-path-style') ? true : null,
                'prefix' => $this->stringOption('s3-prefix'),
            ], static fn ($value) => $value !== null && $value !== '');
        }

        if (in_array($driver, ['ftp', 'ftps'], true)) {
            return array_filter([
                'host' => $this->stringOption('ftp-host'),
                'username' => $this->stringOption('ftp-username'),
                'password' => $this->stringOption('ftp-password'),
                'port' => $this->numericOption('ftp-port'),
            ], static fn ($value) => $value !== null && $value !== '');
        }

        if ($driver === 'sftp') {
            return array_filter([
                'host' => $this->stringOption('sftp-host'),
                'username' => $this->stringOption('sftp-username'),
                'password' => $this->stringOption('sftp-password'),
                'private_key' => $this->stringOption('sftp-private-key'),
                'passphrase' => $this->stringOption('sftp-passphrase'),
                'port' => $this->numericOption('sftp-port'),
            ], static fn ($value) => $value !== null && $value !== '');
        }

        throw new \InvalidArgumentException('Unsupported driver.');
    }

    /**
     * @param ?array<string, mixed> $credentials
     */
    private function assertCredentialsComplete(string $driver, ?array $credentials): void
    {
        if ($driver === 'local') {
            return;
        }

        if ($driver === 's3') {
            $this->requireCredentialValue($credentials, 'access_key_id');
            $this->requireCredentialValue($credentials, 'secret_access_key');
            return;
        }

        if (in_array($driver, ['ftp', 'ftps', 'sftp'], true)) {
            $this->requireCredentialValue($credentials, 'host');
            $this->requireCredentialValue($credentials, 'username');
        }
    }

    /**
     * @param ?array<string, mixed> $credentials
     */
    private function requireCredentialValue(?array $credentials, string $key): void
    {
        $value = $credentials[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("Credential {$key} is required for this driver.");
        }
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);
        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    private function numericOption(string $name): ?int
    {
        $value = $this->option($name);
        if (!is_string($value) || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}

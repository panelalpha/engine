<?php

namespace App\Console\Commands\Backup\Container;

use App\Models\BackupContainer;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

use App\Http\Requests\Concerns\NormalizesBackupContainerCredentials;

class CreateCommand extends Command
{
    use NormalizesBackupContainerCredentials;
    protected $signature = 'backup:container:create
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

    protected $description = 'Create a backup container';

    public function handle(): int
    {
        $name = $this->stringOption('name');
        $driver = $this->stringOption('driver');
        $location = $this->stringOption('location');

        if (!$this->input->isInteractive()) {
            if ($name === null) {
                $this->error('The --name option is required in non-interactive mode.');
                return 1;
            }
            if ($driver === null) {
                $this->error('The --driver option is required in non-interactive mode.');
                return 1;
            }
            if ($location === null) {
                $this->error('The --location option is required in non-interactive mode.');
                return 1;
            }
        } else {
            $name ??= $this->ask('Container name');
            $driver ??= $this->choice('Storage driver', ['local', 's3', 'ftp', 'ftps', 'sftp'], 0);
            $location ??= $this->ask('Storage location');
        }

        if (!is_string($name) || $name === '') {
            $this->error('Container name is required.');
            return 1;
        }
        if (!is_string($driver) || !in_array($driver, ['local', 's3', 'ftp', 'ftps', 'sftp'], true)) {
            $this->error('Driver must be one of: local, s3, ftp, ftps, sftp.');
            return 1;
        }
        if (!is_string($location) || $location === '') {
            $this->error('Storage location is required.');
            return 1;
        }
        if (strlen($location) > 1024) {
            $this->error('Storage location must be at most 1024 characters.');
            return 1;
        }
        if ($driver === 'local') {
            try {
                $this->assertLocalLocationIsSafe($location);
            } catch (ValidationException $e) {
                $messages = collect($e->errors())->flatten();
                $this->error($messages->first() ?? $e->getMessage());
                return 1;
            }
        }

        if (BackupContainer::query()->where('name', $name)->exists()) {
            $this->error("A backup container named '{$name}' already exists.");
            return 1;
        }

        try {
            $credentials = $this->buildCredentials($driver);
            $this->assertCredentialsComplete($driver, $credentials);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return 1;
        }

        $this->info('New backup container:');
        $this->line("  Name: {$name}");
        $this->line("  Driver: {$driver}");
        $this->line("  Location: {$location}");
        $this->line('  Has credentials: ' . ($credentials === null ? 'no' : 'yes'));

        if (!$this->option('force') && !$this->confirm('Create this container?')) {
            $this->info('Cancelled.');
            return 0;
        }

        /** @var BackupContainer $container */
        $container = BackupContainer::create([
            'name' => $name,
            'driver' => $driver,
            'location' => $location,
            'credentials' => $credentials,
        ]);

        $this->info("Backup container created (ID: {$container->id}).");
        return 0;
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
            $accessKeyId = $this->stringOption('s3-access-key-id');
            $secretAccessKey = $this->stringOption('s3-secret-access-key');
            if (!$this->input->isInteractive()) {
                if ($accessKeyId === null) {
                    throw new \InvalidArgumentException('The --s3-access-key-id option is required in non-interactive mode for s3.');
                }
                if ($secretAccessKey === null) {
                    throw new \InvalidArgumentException('The --s3-secret-access-key option is required in non-interactive mode for s3.');
                }
            }

            return array_filter([
                'access_key_id' => $accessKeyId,
                'secret_access_key' => $secretAccessKey,
                'region' => $this->stringOption('s3-region'),
                'endpoint' => $this->stringOption('s3-endpoint'),
                'use_path_style_endpoint' => $this->option('s3-use-path-style') ? true : null,
                'prefix' => $this->stringOption('s3-prefix'),
            ], static fn ($value) => $value !== null && $value !== '');
        }

        if (in_array($driver, ['ftp', 'ftps'], true)) {
            $host = $this->stringOption('ftp-host');
            $username = $this->stringOption('ftp-username');
            if (!$this->input->isInteractive()) {
                if ($host === null) {
                    throw new \InvalidArgumentException('The --ftp-host option is required in non-interactive mode for ftp/ftps.');
                }
                if ($username === null) {
                    throw new \InvalidArgumentException('The --ftp-username option is required in non-interactive mode for ftp/ftps.');
                }
            }

            return array_filter([
                'host' => $host,
                'username' => $username,
                'password' => $this->stringOption('ftp-password'),
                'port' => $this->numericOption('ftp-port'),
            ], static fn ($value) => $value !== null && $value !== '');
        }

        if ($driver === 'sftp') {
            $host = $this->stringOption('sftp-host');
            $username = $this->stringOption('sftp-username');
            if (!$this->input->isInteractive()) {
                if ($host === null) {
                    throw new \InvalidArgumentException('The --sftp-host option is required in non-interactive mode for sftp.');
                }
                if ($username === null) {
                    throw new \InvalidArgumentException('The --sftp-username option is required in non-interactive mode for sftp.');
                }
            }

            return array_filter([
                'host' => $host,
                'username' => $username,
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

<?php

namespace App\System\Services;

use App\System as EngineSystem;
use App\System\Services\Mysql\Databases;
use App\System\Services\Mysql\Privileges;
use App\System\Services\Mysql\Users;
use PDO;
use RuntimeException;

class Mysql
{
    private const DB_COMMAND_TIMEOUT = 7200;

    private ?PDO $pdo = null;

    /** @var string */
    protected $host = '%';

    public function __construct(
        private readonly ?EngineSystem $system = null,
    ) {
    }

    public function databases(): Databases
    {
        return new Databases($this->pdo());
    }

    public function users(): Users
    {
        return new Users($this->pdo(), $this->host);
    }

    public function privileges(): Privileges
    {
        return new Privileges($this->pdo(), $this->host);
    }

    public function dumpToGzip(string $name, string $dest): void
    {
        $this->assertDatabaseName($name);
        [$host, $user, $password] = $this->usersDbCredentials();

        $command = 'mysqldump --single-transaction -h ' . escapeshellarg($host)
            . ' -u ' . escapeshellarg($user)
            . ' ' . escapeshellarg($name)
            . ' | gzip -c > ' . escapeshellarg($dest);

        $this->runUsersDbCommand($command, $password, 'mysqldump failed');
    }

    public function restoreFromGzip(string $name, string $sqlGzPath): void
    {
        $this->assertDatabaseName($name);
        [$host, $user, $password] = $this->usersDbCredentials();

        $command = 'gunzip -c ' . escapeshellarg($sqlGzPath)
            . ' | mysql -h ' . escapeshellarg($host)
            . ' -u ' . escapeshellarg($user)
            . ' ' . escapeshellarg($name);

        $this->runUsersDbCommand($command, $password, 'database restore failed');
    }

    private function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        /** @var mixed $host */
        $host = config('env.USERS_DB_HOST');
        if (!is_string($host)) {
            $host = 'localhost';
        }
        /** @var mixed $user */
        $user = config('env.USERS_DB_USERNAME');
        if (!is_string($user)) {
            $user = 'root';
        }
        /** @var mixed $pass */
        $pass = config('env.USERS_DB_PASSWORD');
        if (!is_string($pass)) {
            $pass = '';
        }

        $this->pdo = new PDO("mysql:host={$host}", $user, $pass);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $this->pdo;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function usersDbCredentials(): array
    {
        $host = config('env.USERS_DB_HOST');
        $user = config('env.USERS_DB_USERNAME');
        $password = config('env.USERS_DB_PASSWORD');

        if (!is_string($host) || $host === '') {
            throw new RuntimeException('USERS_DB_HOST is not configured');
        }
        if (!is_string($user) || $user === '') {
            throw new RuntimeException('USERS_DB_USERNAME is not configured');
        }
        if (!is_string($password)) {
            throw new RuntimeException('USERS_DB_PASSWORD is not configured');
        }

        return [$host, $user, $password];
    }

    private function runUsersDbCommand(string $command, string $password, string $failurePrefix): void
    {
        $system = $this->system;
        if ($system === null) {
            throw new RuntimeException('Mysql dump/restore requires an Engine System');
        }

        $process = $system->runProcess(
            ['sh', '-c', $command],
            ['MYSQL_PWD' => $password],
            self::DB_COMMAND_TIMEOUT,
        );

        if (!$process->isSuccessful()) {
            $message = $process->getErrorOutput() ?: $process->getOutput();
            throw new RuntimeException($failurePrefix . ': ' . trim($message));
        }
    }

    private function assertDatabaseName(string $name): void
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new RuntimeException('Invalid database name');
        }
    }
}

<?php

namespace App\System\Services;

use App\Exceptions\DockerErrorException;
use App\System as EngineSystem;

class PureFtpd
{
    public function __construct(
        private EngineSystem $system,
    ) {
    }

    /**
     * @throws DockerErrorException
     */
    public function reload(): void
    {
        $this->exec(["touch", "/etc/pureftpd/pureftpd.passwd"]);
        $this->exec(["pure-pw", "mkdb"]);
    }

    public function userExists(string $username): bool
    {
        $composeFile = $this->system->composeFilePath();
        $process = $this->system->runProcess([
            "sudo",
            "docker",
            "compose",
            "-f",
            $composeFile,
            "exec",
            "-T",
            "ftp",
            "pure-pw",
            "show",
            $username,
        ]);

        if ($process->getExitCode() == 0) {
            return true;
        }

        if ($process->getExitCode() == 16) {
            return false;
        }

        throw new \Exception($process->getErrorOutput());
    }

    /**
     * @throws DockerErrorException
     */
    public function useradd(
        string $ftpUser,
        string $password,
        int $uid,
        int $gid,
        string $containerPath,
        ?int $quota = null,
    ): void {
        $pureftpdCmd = [
            "pure-pw",
            "useradd",
            escapeshellarg($ftpUser),
            "-m",
            "-u",
            escapeshellarg((string)$uid),
            "-g",
            escapeshellarg((string)$gid),
            "-d",
            escapeshellarg($containerPath),
        ];

        if ($quota) {
            $pureftpdCmd[] = "-N";
            $pureftpdCmd[] = escapeshellarg((string)$quota);
        }

        $this->execPasswordPipe($password, implode(" ", $pureftpdCmd));
        $this->exec(["pure-pw", "mkdb"]);
    }

    /**
     * @throws DockerErrorException
     */
    public function passwd(string $ftpUser, string $password): void
    {
        $pureftpdCmd = [
            "pure-pw",
            "passwd",
            escapeshellarg($ftpUser),
            "-m",
        ];
        $this->execPasswordPipe($password, implode(" ", $pureftpdCmd));
    }

    /**
     * @throws DockerErrorException
     */
    public function usermodQuota(string $ftpUser, ?int $quota): void
    {
        /**
         * need to pass empty string as parameter to set unlimited quota
         */
        $quotaValue = is_null($quota) ? '' : (string)$quota;
        $this->exec([
            "pure-pw",
            "usermod",
            $ftpUser,
            "-N",
            $quotaValue,
        ]);
    }

    /**
     * @throws DockerErrorException
     */
    public function deleteUser(string $username): void
    {
        $this->exec([
            "pure-pw",
            "userdel",
            $username,
            "-m",
        ]);
    }

    /**
     * @param list<string> $command
     * @throws DockerErrorException
     */
    public function exec(array $command): string
    {
        $composeFile = $this->system->composeFilePath();
        return $this->system->exec([
            "sudo",
            "docker",
            "compose",
            "-f",
            $composeFile,
            "exec",
            "-T",
            "ftp",
            ...$command,
        ]);
    }

    /**
     * @throws DockerErrorException
     */
    private function execPasswordPipe(string $password, string $pureftpdCmd): void
    {
        $composeFile = $this->system->composeFilePath();
        $this->system->exec([
            'sudo',
            'docker',
            'compose',
            '-f',
            $composeFile,
            'exec',
            '-T',
            'ftp',
            '/bin/sh',
            '-c',
            '(echo ' . escapeshellarg($password) . '; echo ' . escapeshellarg($password) . ') | ' . $pureftpdCmd,
        ]);
    }
}

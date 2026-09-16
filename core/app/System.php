<?php

namespace App;

use App\Exceptions\DockerErrorException;
use App\Lib\Deploy\DeployLog\StepWatchdog;
use App\Models\User as ModelsUser;
use App\System\Filesystem;
use App\System\Network;
use App\System\Project as SystemProject;
use App\System\Projects;
use App\System\Services\Csf;
use App\System\Services\Exim;
use App\System\Services\Modsec;
use App\System\Services\Mysql;
use App\System\Services\Php;
use App\System\Services\PureFtpd;
use App\System\Services\Sftp;
use App\System\Services\Webserver;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Prompts\Output\ConsoleOutput;
use Symfony\Component\Process\Process;

class System
{
    private const string HOMES_DIR_PATH = '/home';
    private const string ENGINE_DIR_PATH = '/opt/panelalpha/shared-hosting';

    public function homesDirPath(): string
    {
        return self::HOMES_DIR_PATH;
    }

    public function engineDirPath(): string
    {
        return self::ENGINE_DIR_PATH;
    }

    public function projectsDirPath(): string
    {
        return $this->engineDirPath() . '/users';
    }

    public function templatesDirPath(): string
    {
        return $this->engineDirPath() . '/templates';
    }

    public function projectFilesTemplateDirPath(?string $template = null): string
    {
        $template = $template ?? 'default';
        return $this->templatesDirPath() . '/user/' . $template . '/project';
    }

    public function projectDomainTemplateDirPath(?string $template = null): string
    {
        $template = $template ?? 'default';
        $newPath = $this->templatesDirPath() . '/user/' . $template . '/domain';

        if (!is_dir($newPath)) {
            return $this->templatesDirPath() . '/user-home';
        }

        return $newPath;
    }

    public function projectHomeTemplateDirPath(?string $template = null): string
    {
        $template = $template ?? 'default';
        return $this->templatesDirPath() . '/user/' . $template . '/home';
    }

    public function composeFilePath(): string
    {
        return $this->engineDirPath() . '/docker-compose.yml';
    }

    public function projectDirPath(string $username): string
    {
        return $this->projectsDirPath() . '/' . $username;
    }

    public function projectHomeDirPath(string $username): string
    {
        return $this->homesDirPath() . '/' . $username;
    }

    public function filesystem(): Filesystem
    {
        return new Filesystem($this);
    }

    public function directoryExists(string $path): bool
    {
        return $this->filesystem()->directoryExists($path);
    }

    public function fileExists(string $path): bool
    {
        return $this->filesystem()->fileExists($path);
    }

    public function fileGetContents(string $path): string
    {
        return $this->filesystem()->fileGetContents($path);
    }

    public function filePutContents(
        string $path,
        string $contents,
        ?string $chown = null,
        ?string $chmod = null,
    ): void {
        $this->filesystem()->filePutContents($path, $contents, $chown, $chmod);
    }

    public function makeDirWithParents(string $target, ?string $chown = null): void
    {
        $this->filesystem()->makeDirWithParents($target, $chown);
    }

    public function network(): Network
    {
        return new Network($this);
    }

    public function php(): Php
    {
        return new Php($this);
    }

    public function webserver(): Webserver
    {
        return new Webserver($this);
    }

    public function csf(): Csf
    {
        return new Csf($this);
    }

    public function modsec(): Modsec
    {
        return new Modsec($this);
    }

    public function mysql(): Mysql
    {
        return new Mysql($this);
    }

    public function ftp(): PureFtpd
    {
        return new PureFtpd($this);
    }

    public function sftp(): Sftp
    {
        return new Sftp($this);
    }

    public function exim(): Exim
    {
        return new Exim($this);
    }

    /**
     * @return ?array{
     *   started_at: ?int,
     *   finished_at: ?int,
     *   pid: ?int,
     *   exit_code: ?int,
     *   tail_stdout: ?string,
     *   tail_stderr: ?string,
     *   from_version: ?string,
     *   to_version: ?string,
     *   logs_path: string
     * }
     */
    public function getLatestChangeWebserverInfo(): ?array
    {
        return $this->webserver()->getLatestChangeWebserverInfo();
    }

    public function runChangeWebserverScript(string $newWebserver, ?string $serialNumber = null): void
    {
        $this->webserver()->runChangeWebserverScript($newWebserver, $serialNumber);
    }

    public function isChangeWebserverScriptRunning(): bool
    {
        return $this->webserver()->isChangeWebserverScriptRunning();
    }

    /**
     * Render every domain's config and apply it, reporting whether the reload
     * reached the webserver.
     *
     * @return bool false when the proxy was down and the reload is pending in the background
     */
    public function rebuildDomains(): bool
    {
        $this->webserver()->rebuildConfig();

        return $this->reloadWebserver();
    }

    /**
     * Reload the webserver, deferring when its container is not running. The config
     * on disk is already rendered, so a proxy that comes back loads it anyway.
     *
     * @return bool false when the reload was scheduled in the background instead
     */
    public function reloadWebserver(): bool
    {
        try {
            $this->webserver()->reload();
            return true;
        } catch (DockerErrorException $e) {
            if (!$e->isContainerUnavailable()) {
                throw $e;
            }
            Log::warning('Webserver container is not running; reload scheduled in the background', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $this->webserver()->scheduleWebserverReloadInBackground();
        } catch (\Throwable $scheduleError) {
            Log::warning('Could not schedule a background webserver reload: ' . $scheduleError->getMessage());
        }

        return false;
    }

    public function project(ModelsUser $model): SystemProject
    {
        return new SystemProject($this, $model);
    }

    public function projects(): Projects
    {
        return new Projects($this);
    }

    public function isUsernameAvailable(string $username): bool
    {
        $reserved = [
            'root',
            'daemon',
            'bin',
            'sys',
            'sync',
            'games',
            'man',
            'lp',
            'mail',
            'news',
            'uucp',
            'proxy',
            'www-data',
            'backup',
            'list',
            'irc',
            'nobody',
            'systemd-network',
            'systemd-resolve',
        ];

        return (bool) preg_match('/^[a-z][a-z0-9_-]{0,31}$/', $username)
            && !in_array($username, $reserved, true)
            && !$this->isUidExists($username)
            && !is_dir($this->projectHomeDirPath($username))
            && !is_dir($this->projectDirPath($username));
    }

    public function isUidExists(string $username): bool
    {
        $result = $this->runProcessOnHost([
            'id',
            '-u',
            $username,
        ]);

        return $result->getExitCode() === 0;
    }

    public function isComposeServiceRunning(string $service): bool
    {
        $command = "sudo docker compose -f {$this->composeFilePath()} ps --services --filter status=running";
        $result = $this->exec($command);
        $result = trim($result);
        $services = explode("\n", $result);
        if (in_array($service, $services)) {
            return true;
        }
        return false;
    }

    // --- HOST PROCESSES ---

    private static function debugProcesses(): bool
    {
        if (!App::runningInConsole()) {
            return false;
        }
        if (empty($_SERVER['argv'])) {
            return false;
        }
        foreach ($_SERVER['argv'] as $arg) {
            if (in_array($arg, ['-v', '-vv', '-vvv'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string|list<string> $cmd
     * @throws DockerErrorException
     * @throws Exception
     */
    public function exec(string|array $cmd, array $env = [], int $timeout = 600): string
    {
        $process = $this->runProcess($cmd, $env, $timeout);
        if (!$process->isSuccessful()) {
            $message = $process->getErrorOutput() ?: $process->getOutput();
            if (
                Str::contains($message, 'Error response from daemon')
                || DockerErrorException::meansContainerUnavailable($message)
            ) {
                throw new DockerErrorException($message);
            }
            throw new Exception($message);
        }
        return $process->getOutput();
    }

    /**
     * @param string|list<string> $cmd
     * @throws DockerErrorException
     */
    public function execOnHost(string|array $cmd, array $env = []): string
    {
        $args = [
            'sudo',
            'nsenter',
            '--target',
            '1',
            '--all',
        ];
        if (is_string($cmd)) {
            $cmd = implode(" ", $args) . " " . $cmd;
            return $this->exec($cmd, $env);
        }
        return $this->exec([...$args, ...$cmd], $env);
    }

    /**
     * @param string|list<string> $cmd
     */
    public function runProcess(string|array $cmd, array $env = [], int $timeout = 600): Process
    {
        $cmd = $this->normalizeCommand($cmd);
        $process = $this->makeProcess($cmd);
        $process->setTimeout($timeout);

        if (!self::debugProcesses()) {
            $process->run(null, $env);
            return $process;
        }

        $output = new ConsoleOutput();
        $output->writeln("<comment>Running process: " . (is_array($cmd) ? implode(' ', $cmd) : $cmd) . "</comment>");
        $process->run(function (string $type, string $data) use ($output) {
            if ($type === Process::OUT) {
                $output->writeln("<info>{$data}</info>");
            } else {
                $output->writeln("<comment>{$data}</comment>");
            }
        }, $env);
        $output->writeln("<comment>Process exited with code: " . (string)$process->getExitCode() . "</comment>");

        return $process;
    }

    /**
     * @param string|list<string> $cmd
     */
    public function runProcessWithCallbacks(
        string|array $cmd,
        array $env = [],
        int $timeout = 600,
        ?callable $onStart = null,
        ?callable $onOutput = null,
        ?StepWatchdog $watchdog = null
    ): Process {
        $cmd = $this->normalizeCommand($cmd);
        $process = $this->makeProcess($cmd);
        $process->setTimeout($timeout);
        if ($watchdog !== null) {
            $process->start($watchdog->watch($onOutput), $env);
            if ($onStart !== null) {
                $onStart($process);
            }
            $watchdog->wait($process);

            return $process;
        }

        $process->start(null, $env);
        if ($onStart !== null) {
            $onStart($process);
        }
        $process->wait($onOutput);

        return $process;
    }

    /**
     * @param string|list<string> $cmd
     */
    public function runProcessOnHost(string|array $cmd, array $env = [], int $timeout = 600): Process
    {
        $args = [
            'sudo',
            'nsenter',
            '--target',
            '1',
            '--all',
        ];
        if (is_string($cmd)) {
            $cmd = implode(" ", $args) . " " . $cmd;
            return $this->runProcess($cmd, $env, $timeout);
        }

        return $this->runProcess([...$args, ...$cmd], $env, $timeout);
    }

    /**
     * @param string|list<string> $cmd
     */
    private function makeProcess(string|array $cmd): Process
    {
        if (is_array($cmd)) {
            return new Process($cmd);
        }
        return Process::fromShellCommandline($cmd);
    }

    /**
     * @param string|array<string> $cmd
     * @return string|list<string>
     */
    private function normalizeCommand(string|array $cmd): string|array
    {
        if (is_array($cmd)) {
            return array_values($cmd);
        }

        return $cmd;
    }

    public function getEnv(): array
    {
        $lines = file($this->engineDirPath() . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $env = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (strpos($line, '=') !== false) {
                /** @psalm-suppress PossiblyUndefinedArrayOffset */
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, " \t\n\r\0\x0B\"'");
                $env[$key] = $value;
            }
        }

        return $env;
    }

    // --- UPDATE ENGINE ---

    /**
     * @return ?array{
     *   started_at: ?int,
     *   finished_at: ?int,
     *   pid: ?int,
     *   exit_code: ?int,
     *   tail_stdout: ?string,
     *   tail_stderr: ?string,
     *   from_version: ?string,
     *   to_version: ?string,
     *   logs_path: string
     * }
     */
    public function getLatestUpdateInfo(): ?array
    {
        $latestLink = "/opt/panelalpha/log/engine-updates/latest";
        $fs = $this->filesystem();
        if (!$fs->directoryExists($latestLink)) {
            return null;
        }

        $pidFile = "$latestLink/pid";
        $exitCodeFile = "$latestLink/exit_code";
        $stdoutFile = "$latestLink/stdout";
        $stderrFile = "$latestLink/stderr";
        $fromVersionFile = "$latestLink/from_version";
        $toVersionFile = "$latestLink/to_version";

        $pid = $fs->cat($pidFile);
        $exitCode = $fs->cat($exitCodeFile);

        $tailStdout = $fs->tail($stdoutFile, 2);
        if ($tailStdout) {
            $tailStdout = preg_replace('/\x1B\[[0-9;]*[mK]/', '', $tailStdout);
        }
        $tailStderr = $fs->tail($stderrFile, 2);
        if ($tailStderr) {
            $tailStderr = preg_replace('/\x1B\[[0-9;]*[mK]/', '', $tailStderr);
        }

        return [
            'started_at' => $fs->mtime($latestLink),
            'finished_at' => $fs->mtime($exitCodeFile),
            'pid' => (is_numeric($pid) ? (int)$pid : null),
            'exit_code' => (is_numeric($exitCode) ? (int)$exitCode : null),
            'tail_stdout' => $tailStdout,
            'tail_stderr' => $tailStderr,
            'from_version' => $fs->cat($fromVersionFile),
            'to_version' => $fs->cat($toVersionFile),
            'logs_path' => $latestLink,
        ];
    }

    public function runUpdateScript(?string $licenseKey = null): void
    {
        $updaterPath = $this->engineDirPath() . '/updater.sh';

        $updaterScript = 'bash ' . escapeshellarg($updaterPath) . ' -f --background';
        if (!empty($licenseKey)) {
            $updaterScript .= ' ' . escapeshellarg($licenseKey);
        }
        $atScript = 'echo ' . escapeshellarg($updaterScript) . ' | at now';
        $args = [
            "sudo",
            "nsenter",
            "--target",
            "1",
            "--all",
            "bash",
            "-c",
            $atScript,
        ];

        $process = $this->runProcess($args);

        if ($process->getExitCode() !== 0) {
            $message = "Failed to run update script: ";
            $message .= ($process->getErrorOutput() ?: $process->getOutput());
            $message .= " (exit code " . (string)$process->getExitCode() . ")";
            throw new \Exception($message);
        }

        $logsDir = "/opt/panelalpha/log/engine-updates";
        $tmpDirScript = "mkdir -p {$logsDir}/tmp && echo '2' > {$logsDir}/tmp/pid && ln -sfn {$logsDir}/tmp {$logsDir}/latest";
        $this->runProcess([
            "sudo",
            "nsenter",
            "--target",
            "1",
            "--all",
            "bash",
            "-c",
            $tmpDirScript,
        ]);
    }

    public function isUpdateScriptRunning(): bool
    {
        $latestLink = "/opt/panelalpha/log/engine-updates/latest";
        if (!$this->filesystem()->directoryExists($latestLink)) {
            return false;
        }

        $pidFile = "$latestLink/pid";
        if (!file_exists($pidFile)) {
            return false;
        }

        $pid = trim(file_get_contents($pidFile));

        if (!ctype_digit($pid)) {
            return false;
        }

        $process = $this->runProcess([
            'sudo',
            'nsenter',
            '--target',
            '1',
            '--all',
            'kill',
            '-0',
            $pid,
        ]);

        return $process->getExitCode() === 0;
    }
}

<?php

namespace App\System\Project;

use App\System\Project as UserProject;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;

class FileManager
{
    public function __construct(
        private readonly UserProject $project,
    ) {
    }

    public function homeDirPath(): string
    {
        return $this->project->homeDirPath();
    }

    public function storage(): Filesystem
    {
        return Storage::build([
            'driver' => 'local',
            'root' => $this->homeDirPath(),
            'throw' => true,
        ]);
    }

    /**
     * Resolve a caller-supplied path into an absolute path under this Project's home.
     *
     * @throws ValidationException
     */
    public function resolvePath(string $path): string
    {
        if (Str::startsWith($path, '/var/www')) {
            $legacyHome = $this->project->model()->getHomeDir() ?? $this->homeDirPath();
            $path = Str::replaceFirst('/var/www', $legacyHome, $path);
        }
        $absolute = Str::startsWith($path, '/');
        $dir = Str::endsWith($path, '/');
        $clean = self::sanitizePath($path);
        if ($clean === false) {
            throw ValidationException::withMessages([
                'Invalid path',
            ]);
        }
        $home = $this->homeDirPath();
        if ($absolute && Str::startsWith('/' . $clean, $home)) {
            $clean = Str::after('/' . $clean, $home);
        }

        return $home . '/' . ltrim($clean, '/') . ($dir ? '/' : '');
    }

    public function putContents(string $path, string $contents): void
    {
        $path = $this->resolvePath($path);
        $model = $this->project->model();
        $chown = $model->getChownString();
        if ($chown === null) {
            Log::warning("Cannot put file contents as user {$this->project->username()}, UID/GID is missing", [
                'path' => $path,
            ]);
        }
        $this->project->system()->filesystem()->filePutContents($path, $contents, $chown, '644');
    }

    public function mkdir(string $path, bool $parents = false): void
    {
        $path = $this->resolvePath($path);
        $system = $this->project->system();
        $filesystem = $system->filesystem();
        $chown = $this->project->model()->getChownString() ?? '33:33';
        if ($parents) {
            $filesystem->makeDirWithParents($path, $chown);

            return;
        }
        $system->exec(['sudo', 'mkdir', $path]);
        $system->exec(['sudo', 'chown', $chown, $path]);
    }

    public function moveUploadedFile(string $path, UploadedFile $file): void
    {
        $path = $this->resolvePath($path);
        $targetDir = rtrim($path, '/');
        $targetFilename = $file->getClientOriginalName();
        $target = $targetDir . '/' . $targetFilename;

        $system = $this->project->system();
        $uidgid = $this->project->model()->getChownString() ?? '33:33';

        if (!is_dir($targetDir)) {
            $system->filesystem()->makeDirWithParents($targetDir, $uidgid);
        }

        $system->runProcess([
            'sudo',
            'sh',
            '-c',
            sprintf(
                'mv %s %s && chmod 644 %s && chown %s %s',
                escapeshellarg($file->path()),
                escapeshellarg($target),
                escapeshellarg($target),
                escapeshellarg($uidgid),
                escapeshellarg($target),
            ),
        ]);
    }

    public function exists(string $path): bool
    {
        $path = $this->resolvePath($path);
        $command = [
            'test',
            '-e',
            $path,
        ];

        $process = $this->runProcess($command);

        return $process->getExitCode() === 0;
    }

    /**
     * @return array<string, string>
     */
    public function stat(string $path): array
    {
        $path = $this->resolvePath($path);
        $command = [
            'stat',
            '--printf=%n %s %u %g %X %Y %Z %W',
            $path,
        ];

        $process = $this->runProcess($command);
        if (!$process->isSuccessful()) {
            throw new \Exception($process->getErrorOutput());
        }

        $result = [];
        [
            $result['file_name'],
            $result['size'],
            $result['user_id'],
            $result['group_id'],
            $result['access_time'],
            $result['modify_time'],
            $result['status_change_time'],
            $result['create_time'],
        ] = explode(' ', $process->getOutput());

        return $result;
    }

    public function mv(string $sourcePath, string $destPath): void
    {
        $sourcePath = $this->resolvePath($sourcePath);
        $destPath = $this->resolvePath($destPath);
        $command = [
            'mv',
            $sourcePath,
            $destPath,
        ];

        $process = $this->runProcess($command);

        if (!$process->isSuccessful()) {
            $message = ($process->getErrorOutput() ?: $process->getOutput()) . " (exit code {$process->getExitCode()})";
            throw new \Exception($message);
        }
    }

    public function cp(string $sourcePath, string $destPath): void
    {
        $sourcePath = $this->resolvePath($sourcePath);
        $destPath = $this->resolvePath($destPath);
        $command = [
            'cp',
            '-a',
            $sourcePath,
            $destPath,
        ];

        $process = $this->runProcess($command);

        if (!$process->isSuccessful()) {
            $message = ($process->getErrorOutput() ?: $process->getOutput()) . " (exit code {$process->getExitCode()})";
            throw new \Exception($message);
        }
    }

    public function zip(string $zipPath, string $path, bool $skipParents = false): void
    {
        $zipPath = $this->resolvePath($zipPath);
        $path = $this->resolvePath($path);

        $workdir = null;
        if ($skipParents) {
            $workdir = dirname($path);
            $path = basename($path);
        }

        $command = [
            'zip',
            '-r',
            $zipPath,
            $path,
        ];

        $process = $this->runProcess($command, $workdir);
        if (!$process->isSuccessful()) {
            $message = ($process->getErrorOutput() ?: $process->getOutput()) . " (exit code {$process->getExitCode()})";
            throw new \Exception($message);
        }
    }

    public function unzip(string $zipPath, string $path): void
    {
        $zipPath = $this->resolvePath($zipPath);
        $path = $this->resolvePath($path);
        $filename = basename($zipPath);

        if (Str::endsWith($filename, '.zip')) {
            $command = [
                'unzip',
                '-UU',
                '-o',
                $zipPath,
                '-d',
                $path,
            ];
            $process = $this->runProcess($command);
        } elseif (Str::endsWith($filename, '.tar.gz')) {
            $command = [
                'tar',
                '-zxvf',
                $zipPath,
                '-C',
                $path,
            ];
            $process = $this->runProcess($command);
        } else {
            $path = rtrim($path, '/');
            if ($zipPath != "{$path}/{$filename}") {
                $this->cp($zipPath, $path);
            }
            $command = [
                'gunzip',
                '-f',
                $filename,
            ];
            $process = $this->runProcess($command, $path);
        }
        if (!$process->isSuccessful()) {
            $message = ($process->getErrorOutput() ?: $process->getOutput()) . " (exit code {$process->getExitCode()})";
            throw new \Exception($message);
        }
    }

    public function remove(string $path, bool $recursive = false): void
    {
        $path = $this->resolvePath($path);
        $command = [
            'rm',
            '-f',
        ];
        if ($recursive) {
            $command[] = '-r';
        }
        $command[] = $path;

        $process = $this->runProcess($command);
        if (!$process->isSuccessful()) {
            $message = ($process->getErrorOutput() ?: $process->getOutput()) . " (exit code {$process->getExitCode()})";
            throw new \Exception($message);
        }
    }

    public function diskUsage(string $directory = '/'): int
    {
        $path = $this->resolvePath($directory);
        $process = $this->runProcess([
            'du',
            '-shm',
            $path,
        ]);
        $result = $process->getOutput();
        $mb = Str::before($result, "\t");

        return (int) $mb;
    }

    /**
     * @param array<string> $command
     */
    private function runProcess(array $command, ?string $workdir = null): Process
    {
        $composeFile = $this->project->composeFilePath();
        $uidgid = $this->project->model()->getChownString() ?? '33:33';
        $execCommand = [
            'sudo',
            'docker',
            'compose',
            '-f',
            $composeFile,
            'exec',
            '-u',
            $uidgid,
            '-T',
        ];
        if (!empty($workdir)) {
            $execCommand[] = '-w';
            $execCommand[] = $workdir;
            if (!is_dir($workdir)) {
                throw new \Exception('Invalid path');
            }
        }
        $execCommand[] = $this->project->runtime()->defaultServiceName();

        $command = [...$execCommand, ...$command];

        return $this->project->system()->runProcess($command);
    }

    /**
     * @return string|false
     */
    private static function sanitizePath(string $path): string|false
    {
        if (Str::contains($path, ["\0", "\n", "\t"])) {
            return false;
        }
        $validParts = [];
        $parts = explode('/', $path);
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if (count($validParts) < 1) {
                    return false;
                }
                array_pop($validParts);
                continue;
            }
            $validParts[] = $part;
        }

        return implode('/', $validParts);
    }
}

<?php

namespace App\System\Project\Dind;

use App\Lib\Deploy\Compose\ComposeFileInspector;
use App\Lib\Deploy\Platform\Strategies;
use App\System\Project\Dind as DindProject;

/**
 * Paths for the inner ~/project tree inside a DinD account container.
 */
final class Paths
{
    /** Compose's own name for a layer applied over the base file. */
    public const COMPOSE_OVERRIDE_FILENAME = 'docker-compose.override.yml';

    /** @var list<string> */
    private const COMPOSE_FILE_CANDIDATES = ComposeFileInspector::COMPOSE_FILE_CANDIDATES;

    public function __construct(
        private DindProject $project,
    ) {
    }

    public function appDir(): string
    {
        return $this->project->homeDirPath() . '/project';
    }

    public function composeFile(): string
    {
        return $this->appDir() . '/docker-compose.yml';
    }

    public function existingComposeFile(): ?string
    {
        $fs = $this->project->system()->filesystem();
        foreach (self::COMPOSE_FILE_CANDIDATES as $name) {
            $path = $this->appDir() . '/' . $name;
            if ($fs->fileExists($path)) {
                return $path;
            }
        }

        return null;
    }

    public function composeFileToRun(): string
    {
        $strategy = $this->project->userModel()->getDeployStrategy();
        if ($strategy === Strategies::COMPOSE || $strategy === Strategies::PAEMD) {
            return $this->existingComposeFile() ?? $this->composeFile();
        }

        return $this->composeFile();
    }

    public function composeOverrideFile(): string
    {
        return $this->appDir() . '/' . self::COMPOSE_OVERRIDE_FILENAME;
    }

    /**
     * @return list<string>
     */
    public function composeFiles(): array
    {
        $files = [$this->composeFileToRun()];

        $override = $this->composeOverrideFile();
        if ($override !== $files[0] && $this->project->system()->filesystem()->fileExists($override)) {
            $files[] = $override;
        }

        return $files;
    }

    /**
     * @param list<string> $rest
     * @return list<string>
     */
    public function composeCommand(array $rest): array
    {
        $command = [
            'docker',
            'compose',
            '--project-directory',
            $this->appDir(),
        ];
        foreach ($this->composeFiles() as $file) {
            $command[] = '-f';
            $command[] = $file;
        }

        return array_merge($command, $rest);
    }

    /**
     * @return list<string>
     */
    public function composeCommandForDirectory(string $appDir): array
    {
        $appDir = rtrim($appDir, '/');
        $files = $this->composeFilesIn($appDir);
        $command = [
            'docker',
            'compose',
            '--project-directory',
            $appDir,
        ];
        foreach ($files as $file) {
            $command[] = '-f';
            $command[] = $file;
        }

        return $command;
    }

    /**
     * @return list<string>
     */
    private function composeFilesIn(string $appDir): array
    {
        $primary = $this->existingComposeFileIn($appDir) ?? ($appDir . '/docker-compose.yml');
        $files = [$primary];
        $override = $appDir . '/' . self::COMPOSE_OVERRIDE_FILENAME;
        if ($override !== $primary && $this->project->system()->filesystem()->fileExists($override)) {
            $files[] = $override;
        }

        return $files;
    }

    private function existingComposeFileIn(string $appDir): ?string
    {
        $fs = $this->project->system()->filesystem();
        foreach (self::COMPOSE_FILE_CANDIDATES as $candidate) {
            $path = $appDir . '/' . $candidate;
            if ($fs->fileExists($path)) {
                return $path;
            }
        }

        return null;
    }
}

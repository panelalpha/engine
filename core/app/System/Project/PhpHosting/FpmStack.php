<?php

namespace App\System\Project\PhpHosting;

use App\Models\User as ModelsUser;
use App\System;
use App\System\Project\PhpHosting;

final class FpmStack implements PhpStack
{
    use FpmPoolSettings;

    public function __construct(
        private System $system,
        private ModelsUser $model,
    ) {
    }

    public function dockerfileTemplateName(): string
    {
        return 'Dockerfile-fpm';
    }

    public function composeTemplateName(): string
    {
        return 'docker-compose.yml-fpm';
    }

    public function applySettings(PhpHosting $project): void
    {
        $this->applyPhpFpmSettings($project);
        (new EnvironmentSetup())->applyRedisSettings($project);
    }

    public function entrypointInitScripts(PhpHosting $project): array
    {
        return [];
    }

    public function entrypointBackgroundScripts(PhpHosting $project): array
    {
        return $this->getEntrypointPhpScripts();
    }

    public function waitForAllRunning(PhpHosting $project, int $tries = 12, int $intervalSeconds = 5): void
    {
    }

    public function restartPhpHandler(PhpHosting $project, string $phpVersion): void
    {
        $this->system->runProcess([
            'sudo',
            'docker',
            'compose',
            '-f',
            $project->composeFilePath(),
            'exec',
            '-T',
            'php',
            'bash',
            '/entrypoint-runner.sh',
            'restart',
            "php-fpm{$phpVersion}",
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function getEntrypointPhpScripts(): array
    {
        $scriptFiles = [];
        $usedPhpVersions = [];
        foreach ($this->model->getDomains() as $domain) {
            $ver = $domain->getPhpVersion();
            if ($ver === null) {
                continue;
            }
            if (!in_array($ver, $usedPhpVersions, true)) {
                $usedPhpVersions[] = $ver;
            }
        }
        foreach ($usedPhpVersions as $phpVersion) {
            $scriptFiles["php-fpm{$phpVersion}.sh"] = "exec php-fpm{$phpVersion} -F";
        }

        return $scriptFiles;
    }
}

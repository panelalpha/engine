<?php

namespace App\System\Project\Dind;

use App\Exceptions\DeployCancelledException;
use App\Lib\Deploy\Compose\DeployCompose;
use App\Lib\Deploy\Dind\DindBuildStorage;
use App\Lib\Deploy\Platform\PlatformManifest;
use App\Lib\Deploy\Platform\Runtime\HostRunProject;
use App\Lib\Deploy\Platform\Runtime\Images;
use App\Lib\Deploy\Platform\Runtime\StandaloneNodeServe;
use App\Lib\Deploy\Telemetry\Telemetry;
use App\System\Project\Dind as DindProject;
use Symfony\Component\Process\Process;

/**
 * Inner `docker compose up` for clone/push and other App\System callers.
 */
final class AppLauncher
{
    private const COMPOSE_TIMEOUT_SECONDS = 3600;

    private const FAILURE_LOG_LINES = 60;

    private const FAILURE_LOG_TIMEOUT_SECONDS = 30;

    public function __construct(
        private DindProject $project,
    ) {
    }

    /**
     * @return array{stdout: string, stderr: string, exit_code: ?int}
     * @throws \Exception
     */
    public function start(): array
    {
        $model = $this->project->userModel();
        $strategy = $model->getDeployStrategy();
        $runtime = $model->getDeployRuntime();
        $skipBuild = DeployCompose::skipBuild($strategy, $runtime);

        $this->preloadImages($strategy, $runtime, $skipBuild, $model->getDeployImage());

        $command = $this->project->userAppComposeCommand(['up', '-d', '--remove-orphans']);
        if (DeployCompose::forceRecreate($strategy, $runtime)) {
            $command[] = '--force-recreate';
        }
        if ($skipBuild) {
            $command[] = '--no-build';
        } else {
            $command[] = '--build';
        }

        $process = $this->run($command);
        $process = $this->retryOnceIfDiskFull($process, $command);

        if ($process->getExitCode() !== 0) {
            $this->recordContainerOutput();
        }

        if ($process->getExitCode() === 0) {
            $this->project->alignAppPort();
        }

        return [
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput(),
            'exit_code' => $process->getExitCode(),
        ];
    }

    /**
     * @param string|null $strategy
     * @param string|null $runtime
     * @param bool $skipBuild
     * @param string|null $resolvedImage
     * @throws \Exception
     */
    private function preloadImages(
        ?string $strategy,
        ?string $runtime,
        bool $skipBuild,
        ?string $resolvedImage = null
    ): void {
        $innerDocker = $this->project->innerDocker();

        if ($resolvedImage !== null && $resolvedImage !== '') {
            $innerDocker->ensureImage($resolvedImage);
        }

        if ($runtime === PlatformManifest::RUNTIME_NGINX) {
            $this->project->hostCompile()->run();
            $innerDocker->ensureImage(Images::NGINX_IMAGE);
        } elseif (StandaloneNodeServe::isStandaloneStrategy($strategy) || HostRunProject::isStrategy($strategy)) {
            $this->project->hostCompile()->run();
            if (HostRunProject::isNode($strategy) || StandaloneNodeServe::isStandaloneStrategy($strategy)) {
                $innerDocker->ensureImage(Images::nodeImage($this->project->userAppDirPath()));
            }
        } elseif (!$skipBuild) {
            $innerDocker->preloadFrameworkBaseImages($strategy, $runtime);
        }
        $innerDocker->preloadComposeImages($this->project->userAppComposeFileToRun());
        if (!DeployCompose::skipReclaimBeforeBuild($strategy, $runtime)) {
            $innerDocker->reclaimStorageIfNeeded();
        }
    }

    /**
     * @param list<string> $command
     */
    private function retryOnceIfDiskFull(Process $process, array $command): Process
    {
        $innerDocker = $this->project->innerDocker();
        $message = $process->getErrorOutput() . "\n" . $process->getOutput();
        if (
            $process->getExitCode() === 0
            || !DindBuildStorage::isDiskFullError($message)
            || !$innerDocker->canAggressivelyReclaim()
        ) {
            return $process;
        }

        $this->project->shell()->logger()?->info(
            'Build hit ENOSPC; reclaiming inner Docker cache and retrying once'
        );
        Telemetry::signal(
            $this->project->userModel()->username,
            'enospc-retry',
            'Build hit ENOSPC; reclaimed the inner Docker cache and retried once'
        );
        $innerDocker->reclaimStorage(true);

        return $this->run($command);
    }

    private function recordContainerOutput(): void
    {
        $shell = $this->project->shell();
        $logger = $shell->logger();
        if ($logger === null) {
            return;
        }

        try {
            $output = $shell->execAsUserQuiet(
                $this->project->userAppComposeCommand([
                    'logs',
                    '--tail=' . self::FAILURE_LOG_LINES,
                    '--no-color',
                    '--timestamps',
                ]),
                [],
                self::FAILURE_LOG_TIMEOUT_SECONDS
            );
        } catch (\Throwable) {
            return;
        }

        $output = trim($output);
        if ($output === '') {
            return;
        }

        $logger->info('Container output at the point of failure:');
        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            if (trim($line) !== '') {
                $logger->info($line);
            }
        }
    }

    /**
     * @param list<string> $command
     * @throws DeployCancelledException
     */
    private function run(array $command): Process
    {
        $shell = $this->project->shell();
        $logger = $shell->logger();
        if ($logger === null) {
            return $shell->runProcess($command, [], self::COMPOSE_TIMEOUT_SECONDS);
        }

        $logger->throwIfCancelled();
        $logger->info('Starting application (docker compose up -d)');
        $process = $shell->streamProcess(
            $shell->wrap($command),
            [],
            self::COMPOSE_TIMEOUT_SECONDS,
            $logger
        );
        $logger->throwIfCancelled();

        return $process;
    }
}

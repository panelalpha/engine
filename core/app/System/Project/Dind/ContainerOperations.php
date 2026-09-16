<?php

namespace App\System\Project\Dind;

use App\System\Project\Dind as DindProject;

/**
 * Container/service-level operations on the user's inner compose project.
 */
final class ContainerOperations
{
    public function __construct(
        private DindProject $project,
        private ShellOperations $shell,
    ) {
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function getContainers(): array
    {
        try {
            $output = $this->shell->execAsUser($this->project->userAppComposeCommand([
                'ps',
                '--format',
                'json',
                '--all',
            ]));
            $containers = [];
            foreach (explode("\n", trim($output)) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                /** @var mixed */
                $item = json_decode($line, true);
                if (is_array($item)) {
                    $containers[] = $item;
                }
            }

            return $containers;
        } catch (\Exception) {
            return [];
        }
    }

    private const COMPOSE_TIMEOUT = 7200;

    /**
     * Inner compose stop for backup downtime (throws on failure).
     */
    public function composeStop(): void
    {
        try {
            $this->shell->execAsUser(
                $this->project->userAppComposeCommand(['stop']),
                [],
                self::COMPOSE_TIMEOUT,
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException('compose stop failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Inner compose start (resume, no rebuild) after backup/restore.
     */
    public function composeStart(): void
    {
        $this->shell->execAsUser(
            $this->project->userAppComposeCommand(['start']),
            [],
            self::COMPOSE_TIMEOUT,
        );
    }

    /**
     * @return array{stdout: string, stderr: string, exit_code: int}
     */
    public function projectAction(string $action): array
    {
        $allowed = ['up', 'start', 'stop', 'restart', 'down', 'pull'];
        if (!in_array($action, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid project action: {$action}");
        }

        try {
            if ($action === 'pull') {
                $pullOut = $this->shell->execAsUser($this->project->userAppComposeCommand(['pull']), [], 600);
                $upOut = $this->shell->execAsUser(
                    $this->project->userAppComposeCommand(['up', '-d', '--remove-orphans']),
                    [],
                    600
                );

                return ['stdout' => $pullOut . "\n" . $upOut, 'stderr' => '', 'exit_code' => 0];
            }

            $cmd = $action === 'up'
                ? $this->project->userAppComposeCommand(['up', '-d', '--remove-orphans'])
                : $this->project->userAppComposeCommand([$action]);

            return ['stdout' => $this->shell->execAsUser($cmd, [], 600), 'stderr' => '', 'exit_code' => 0];
        } catch (\Exception $e) {
            return ['stdout' => '', 'stderr' => $e->getMessage(), 'exit_code' => 1];
        }
    }

    /**
     * @return array{stdout: string, stderr: string, exit_code: int}
     */
    public function serviceAction(string $service, string $action): array
    {
        $allowed = ['start', 'stop', 'restart'];
        if (!in_array($action, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid service action: {$action}");
        }
        self::assertServiceName($service);

        try {
            $output = $this->shell->execAsUser(
                $this->project->userAppComposeCommand([$action, $service]),
                [],
                120
            );

            return ['stdout' => $output, 'stderr' => '', 'exit_code' => 0];
        } catch (\Exception $e) {
            return ['stdout' => '', 'stderr' => $e->getMessage(), 'exit_code' => 1];
        }
    }

    public function getServiceLogs(string $service, int $lines = 200): string
    {
        self::assertServiceName($service);

        try {
            return $this->shell->execAsUser(
                $this->project->userAppComposeCommand([
                    'logs',
                    "--tail={$lines}",
                    '--no-color',
                    $service,
                ]),
                [],
                60
            );
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    private static function assertServiceName(string $service): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $service)) {
            throw new \InvalidArgumentException("Invalid service name: {$service}");
        }
    }
}

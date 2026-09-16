<?php

namespace App\Lib\Deploy\Inspect\Report;

use App\Lib\Deploy\Compose\ComposeFileInspector;
use App\Lib\Deploy\Detect\DockerfileFinder;
use App\Lib\Deploy\DetectAppPort;

/**
 * Ports, and which source each one came from.
 *
 * They disagree often enough that reporting one number would be a guess: a
 * repository's Dockerfile EXPOSEs 8080, its compose publishes 3000, and the
 * platform manifest says the framework listens on 3000. The primary is the
 * one the engine would proxy; the rest are shown so a disagreement is visible
 * rather than silently resolved.
 */
final class PortsReport
{
    private readonly string $projectDir;

    /**
     * @param array<string, mixed> $decision
     */
    public function __construct(string $projectDir, private readonly array $decision)
    {
        $this->projectDir = rtrim($projectDir, '/');
    }

    /**
     * @param array<string, mixed> $decision
     * @return array{primary: ?int, source: ?string, compose: list<int>, dockerfile_expose: ?int}
     */
    public static function of(string $projectDir, array $decision): array
    {
        return (new self($projectDir, $decision))->build();
    }

    /**
     * @return array{primary: ?int, source: ?string, compose: list<int>, dockerfile_expose: ?int}
     */
    public function build(): array
    {
        $compose = $this->composePorts();
        $expose = $this->exposedPort();
        [$primary, $source] = $this->primary($compose, $expose);

        return [
            'primary' => $primary,
            'source' => $source,
            'compose' => array_values(array_map('intval', $compose['all'] ?? [])),
            'dockerfile_expose' => $expose,
        ];
    }

    /**
     * @param array<string, mixed> $compose
     * @return array{0: ?int, 1: ?string}
     */
    private function primary(array $compose, ?int $expose): array
    {
        $platform = (int) ($this->decision['port_hint'] ?? 0);
        $composePrimary = (int) ($compose['primary'] ?? 0);

        return match (true) {
            $platform > 0 => [$platform, 'platform'],
            $composePrimary > 0 => [$composePrimary, 'compose'],
            $expose !== null => [$expose, 'dockerfile'],
            default => [null, null],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function composePorts(): array
    {
        $path = $this->composePath();

        return $path === null ? ['all' => []] : DetectAppPort::detectAllPorts($path);
    }

    private function composePath(): ?string
    {
        $declared = $this->decision['compose_path'] ?? null;
        if (is_string($declared) && is_file($declared)) {
            return $declared;
        }

        return ComposeFileInspector::firstIn($this->projectDir);
    }

    /**
     * The EXPOSE of a Dockerfile the engine would actually build.
     *
     * A losing answer is still worth reporting -- that is the point of this
     * report -- but only when it is a real one. A decision naming no
     * Dockerfile used to mean the repository had none; it can now also mean
     * detection read one and refused it, and a port taken from a file the
     * engine will not build is not a port anything publishes. So the name is
     * resolved through the same finder rather than guessed back.
     */
    private function exposedPort(): ?int
    {
        $dockerfile = $this->decision['dockerfile'] ?? null;
        if (!is_string($dockerfile) || $dockerfile === '') {
            $dockerfile = DockerfileFinder::find($this->projectDir, []);
        }

        return $dockerfile === null
            ? null
            : DockerfileFinder::exposedPort($this->projectDir . '/' . $dockerfile);
    }
}

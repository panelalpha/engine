<?php

namespace Tests\Unit\Deploy\Dind;

use App\System\Project\Dind;
use App\System\Project\Dind\ShellOperations;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class ShellStepTagTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const COMPOSE = '/home/acme/docker-compose.yml';

    public function test_a_dind_exec_step_carries_the_tag_in_its_environment(): void
    {
        $cmd = ['sudo', 'docker', 'compose', '-f', self::COMPOSE, 'exec', '-T', 'dind', 'docker', 'compose', 'up'];

        $this->assertSame(
            ['sudo', 'docker', 'compose', '-f', self::COMPOSE, 'exec', '-e', 'PANELALPHA_STEP=abc', '-T', 'dind', 'docker', 'compose', 'up'],
            $this->shell()->tagStep($cmd, 'abc')
        );
    }

    public function test_a_host_build_container_carries_the_tag_as_a_label(): void
    {
        $this->assertSame(
            ['sudo', 'docker', 'run', '--label', 'panelalpha.step=abc', '--rm', 'node:22', 'sh', '-c', 'npm ci'],
            $this->shell()->tagStep(['sudo', 'docker', 'run', '--rm', 'node:22', 'sh', '-c', 'npm ci'], 'abc')
        );
    }

    public function test_any_other_command_is_left_alone(): void
    {
        $cmd = ['sudo', 'sh', '-c', 'docker load < image.tar'];

        $this->assertSame($cmd, $this->shell()->tagStep($cmd, 'abc'));
    }

    public function test_the_step_label_is_the_command_inside_the_wrappers(): void
    {
        $this->assertSame(
            'npm run build',
            ShellOperations::stepLabel(['sudo', 'docker', 'compose', '-f', self::COMPOSE, 'exec', '-T', 'dind', 'su', '-s', '/bin/bash', 'acme', '-c', 'npm run build'])
        );
        $this->assertSame(
            'docker compose up -d --build',
            ShellOperations::stepLabel(['sudo', 'docker', 'compose', '-f', self::COMPOSE, 'exec', '-T', 'dind', 'docker', 'compose', 'up', '-d', '--build'])
        );
        $this->assertSame('host build: npm ci', ShellOperations::stepLabel(['sudo', 'docker', 'run', '--rm', 'node:22', 'sh', '-c', 'npm ci']));
    }

    private function shell(): ShellOperations
    {
        $dind = Mockery::mock(Dind::class);
        $dind->shouldReceive('composeFilePath')->andReturn(self::COMPOSE);

        return new ShellOperations($dind);
    }
}

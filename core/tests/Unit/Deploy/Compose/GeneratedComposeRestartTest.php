<?php

namespace Tests\Unit\Deploy\Compose;

use App\Lib\Deploy\Compose\DeployCompose;
use App\Lib\Deploy\Compose\GeneratedCompose;
use PHPUnit\Framework\TestCase;

/**
 * Every compose file the engine generates must survive a restart.
 *
 * Docker's own default is `restart: no`, so a generator that forgets the key
 * produces an app that comes up on the deploy that created it and never
 * again. It is invisible until the host reboots — which is exactly how it was
 * found: static sites stayed down while every other platform's came back.
 */
class GeneratedComposeRestartTest extends TestCase
{
    public function test_a_generator_that_says_nothing_still_gets_a_policy(): void
    {
        $yaml = GeneratedCompose::render(['image' => 'nginx:alpine']);

        $this->assertStringContainsString('restart: unless-stopped', $yaml);
    }

    public function test_a_generators_own_choice_is_respected(): void
    {
        // A one-shot service that should stop when it stops.
        $yaml = GeneratedCompose::render(['image' => 'acme/migrate', 'restart' => 'no']);

        $this->assertStringContainsString("restart: 'no'", $yaml);
        $this->assertStringNotContainsString('unless-stopped', $yaml);
    }

    public function test_every_shipped_generator_produces_a_restarting_app(): void
    {
        $generated = [
            'static' => DeployCompose::staticNginx(),
            'dockerfile' => DeployCompose::dockerfile('Dockerfile', 3000),
            'railpack' => DeployCompose::railpack('acme/app', 8080),
            'framework' => DeployCompose::framework(['runtime' => 'nginx'], 8080),
        ];

        foreach ($generated as $name => $yaml) {
            $this->assertStringContainsString('restart: unless-stopped', $yaml, $name);
        }
    }
}

<?php

namespace Tests\Unit\Cloudflare;

use PHPUnit\Framework\TestCase;

/**
 * Assert the DinD supervisor template contract Cloudflare renders.
 * Templates live under engine/templates locally; on some hosts they are
 * bind-mounted outside the core tree — try known roots then skip.
 *
 * @see \App\Integrations\Tunnels\Cloudflare::renderCloudflaredSupervisorConf()
 */
class CloudflaredSupervisorConfTest extends TestCase
{
    private function projectTemplateDir(): string
    {
        $candidates = [
            dirname(__DIR__, 4) . '/templates/user/dind/project',
            '/opt/panelalpha/shared-hosting/templates/user/dind/project',
            '/var/www/templates/user/dind/project',
        ];
        foreach ($candidates as $dir) {
            if (is_dir($dir . '/supervisord.conf.d')) {
                return $dir;
            }
        }
        $this->markTestSkipped('DinD project templates not available in this environment');
    }

    public function test_blade_template_wires_autostart_and_optional_token(): void
    {
        $path = $this->projectTemplateDir() . '/supervisord.conf.d/cloudflared.conf.blade.php';
        $this->assertFileExists($path);
        $tpl = (string) file_get_contents($path);

        $this->assertStringContainsString('[program:cloudflared]', $tpl);
        $this->assertStringContainsString('cloudflared --no-autoupdate tunnel run', $tpl);
        $this->assertStringContainsString("autostart={{ !empty(\$autostart) ? 'true' : 'false' }}", $tpl);
        $this->assertStringContainsString('@if (!empty($autostart) && !empty($tunnelToken))', $tpl);
        $this->assertStringContainsString('environment=TUNNEL_TOKEN=', $tpl);
    }

    public function test_static_supervisor_programs_exist(): void
    {
        $base = $this->projectTemplateDir() . '/supervisord.conf.d';
        $this->assertFileExists($base . '/docker.conf');
        $this->assertFileExists($base . '/cron.conf');
        $docker = (string) file_get_contents($base . '/docker.conf');
        $cron = (string) file_get_contents($base . '/cron.conf');
        $this->assertStringContainsString('command=dockerd', $docker);
        $this->assertStringContainsString('command=cron -f', $cron);
    }
}

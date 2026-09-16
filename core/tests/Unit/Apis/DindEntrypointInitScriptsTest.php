<?php

namespace Tests\Unit\Apis;

use App\System\Project\Dind\AccountTemplate;
use PHPUnit\Framework\TestCase;

/**
 * The init scripts an account runs before supervisord starts dockerd.
 *
 * They run from `/entrypoint.sh` as `entrypoint.d/*.sh` one-shots ahead of
 * `exec supervisord` — so this is the hook for anything that must be true
 * before the nested Docker daemon comes up, without rebuilding the account image.
 *
 * Since `/run` became a tmpfs it starts empty on every boot, and the
 * directories Debian's packages expect there — `/run/php` owned by www-data,
 * `/run/lock`, `/run/dbus` — are not recreated by anything else: there is no
 * systemd in the container to run tmpfiles at boot.
 */
class DindEntrypointInitScriptsTest extends TestCase
{
    private function script(): string
    {
        // Mocks rather than stubs, because the property and accessors are
        // typed against the concrete classes. Only username and uid are read.
        // A real model: getUid() reads details['UID'], so nothing needs
        // stubbing and the test exercises the same accessors production does.
        $model = new \App\Models\User();
        $model->username = 'acme';
        $model->details = ['UID' => 1234];

        $dind = $this->createStub(\App\System\Project\Dind::class);
        $dind->method('userModel')->willReturn($model);

        $template = (new \ReflectionClass(AccountTemplate::class))->newInstanceWithoutConstructor();
        $property = new \ReflectionProperty(AccountTemplate::class, 'project');
        $property->setAccessible(true);
        $property->setValue($template, $dind);

        return $template->entrypointInitScripts()['useradd.sh'] ?? '';
    }

    /**
     * Outside a booted application AccountRuntime falls back to sysbox, so the
     * privileged-only cgroup handling must not appear. Belt and braces with
     * DindAccountRuntimeTest: this is the path a production account takes.
     */
    public function test_the_cgroup_dance_is_absent_by_default(): void
    {
        $this->assertStringNotContainsString('cgroup.subtree_control', $this->script());
    }

    public function test_run_is_repopulated_before_the_daemon_starts(): void
    {
        $this->assertStringContainsString('systemd-tmpfiles --create', $this->script());
    }

    public function test_repopulating_run_can_never_fail_the_boot(): void
    {
        // If tmpfiles is unhappy the account must still come up: an init
        // script that exits non-zero would take Docker and every app with it,
        // which is a worse failure than the one being prevented.
        $this->assertMatchesRegularExpression('/systemd-tmpfiles --create[^\n]*\|\| true/', $this->script());
    }

    public function test_the_account_user_is_still_created(): void
    {
        // The script's original job, unchanged.
        $script = $this->script();

        $this->assertStringContainsString('useradd --uid 1234', $script);
        $this->assertStringContainsString('acme', $script);
    }

    public function test_the_daemon_data_root_is_still_written(): void
    {
        $this->assertStringContainsString('/etc/docker/daemon.json', $this->script());
        $this->assertStringContainsString('data-root', $this->script());
    }
}

<?php

namespace Tests\Unit\Deploy\Dind;

use App\Lib\Deploy\Dind\DindImageStore;
use App\Lib\Deploy\Engine\EngineAccount;
use PHPUnit\Framework\TestCase;

/**
 * Moving a host image into an account's own Docker daemon.
 *
 * The registry path exists for layer reuse, not compression: measured on
 * 10.10.10.25, a 1GB base into an empty account is 56s by pull against 57s by
 * `save | load`, but a second image sharing its layers is 1s against 17s.
 */
class DindImageStoreTest extends TestCase
{
    private function account(): EngineAccount
    {
        return new EngineAccount(
            'demo',
            '/home/demo',
            '33:33',
            '/opt/panelalpha/shared-hosting/users/demo/docker-compose.yml'
        );
    }

    private function command(): string
    {
        return (new DindImageStore())->loadFromHostCommand($this->account(), 'panelalpha/php:8.3-pa1');
    }

    public function test_it_prefers_the_registry_when_one_is_reachable(): void
    {
        $cmd = $this->command();

        $this->assertStringContainsString('docker push', $cmd);
        $this->assertStringContainsString('docker pull', $cmd);
        $this->assertStringContainsString(DindImageStore::CACHE_REGISTRY, $cmd);
        $this->assertStringContainsString(DindImageStore::HOST_CACHE_REGISTRY, $cmd);
    }

    /**
     * The host and the account address the same registry differently, and
     * getting this backwards is invisible: the push fails, the guard catches
     * it, and every transfer silently takes the slow path while the registry
     * sits there looking installed.
     *
     * `panelalpha-cache-registry` is a name on the engine's docker network.
     * The host is not on that network, so from there it resolves against the
     * host's own DNS, misses, and falls back to HTTPS against a plain-HTTP
     * registry.
     */
    public function test_the_host_pushes_to_loopback_and_the_account_pulls_by_name(): void
    {
        $cmd = $this->command();
        $host = DindImageStore::HOST_CACHE_REGISTRY;
        $inner = DindImageStore::CACHE_REGISTRY;

        $this->assertMatchesRegularExpression(
            "/docker push -q '" . preg_quote($host, '/') . "\//",
            $cmd,
            'the host must push to an address it can actually resolve'
        );
        $this->assertMatchesRegularExpression(
            "/docker pull -q '" . preg_quote($inner, '/') . "\//",
            $cmd,
            'the account must pull by the name its daemon trusts'
        );
        $this->assertStringNotContainsString("push -q '{$inner}/", $cmd);
    }

    /**
     * Probed on the host's address, because that is where the probe runs.
     * Testing the network name here only ever produced a false negative.
     */
    public function test_the_probe_uses_the_address_the_probe_can_reach(): void
    {
        $cmd = $this->command();

        $this->assertStringContainsString(
            'curl -sf --max-time 3 http://' . DindImageStore::HOST_CACHE_REGISTRY . '/v2/',
            $cmd
        );
        $this->assertStringNotContainsString(
            'curl -sf --max-time 3 http://' . DindImageStore::CACHE_REGISTRY . '/v2/',
            $cmd
        );
    }

    public function test_it_falls_back_to_save_and_load(): void
    {
        // Both when no registry answers, and when the registry path fails
        // partway — a registry that is up but unwritable must not fail a
        // deploy that would have worked without it.
        $cmd = $this->command();

        $this->assertSame(2, substr_count($cmd, 'docker save --'), 'both branches need the fallback');
        $this->assertStringContainsString('docker load', $cmd);
        $this->assertStringContainsString('|| {', $cmd);
    }

    public function test_the_image_keeps_its_plain_name_inside_the_account(): void
    {
        // The generated Dockerfile says `FROM panelalpha/php:…`, not
        // `FROM panelalpha-cache-registry:5000/panelalpha/php:…`, so the pull
        // has to be re-tagged or every PHP build breaks.
        $cmd = $this->command();

        $this->assertMatchesRegularExpression(
            "/docker tag '" . preg_quote(DindImageStore::CACHE_REGISTRY, '/') . "\/panelalpha\/php:8\.3-pa1' 'panelalpha\/php:8\.3-pa1'/",
            $cmd
        );
    }

    public function test_the_reachability_probe_is_bounded(): void
    {
        // An unreachable registry must not hang a deploy waiting on a socket.
        $this->assertStringContainsString('--max-time 3', $this->command());
    }
}

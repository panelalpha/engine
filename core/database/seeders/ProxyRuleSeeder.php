<?php

namespace Database\Seeders;

use App\Models\ProxyRule;
use Illuminate\Database\Seeder;

class ProxyRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Get panel configuration from environment
        $panelAaPort = (int) (env('APP_LITE_AA_PORT') ?? 8443);
        $panelCaPort = (int) (env('APP_LITE_CA_PORT') ?? 8444);
        $panelAaDomain = (string) (env('APP_LITE_AA_DOMAIN') ?? 'admin.local');
        $panelCaDomain = (string) (env('APP_LITE_CA_DOMAIN') ?? 'panel.local');
        $panelProxyAaHost = (string) (env('APP_LITE_PROXY_AA_HOST') ?? 'panel-admin');
        $panelProxyAaPort = (int) (env('APP_LITE_PROXY_AA_PORT') ?? 443);
        $panelProxyCaHost = (string) (env('APP_LITE_PROXY_CA_HOST') ?? 'panel-client');
        $panelProxyCaPort = (int) (env('APP_LITE_PROXY_CA_PORT') ?? 443);

        // Admin Area HTTPS
        ProxyRule::updateOrCreate(
            [
                'owner_scope' => 'system',
                'transport' => 'http',
                'listen_port' => $panelAaPort,
                'server_name' => $panelAaDomain,
            ],
            [
                'username' => null,
                'enabled' => true,
                'listen_ip' => '*',
                'upstream_host' => $panelProxyAaHost,
                'upstream_port' => $panelProxyAaPort,
                'upstream_protocol' => 'https',
                'is_generated' => true,
                'metadata' => [
                    'description' => 'Panel Admin Area',
                    'source' => 'system-seed',
                ],
            ]
        );

        // Client Area HTTPS
        ProxyRule::updateOrCreate(
            [
                'owner_scope' => 'system',
                'transport' => 'http',
                'listen_port' => $panelCaPort,
                'server_name' => $panelCaDomain,
            ],
            [
                'username' => null,
                'enabled' => true,
                'listen_ip' => '*',
                'upstream_host' => $panelProxyCaHost,
                'upstream_port' => $panelProxyCaPort,
                'upstream_protocol' => 'https',
                'is_generated' => true,
                'metadata' => [
                    'description' => 'Panel Client Area',
                    'source' => 'system-seed',
                ],
            ]
        );

        // Also seed standard HTTP->HTTPS redirect rules if not already present
        // Admin Area HTTP redirect to HTTPS
        ProxyRule::updateOrCreate(
            [
                'owner_scope' => 'system',
                'transport' => 'http',
                'listen_port' => 80,
                'server_name' => $panelAaDomain,
            ],
            [
                'username' => null,
                'enabled' => true,
                'listen_ip' => '*',
                'upstream_host' => $panelProxyAaHost,
                'upstream_port' => 80,
                'upstream_protocol' => 'http',
                'is_generated' => true,
                'metadata' => [
                    'description' => 'Panel Admin Area HTTP redirect',
                    'source' => 'system-seed',
                ],
            ]
        );

        // Client Area HTTP redirect to HTTPS
        ProxyRule::updateOrCreate(
            [
                'owner_scope' => 'system',
                'transport' => 'http',
                'listen_port' => 80,
                'server_name' => $panelCaDomain,
            ],
            [
                'username' => null,
                'enabled' => true,
                'listen_ip' => '*',
                'upstream_host' => $panelProxyCaHost,
                'upstream_port' => 80,
                'upstream_protocol' => 'http',
                'is_generated' => true,
                'metadata' => [
                    'description' => 'Panel Client Area HTTP redirect',
                    'source' => 'system-seed',
                ],
            ]
        );
    }
}

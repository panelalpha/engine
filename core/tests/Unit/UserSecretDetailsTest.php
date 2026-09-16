<?php

namespace Tests\Unit;

use App\Http\Resources\UserResource;
use App\Models\User;
use Tests\TestCase;

class UserSecretDetailsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('k', 32))]);
        $this->app->forgetInstance('encrypter');
    }

    public function test_secret_details_are_encrypted_at_rest_and_decrypted_for_internal_access(): void
    {
        $user = new User();
        $user->details = [
            'git_token' => 'git-secret',
            'cloudflare_api_token' => 'cf-api-secret',
            'cloudflare_tunnel_token' => 'cf-tunnel-secret',
            'env_vars' => ['API_TOKEN' => 'env-secret'],
            'deploy_strategy' => 'static',
        ];

        $stored = (string) $user->getAttributes()['details'];
        $this->assertStringNotContainsString('git-secret', $stored);
        $this->assertStringNotContainsString('cf-api-secret', $stored);
        $this->assertStringNotContainsString('cf-tunnel-secret', $stored);
        $this->assertStringNotContainsString('env-secret', $stored);
        $this->assertSame('git-secret', $user->getGitToken());
        $this->assertSame('cf-api-secret', $user->getCloudflareApiToken());
        $this->assertSame('cf-tunnel-secret', $user->getCloudflareTunnelToken());
        $this->assertSame(['API_TOKEN' => 'env-secret'], $user->getEnvVars());
        $this->assertSame('static', $user->getDeployStrategy());
    }

    public function test_plaintext_legacy_details_remain_readable_and_upgrade_on_next_write(): void
    {
        $user = new User();
        $user->setRawAttributes([
            'details' => json_encode([
                'git_token' => 'legacy-token',
                'env_vars' => ['APP_KEY' => 'legacy-key'],
            ]),
        ]);

        $this->assertSame('legacy-token', $user->getGitToken());
        $this->assertSame(['APP_KEY' => 'legacy-key'], $user->getEnvVars());

        $user->setDetails(['deployment_status' => 'success']);
        $stored = (string) $user->getAttributes()['details'];
        $this->assertStringNotContainsString('legacy-token', $stored);
        $this->assertStringNotContainsString('legacy-key', $stored);
        $this->assertSame('legacy-token', $user->getGitToken());
    }

    public function test_user_resource_never_returns_secret_detail_fields(): void
    {
        $user = new User();
        $user->details = [
            'git_token' => 'git-secret',
            'cloudflare_api_token' => 'cf-api-secret',
            'cloudflare_tunnel_token' => 'cf-tunnel-secret',
            'cloudflare_tunnel_id' => 'tunnel-id-public',
            'env_vars' => ['API_TOKEN' => 'env-secret'],
            'deploy_strategy' => 'static',
        ];

        $resource = new UserResource($user);
        $method = new \ReflectionMethod($resource, 'publicDetails');
        $method->setAccessible(true);
        $details = $method->invoke($resource, $user);

        $this->assertArrayNotHasKey('git_token', $details);
        $this->assertArrayNotHasKey('env_vars', $details);
        $this->assertArrayNotHasKey('cloudflare_api_token', $details);
        $this->assertArrayNotHasKey('cloudflare_tunnel_token', $details);
        $this->assertSame('tunnel-id-public', $details['cloudflare_tunnel_id']);
        $this->assertSame('static', $details['deploy_strategy']);
    }

    public function test_site_git_token_is_encrypted_at_rest_and_does_not_clobber_git_token(): void
    {
        $user = new User();
        $user->details = [
            'git_token' => 'deploy-secret',
            'site_git' => [
                'public_html' => [
                    'repo_url' => 'https://github.com/org/repo.git',
                    'branch' => 'main',
                    'token' => 'pat-secret',
                ],
            ],
        ];

        $stored = (string) $user->getAttributes()['details'];
        $this->assertStringNotContainsString('pat-secret', $stored);
        $this->assertStringNotContainsString('deploy-secret', $stored);
        $this->assertSame('deploy-secret', $user->getGitToken());
        $site = $user->getSiteGit('public_html');
        $this->assertNotNull($site);
        $this->assertSame('pat-secret', $site['token']);
        $this->assertSame('https://github.com/org/repo.git', $site['repo_url']);
    }

    public function test_user_resource_omits_site_git(): void
    {
        $user = new User();
        $user->details = [
            'site_git' => ['public_html' => ['token' => 'pat-secret', 'repo_url' => 'https://x/y.git', 'branch' => 'main']],
            'deploy_strategy' => 'static',
        ];
        $resource = new UserResource($user);
        $method = new \ReflectionMethod($resource, 'publicDetails');
        $method->setAccessible(true);
        $details = $method->invoke($resource, $user);
        $this->assertArrayNotHasKey('site_git', $details);
    }

    public function test_get_site_git_falls_back_to_deploy_git_repo_for_project_key(): void
    {
        $user = new User();
        $user->details = [
            'git_repo' => 'https://github.com/org/app.git',
            'git_branch' => 'main',
            'git_token' => 'deploy-secret',
        ];

        $site = $user->getSiteGit('project');
        $this->assertNotNull($site);
        $this->assertSame('https://github.com/org/app.git', $site['repo_url']);
        $this->assertSame('main', $site['branch']);
        $this->assertSame('deploy-secret', $site['token']);
        $this->assertNull($user->getSiteGit('public_html'));
    }
}

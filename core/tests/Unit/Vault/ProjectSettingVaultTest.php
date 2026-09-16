<?php

namespace Tests\Unit\Vault;

use App\Lib\Vault\RequestVault;
use App\Models\SecretVaultEntry;
use App\System\Project\Settings;
use Illuminate\Validation\ValidationException;

/**
 * The Cloudflare token goes through the vault, like every other secret.
 *
 * `project_setting_set` is the one tool that takes a credential rather than a
 * preference, and it was the one secret-bearing field in the API that could not
 * take a `vault:<ref>` -- `RequestVault` was wired into `source_inspect` and
 * `project_create` and nowhere else. The documented advice for this tool is
 * "never ask for that token in chat", and that was unimplementable through the
 * tool itself: an assistant either relayed the secret or could not do the job.
 *
 * The resolution itself is `RequestVault`'s, and tested with it. What is tested
 * here is the decision this controller makes about *which* settings may take a
 * reference, which is the part that could go wrong quietly.
 */
class ProjectSettingVaultTest extends VaultTestCase
{
    public function test_a_cloudflare_token_is_a_secret_setting(): void
    {
        $this->assertTrue(Settings::isSecret('cloudflare-api-token'));
    }

    /**
     * The request field is `value`, so `RequestVault::get('value')` is what the
     * controller must ask for. A reference in a differently named field would
     * resolve to nothing and be stored as a literal `vault:...` string -- which
     * is the failure this asserts against.
     */
    public function test_a_reference_in_the_value_field_resolves_to_the_pasted_secret(): void
    {
        [, $ref] = $this->entry([
            'type' => 'cloudflare_api_token',
            'secret' => 'cf-real-token-value',
        ]);

        $request = \Illuminate\Http\Request::create(
            '/',
            'PUT',
            ['value' => RequestVault::PREFIX . $ref]
        );
        $this->app->instance('request', $request);

        $this->assertSame(
            'cf-real-token-value',
            RequestVault::get('value', 'cloudflare_api_token')
        );
    }

    /** A literal still passes through, so nothing changes for a caller with one. */
    public function test_a_literal_value_passes_through_untouched(): void
    {
        $request = \Illuminate\Http\Request::create('/', 'PUT', ['value' => 'a-literal-token']);
        $this->app->instance('request', $request);

        $this->assertSame(
            'a-literal-token',
            RequestVault::get('value', 'cloudflare_api_token')
        );
    }

    /**
     * An unusable reference is a 422 rather than a passthrough.
     *
     * Storing the literal text `vault:xyz` as a token would surface later as a
     * Cloudflare rejection nobody can trace; the exception names the field and
     * says what to do.
     */
    public function test_an_unknown_reference_is_refused_not_stored(): void
    {
        $request = \Illuminate\Http\Request::create('/', 'PUT', ['value' => 'vault:nothing-here']);
        $this->app->instance('request', $request);

        $this->expectException(ValidationException::class);
        RequestVault::get('value', 'cloudflare_api_token');
    }

    /**
     * A reference for one type does not resolve into another field.
     *
     * The type is the field name by design, so a `git_token` entry cannot be
     * used as a Cloudflare token even if someone tries.
     */
    public function test_a_reference_of_the_wrong_type_does_not_resolve(): void
    {
        [, $ref] = $this->entry([
            'type' => SecretVaultEntry::TYPE_GIT_TOKEN,
            'secret' => 'gh-token-not-a-cloudflare-one',
        ]);

        $request = \Illuminate\Http\Request::create(
            '/',
            'PUT',
            ['value' => RequestVault::PREFIX . $ref]
        );
        $this->app->instance('request', $request);

        $this->expectException(ValidationException::class);
        RequestVault::get('value', 'cloudflare_api_token');
    }

    /**
     * An entry minted but not yet pasted is its own sentence, because the fix
     * is different: open the URL rather than create another entry.
     */
    public function test_an_unfilled_reference_says_to_open_the_url(): void
    {
        [, $ref] = $this->entry(['type' => 'cloudflare_api_token']);

        $request = \Illuminate\Http\Request::create(
            '/',
            'PUT',
            ['value' => RequestVault::PREFIX . $ref]
        );
        $this->app->instance('request', $request);

        try {
            RequestVault::get('value', 'cloudflare_api_token');
            $this->fail('an unfilled reference must not resolve');
        } catch (ValidationException $e) {
            $this->assertStringContainsString(
                'no secret pasted yet',
                $e->getMessage()
            );
        }
    }
}

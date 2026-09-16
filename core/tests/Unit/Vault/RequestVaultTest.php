<?php

namespace Tests\Unit\Vault;

use App\Lib\Vault\RequestVault;
use App\Models\SecretVaultEntry;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The resolver: `RequestVault::get($field)` reads the current request and
 * swaps a `vault:<ref>` for the pasted secret, with one sentence per way a
 * reference can be unusable.
 *
 * The key is both the field it reads and the vault `type` it matches -- the
 * two are one name, by design, so this also covers that an entry created for
 * one field can never be spent as another.
 */
class RequestVaultTest extends VaultTestCase
{
    public function test_a_literal_passes_through_untouched(): void
    {
        $this->requestWith(['git_token' => 'ghp_realtoken']);

        $this->assertSame('ghp_realtoken', RequestVault::get('git_token'));
    }

    public function test_an_absent_field_is_null(): void
    {
        $this->requestWith([]);

        $this->assertNull(RequestVault::get('git_token'));
    }

    public function test_an_empty_string_passes_through(): void
    {
        // '' is how a caller clears a stored credential; it must not become
        // a lookup, let alone an error.
        $this->requestWith(['git_token' => '']);

        $this->assertSame('', RequestVault::get('git_token'));
    }

    public function test_a_filled_reference_resolves_to_the_secret(): void
    {
        [$entry, $ref] = $this->entry(['secret' => 'ghp_pasted']);
        $this->requestWith(['git_token' => RequestVault::PREFIX . $ref]);

        $this->assertSame('ghp_pasted', RequestVault::get('git_token'));
        $this->assertSame(1, $entry->refresh()->use_count);
    }

    public function test_a_reference_is_reusable_until_ttl(): void
    {
        [$entry, $ref] = $this->entry(['secret' => 'ghp_pasted']);
        $this->requestWith(['git_token' => RequestVault::PREFIX . $ref]);

        RequestVault::get('git_token');
        RequestVault::get('git_token');
        RequestVault::get('git_token');

        $this->assertSame(3, $entry->refresh()->use_count);
    }

    public function test_an_unknown_reference_is_a_422_naming_the_field(): void
    {
        $this->requestWith(['git_token' => RequestVault::PREFIX . 'never-created']);

        try {
            RequestVault::get('git_token');
            $this->fail('An unknown reference must not pass through as a literal.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('git_token', $e->errors());
            $this->assertStringContainsString('vault_secret_create', $e->errors()['git_token'][0]);
        }
    }

    public function test_a_wrong_type_reference_does_not_resolve(): void
    {
        // Created for git_token, asked for as env_vars: the type guard is
        // what stops a ref minted for one field being spent as another.
        [, $ref] = $this->entry(['secret' => 'ghp_pasted']);

        $this->requestWith(['env_vars' => ['DB_PASSWORD' => RequestVault::PREFIX . $ref]]);

        try {
            RequestVault::envVars();
            $this->fail('A ref of one type must not resolve as another.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('env_vars', $e->errors());
        }
    }

    public function test_an_unfilled_reference_is_a_422_saying_so(): void
    {
        [, $ref] = $this->entry(); // no secret
        $this->requestWith(['git_token' => RequestVault::PREFIX . $ref]);

        try {
            RequestVault::get('git_token');
            $this->fail('An unfilled reference must fail, not resolve to null.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('pasted', $e->errors()['git_token'][0]);
        }
    }

    public function test_an_expired_reference_is_a_422_saying_so(): void
    {
        [, $ref] = $this->entry(['secret' => 'ghp_pasted', 'expires_at' => now()->subMinute()]);
        $this->requestWith(['git_token' => RequestVault::PREFIX . $ref]);

        try {
            RequestVault::get('git_token');
            $this->fail('An expired reference must fail.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('expired', $e->errors()['git_token'][0]);
        }
    }

    public function test_env_vars_resolves_only_prefixed_values(): void
    {
        [$filled, $ref] = $this->entry(['secret' => 'db-pass', 'type' => SecretVaultEntry::TYPE_ENV_VARS]);
        $this->requestWith(['env_vars' => [
            'APP_ENV' => 'production',
            'DB_PASSWORD' => RequestVault::PREFIX . $ref,
        ]]);

        $resolved = RequestVault::envVars();

        $this->assertSame('production', $resolved['APP_ENV']);
        $this->assertSame('db-pass', $resolved['DB_PASSWORD']);
        $this->assertSame(1, $filled->refresh()->use_count);
    }

    public function test_env_vars_absent_is_null(): void
    {
        $this->requestWith([]);

        $this->assertNull(RequestVault::envVars());
    }

    /**
     * Swap the request the resolver reads. RequestVault reads the container's
     * current request, exactly as it would inside a controller.
     *
     * @param array<string, mixed> $input
     */
    private function requestWith(array $input): void
    {
        $this->app->instance('request', Request::create('/api/projects', 'POST', $input));
    }
}
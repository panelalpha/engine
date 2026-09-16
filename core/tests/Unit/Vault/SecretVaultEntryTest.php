<?php

namespace Tests\Unit\Vault;

use App\Lib\Vault\RequestVault;
use App\Models\SecretVaultEntry;

/**
 * The model: hashing is stable (the form URL and the `vault:` lookup are one
 * string, one spelling of the truth), the secret is encrypted at rest, and
 * status follows the entry's own two clocks.
 */
class SecretVaultEntryTest extends VaultTestCase
{
    public function test_the_ref_hash_is_sha256_hex(): void
    {
        $this->assertSame(
            hash('sha256', 'abc123'),
            SecretVaultEntry::hashRef('abc123')
        );
        $this->assertSame(64, strlen(SecretVaultEntry::hashRef('abc123')));
    }

    public function test_the_secret_is_encrypted_at_rest(): void
    {
        [$entry] = $this->entry(['secret' => 'ghp_something']);

        $this->assertStringNotContainsString('ghp_something', (string) $entry->getAttributes()['secret_encrypted']);
        $this->assertSame('ghp_something', $entry->revealSecret());
    }

    public function test_status_is_pending_then_filled_then_expired(): void
    {
        [$entry] = $this->entry();
        $this->assertSame('pending', $entry->status());

        $entry->setSecret('ghp_something');
        $this->assertSame('filled', $entry->status());

        $entry->expires_at = now()->subSecond();
        $this->assertSame('expired', $entry->status());
    }

    public function test_a_repeated_paste_replaces_the_secret(): void
    {
        [$entry] = $this->entry(['secret' => 'first-paste']);

        $entry->setSecret('second-paste');
        $this->assertSame('second-paste', $entry->revealSecret());
    }

    public function test_reveal_on_an_unfilled_entry_is_null(): void
    {
        [$entry] = $this->entry();

        $this->assertNull($entry->revealSecret());
    }

    public function test_the_prefix_is_what_marks_a_reference(): void
    {
        // The contract the call sites rely on: only this exact prefix turns a
        // value into a lookup. Anything else -- including a secret that
        // happens to start with the word -- is a literal.
        $this->assertSame('vault:', RequestVault::PREFIX);
        $this->assertTrue(str_starts_with(RequestVault::PREFIX . 'abc', RequestVault::PREFIX));
        $this->assertFalse(str_starts_with('Vault:abc', RequestVault::PREFIX));
    }
}
<?php

namespace Tests\Unit\Vault;

use App\Models\SecretVaultEntry;
use Illuminate\Support\Facades\Artisan;

/**
 * The sweeper: expired entries go, live ones stay, and the grace window
 * keeps a just-expired ref readable as `expired` instead of `unknown` for
 * an hour after its clock ran out.
 */
class VaultPurgeCommandTest extends VaultTestCase
{
    public function test_deletes_only_expired_entries(): void
    {
        [, $liveRef] = $this->entry(['secret' => 'still-good']);
        [, $expiredRef] = $this->entry(['secret' => 'gone', 'expires_at' => now()->subHours(2)]);

        $exit = Artisan::call('vault:purge');

        $this->assertSame(0, $exit);
        $this->assertNotNull(
            SecretVaultEntry::query()->where('ref_hash', SecretVaultEntry::hashRef($liveRef))->first(),
            'A live entry must survive the sweep.'
        );
        $this->assertNull(
            SecretVaultEntry::query()->where('ref_hash', SecretVaultEntry::hashRef($expiredRef))->first(),
            'An expired entry must be deleted.'
        );
    }

    public function test_grace_keeps_a_just_expired_entry(): void
    {
        [, $justExpiredRef] = $this->entry(['secret' => 'graceful', 'expires_at' => now()->subMinute()]);

        Artisan::call('vault:purge'); // default grace: 3600s

        $this->assertNotNull(
            SecretVaultEntry::query()->where('ref_hash', SecretVaultEntry::hashRef($justExpiredRef))->first(),
            'A just-expired entry is kept for the grace window, so a status check still explains itself.'
        );

        Artisan::call('vault:purge', ['--grace' => 0]);

        $this->assertNull(
            SecretVaultEntry::query()->where('ref_hash', SecretVaultEntry::hashRef($justExpiredRef))->first(),
            'With no grace, even a just-expired entry is deleted.'
        );
    }
}
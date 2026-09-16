<?php

namespace App\Console\Commands\Vault;

use App\Models\SecretVaultEntry;
use Illuminate\Console\Command;

/**
 * Removes expired vault entries and their secrets.
 *
 * An expired entry cannot be resolved ({@see \App\Lib\Vault\RequestVault}
 * refuses it), but the ciphertext would sit in `secret_vault_entries` for
 * ever without this -- and a secret that no longer works should not outlive
 * its usefulness on disk any more than in behaviour.
 *
 * `--grace` keeps rows an hour past expiry before deleting, so a status
 * check on a just-expired ref still explains itself (`expired`) instead of
 * vanishing into `unknown` while somebody is reading the page.
 */
class VaultPurgeCommand extends Command
{
    protected $signature = 'vault:purge {--grace=3600 : Seconds to keep rows past their expiry}';

    protected $description = 'Delete expired secret vault entries';

    public function handle(): int
    {
        $grace = max(0, (int) $this->option('grace'));

        $deleted = SecretVaultEntry::query()
            ->where('expires_at', '<=', now()->subSeconds($grace))
            ->delete();

        $this->info("Deleted {$deleted} expired vault entr" . ($deleted === 1 ? 'y' : 'ies') . ($grace > 0 ? " (grace {$grace}s)" : '') . '.');

        return self::SUCCESS;
    }
}
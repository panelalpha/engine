<?php

namespace Tests\Unit\Vault;

use App\Models\SecretVaultEntry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The vault tables on an in-memory sqlite database, mirroring
 * SqliteTaskTestCase: the production database is a real MySQL on a host this
 * suite must not touch, so the migration runs by hand against the connection
 * the test configures.
 */
abstract class VaultTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.key' => 'base64:' . base64_encode(str_repeat('k', 32)),
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        $this->app->forgetInstance('encrypter');
        // No forgetInstance('db'): purge/reconnect must act on the same
        // DatabaseManager Eloquent already holds, or the schema lands on a
        // manager the models never see.
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::connection('sqlite')->create('secret_vault_entries', function (Blueprint $table) {
            $table->id();
            $table->char('ref_hash', 64)->unique()->index();
            $table->string('type');
            $table->text('secret_encrypted')->nullable();
            $table->timestamp('filled_at')->nullable();
            $table->timestamp('expires_at');
            $table->unsignedInteger('use_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlite')->dropIfExists('secret_vault_entries');
        parent::tearDown();
    }

    /**
     * An entry straight into the table, with the raw ref handed back so a
     * test can build the `vault:<ref>` value the way the API would.
     *
     * @param array<string, mixed> $overrides
     * @return array{0: SecretVaultEntry, 1: string}
     */
    protected function entry(array $overrides = []): array
    {
        $ref = 'ref' . bin2hex(random_bytes(8));
        $params = array_merge([
            'ref_hash' => SecretVaultEntry::hashRef($ref),
            'type' => SecretVaultEntry::TYPE_GIT_TOKEN,
            'expires_at' => now()->addSeconds(SecretVaultEntry::TTL_SECONDS),
        ], $overrides);

        /** @var SecretVaultEntry */
        $entry = SecretVaultEntry::create($params);
        if (!empty($overrides['secret'])) {
            $entry->setSecret((string) $overrides['secret']);
            $entry->save();
        }

        return [$entry, $ref];
    }
}
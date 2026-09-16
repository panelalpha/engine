<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * One paste-slot for a secret the API caller does not want to send.
 *
 * An agent creates an entry (`vault_secret_create`), gets back a one-line URL
 * and a `vault:<ref>` reference, the customer pastes the secret into the form
 * at that URL, and the next API call that sends `vault:<ref>` in the
 * matching request field receives the plaintext in its place.
 *
 * The raw ref is never stored: only `ref_hash` (sha-256) is. The form URL and
 * the reference are the same string, so one hashed column serves both, and a
 * database dump contains no live form links.
 *
 * @property int       $id
 * @property string    $ref_hash
 * @property string    $type
 * @property ?string   $secret_encrypted
 * @property ?Carbon   $filled_at
 * @property Carbon    $expires_at
 * @property int       $use_count
 * @property ?Carbon   $last_used_at
 * @property Carbon    $created_at
 * @property Carbon    $updated_at
 */
class SecretVaultEntry extends Model
{
    public const TYPE_GIT_TOKEN = 'git_token';
    public const TYPE_ENV_VARS = 'env_vars';

    /**
     * A project's Cloudflare API token, as its setting key spells it.
     *
     * The setting is `cloudflare-api-token` and the vault type is
     * `cloudflare_api_token`: names are hyphenated in one and snake_case in
     * the other, and the conversion is the caller's
     * ({@see \App\Http\Controllers\User\ProjectSettingController}).
     */
    public const TYPE_CLOUDFLARE_API_TOKEN = 'cloudflare_api_token';

    /**
     * The types that ship with a help file of their own. Anything else is
     * still a valid type -- the form falls back to the default help -- but
     * a type listed here without its file would show the fallback while
     * claiming otherwise, so the list and the files keep each other honest.
     *
     * @var list<string>
     */
    public const TYPES_WITH_HELP = [
        self::TYPE_GIT_TOKEN,
        self::TYPE_CLOUDFLARE_API_TOKEN,
    ];

    /** Seconds an entry stays usable, from creation: paste window and reads alike. */
    public const TTL_SECONDS = 3600;

    protected $fillable = [
        'ref_hash',
        'type',
        'secret_encrypted',
        'filled_at',
        'expires_at',
    ];

    protected $casts = [
        'filled_at'    => 'datetime',
        'expires_at'   => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /**
     * sha-256 of the raw ref, hex -- the only spelling ever written to the
     * column, so the URL and the `vault:` lookup cannot disagree.
     */
    public static function hashRef(string $ref): string
    {
        return hash('sha256', $ref);
    }

    /**
     * Markdown help rendered on the paste form, for this entry's type.
     *
     * The type is whatever field the caller passed at create time -- it is
     * not a closed list, and the help is found by convention: a file named
     * `resources/vault/<type>.md` describes that type; anything without one
     * gets `resources/vault/default.md`. A typo'd or unknown type therefore
     * renders the generic page rather than breaking, and adding help for a
     * new type is dropping a file in -- no code, no allowlist.
     *
     * @return string rendered HTML. From a file on this server, rendered by
     *         CommonMark with raw HTML disabled, so nothing here can script
     *         the paste page.
     */
    public static function help(string $type): string
    {
        $file = resource_path('vault/' . self::helpFileName($type));

        if (!is_file($file) || !is_readable($file)) {
            $file = resource_path('vault/default.md');
        }

        if (!is_file($file) || !is_readable($file)) {
            return '';
        }

        return (string) Str::markdown((string) file_get_contents($file), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /**
     * The type doubles as a filename, so it may only be the characters a
     * sane filename can hold -- snake_case words. Anything else (a caller
     * that sent a field name that is not, or junk) falls back to the
     * default help rather than probing paths.
     */
    private static function helpFileName(string $type): string
    {
        return preg_match('/^[a-z][a-z0-9_]*$/', $type) === 1 ? $type . '.md' : 'default.md';
    }

    public function expired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * `pending` (no paste yet), `filled` (usable), or `expired`.
     */
    public function status(): string
    {
        if ($this->expired()) {
            return 'expired';
        }

        return $this->filled_at !== null ? 'filled' : 'pending';
    }

    public function setSecret(string $secret): void
    {
        $this->secret_encrypted = Crypt::encryptString($secret);
        $this->filled_at = Carbon::now();
    }

    /**
     * The plaintext. null only for a corrupt ciphertext -- an APP_KEY
     * rotation, which is better seen here than at clone time.
     */
    public function revealSecret(): ?string
    {
        if ($this->secret_encrypted === null) {
            return null;
        }

        try {
            return Crypt::decryptString($this->secret_encrypted);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
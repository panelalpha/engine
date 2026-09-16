<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'token_id',
        'token_name',
        'tool_name',
        'input',
        'status',
        'error_message',
    ];

    protected $casts = [
        'input' => 'array',
    ];

    /**
     * Argument names whose value never reaches the log.
     *
     * Matched as suffixes, not exact names: the credential arguments across the
     * tool surface are `password` and `git_token` but also `admin_password`,
     * `sendgrid_api_token`, `mailchannels_password`, `amazon_ses_smtp_password`
     * and `smtp_password`. An exact-name list silently missed those two whole
     * families, so anything *ending* in one of these is redacted.
     *
     * These rows are readable through GET /api/mcp-activity-logs, so storing a
     * credential verbatim would put it in plaintext behind an ordinary read
     * endpoint. Tests\Unit\Mcp\ActivityLogRedactionTest checks this list against
     * the arguments the generated tools actually declare.
     */
    public const REDACT_SUFFIXES = [
        'password',
        'passwd',
        'token',
        'secret',
        'private_key',
        'api_key',
    ];

    public const REDACTED = '[redacted]';

    /**
     * Strip credentials on the way in, so no writer can forget to. Applies to
     * the MCP middleware and to the panel's POST /api/mcp-activity-logs alike.
     */
    public function setInputAttribute(mixed $value): void
    {
        $this->attributes['input'] = $value === null
            ? null
            : json_encode(is_array($value) ? self::redact($value) : $value);
    }

    /**
     * Matched case-insensitively against the key, at every depth.
     *
     * @param array<array-key, mixed> $input
     * @return array<array-key, mixed>
     */
    public static function redact(array $input): array
    {
        $out = [];

        foreach ($input as $key => $value) {
            if (is_string($key) && self::isSecretKey($key)) {
                $out[$key] = self::REDACTED;
                continue;
            }

            $out[$key] = is_array($value) ? self::redact($value) : $value;
        }

        return $out;
    }

    public static function isSecretKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (self::REDACT_SUFFIXES as $suffix) {
            if ($key === $suffix || str_ends_with($key, '_' . $suffix)) {
                return true;
            }
        }

        return false;
    }

    public function token(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'token_id');
    }
}

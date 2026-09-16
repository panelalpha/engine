<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Public tunnel hostname attached to a local domain (Cloudflare or PanelAlpha Online).
 *
 * @psalm-type TunnelDetails = array{
 *   cloudflare_zone_id?: string,
 *   cloudflare_dns_record_id?: string,
 *   wdns_site_id?: int,
 *   path?: string,
 *   target_ip?: string,
 *   valid_until?: string,
 *   status?: string,
 * }
 *
 * @property int $id
 * @property int $domain_id
 * @property int $user_id
 * @property string $provider
 * @property string $hostname
 * @psalm-property ?TunnelDetails $details
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property ?Domain $domain
 * @property ?User $user
 */
class Tunnel extends Model
{
    public const PROVIDER_CLOUDFLARE = 'cloudflare';

    public const PROVIDER_PANELALPHA = 'panelalpha';

    /** @var list<string> */
    public const PROVIDERS = [
        self::PROVIDER_CLOUDFLARE,
        self::PROVIDER_PANELALPHA,
    ];

    protected $fillable = [
        'domain_id',
        'user_id',
        'provider',
        'hostname',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @psalm-return TunnelDetails
     */
    public function getDetails(): array
    {
        return is_null($this->details) ? [] : $this->details;
    }

    /**
     * @psalm-param TunnelDetails $details
     */
    public function setDetails(array $details): void
    {
        $this->details = array_merge($this->getDetails(), $details);
    }

    public function isCloudflare(): bool
    {
        return strtolower($this->provider) === self::PROVIDER_CLOUDFLARE;
    }

    public function isPanelAlpha(): bool
    {
        return strtolower($this->provider) === self::PROVIDER_PANELALPHA;
    }

    public static function findByHostname(string $hostname): ?self
    {
        /** @var ?self */
        return self::query()->where('hostname', strtolower($hostname))->first();
    }

    public static function hostnameExists(string $hostname): bool
    {
        return self::query()->where('hostname', strtolower($hostname))->exists();
    }

    /**
     * @return list<self>
     */
    public static function forDomain(Domain $domain): array
    {
        /** @var list<self> */
        return self::query()
            ->where('domain_id', $domain->id)
            ->orderBy('hostname')
            ->get()
            ->all();
    }

    public static function domainHasTunnels(Domain $domain): bool
    {
        return self::query()->where('domain_id', $domain->id)->exists();
    }

    public static function projectHasTunnels(User $user): bool
    {
        return self::query()->where('user_id', $user->id)->exists();
    }

    public static function projectHasCloudflareTunnels(User $user): bool
    {
        return self::query()
            ->where('user_id', $user->id)
            ->where('provider', self::PROVIDER_CLOUDFLARE)
            ->exists();
    }
}

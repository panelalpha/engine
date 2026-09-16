<?php

namespace App\Lib\Deploy\Telemetry;

use App\Lib\PanelAlpha\Monitoring;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Push notification preferences (email, probe targets) to monitoring.
 */
final class NotificationPreferences
{
    public const SETTING_TELEMETRY_ENABLED = 'telemetry_enabled';

    public const SETTING_NOTIFY_EMAIL = 'email';

    public static function isTelemetryEnabled(): bool
    {
        $override = Setting::get(self::SETTING_TELEMETRY_ENABLED);
        if ($override === '0' || $override === 'false') {
            return false;
        }
        if ($override === '1' || $override === 'true') {
            return true;
        }

        return (bool) config('telemetry.enabled', true);
    }

    public static function notifyEmail(): ?string
    {
        $email = Setting::get(self::SETTING_NOTIFY_EMAIL);
        if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        return null;
    }

    public static function serverProbeUrl(): ?string
    {
        $cert = Setting::get('cert_domain');
        if (is_string($cert) && $cert !== '') {
            return 'https://'.ltrim($cert, '.');
        }

        $ipv4 = Setting::get('default_ipv4');
        if (is_string($ipv4) && filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return 'https://'.str_replace('.', '-', $ipv4).'.panelalpha.direct';
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function appProbeUrls(): array
    {
        $urls = [];
        try {
            foreach (User::query()->whereNotNull('domain')->pluck('domain') as $domain) {
                if (! is_string($domain) || $domain === '') {
                    continue;
                }
                $urls[] = str_starts_with($domain, 'http') ? rtrim($domain, '/') : 'https://'.$domain;
            }
        } catch (Throwable $e) {
            Log::debug('notification preferences: could not list app domains: '.$e->getMessage());
        }

        return array_values(array_unique($urls));
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public static function sync(?bool $enabled = null): array
    {
        $endpoint = Monitoring::url((string) config('telemetry.reports_path', Telemetry::EVENTS_PATH));
        if ($endpoint === '') {
            return ['ok' => false, 'message' => 'No monitoring endpoint configured'];
        }

        $enabled ??= self::isTelemetryEnabled();
        $email = self::notifyEmail();
        $serverUrl = self::serverProbeUrl();

        $payload = [
            'events' => [[
                'type' => 'notification.preferences',
                'occurred_at' => now()->toIso8601String(),
                'panel_url' => $serverUrl,
                'payload' => [
                    'enabled' => $enabled,
                    'notify_email' => $email,
                    'server_probe_url' => $serverUrl,
                    'app_probe_urls' => $enabled ? self::appProbeUrls() : [],
                ],
            ]],
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'PanelAlpha-Engine/notification-preferences',
        ];
        $licenseKey = Setting::get('license_key');
        if (is_string($licenseKey) && $licenseKey !== '') {
            $headers['License-Key'] = $licenseKey;
        }
        $token = trim((string) config('telemetry.token', ''));
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout((int) config('telemetry.timeout', 15))
                ->post($endpoint, $payload);
            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'message' => 'HTTP '.$response->status().': '.$response->body(),
                ];
            }

            return ['ok' => true, 'message' => 'Preferences synced to monitoring'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}

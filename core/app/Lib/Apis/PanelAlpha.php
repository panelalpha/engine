<?php

namespace App\Lib\Apis;

use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Shared transport for PanelAlpha cloud services (hub and monitoring).
 *
 * Join rules and the licensed HTTP client live here so Integrations only
 * decide *which* host and *which* path. Empty base yields '' rather than a
 * relative URL — callers must check before posting.
 */
class PanelAlpha
{
    /**
     * Join `{base}/{path}`, or '' when base is empty.
     *
     * Trailing slash on the base and leading slash on the path are stripped
     * so callers can pass either form without double-slashing.
     */
    public static function url(string $base, string $path): string
    {
        $base = rtrim(trim($base), '/');

        return $base === '' ? '' : $base . '/' . ltrim(trim($path), '/');
    }

    /**
     * JSON client with optional Bearer from the engine's license key.
     *
     * Used by the hub's WithoutDNS proxy. Monitoring ingest authenticates
     * separately (telemetry.token) and does not go through this method.
     */
    public static function http(): PendingRequest
    {
        $pending = Http::acceptJson()->asJson()->timeout(60);
        $licenseKey = Setting::get('license_key');
        if (is_string($licenseKey) && trim($licenseKey) !== '') {
            $pending = $pending->withToken(trim($licenseKey));
        }

        return $pending;
    }
}

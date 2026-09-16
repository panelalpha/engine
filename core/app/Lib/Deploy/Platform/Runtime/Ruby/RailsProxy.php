<?php

namespace App\Lib\Deploy\Platform\Runtime\Ruby;

use App\Lib\Deploy\Template\Template;

/**
 * What a Rails app has to be told about the reverse proxy in front of it.
 *
 * force_ssl stays off: the hosting proxy terminates TLS, usually with a
 * self-signed certificate, so the HSTS header Rails 8 sends by default pins
 * the browser to a certificate it does not trust and the next navigation
 * fails inside the app's service worker as "offline". assume_ssl stays on
 * when the public URL is https, so cookies and generated links still look
 * like HTTPS.
 */
final class RailsProxy
{
    private const HTTPS = 'https://';

    /**
     * @return array<string, string>
     */
    public static function sslEnvironment(?string $publicUrl): array
    {
        return [
            'RAILS_FORCE_SSL' => 'false',
            'RAILS_ASSUME_SSL' => self::isHttps($publicUrl) ? 'true' : 'false',
        ];
    }

    /**
     * Adds the proxy hostname to Host Authorization, but only when the app
     * already keeps a whitelist. An empty `config.hosts` is Rails' way of
     * saying "accept any Host" — appending to it would flip the app from open
     * to single-host and 403 every other name it answers to.
     */
    public static function hostAuthorizationInitializer(?string $publicUrl = null): string
    {
        return Template::named('rails/host-authorization')->render([
            'host' => self::rubyHost($publicUrl),
        ]);
    }

    private static function isHttps(?string $publicUrl): bool
    {
        return is_string($publicUrl) && str_starts_with(strtolower(trim($publicUrl)), self::HTTPS);
    }

    private static function rubyHost(?string $publicUrl): string
    {
        $host = is_string($publicUrl) ? parse_url($publicUrl, PHP_URL_HOST) : null;
        $host = is_string($host) ? trim($host) : '';

        return $host === '' ? '' : var_export($host, true);
    }
}

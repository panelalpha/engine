<?php

namespace App\Lib\Deploy\Compose;

/**
 * Public-URL aliases. Applications behind the reverse proxy generate http://
 * links unless told the external origin, and every framework spells the
 * variable differently, so all the usual names are set.
 */
final class PublicUrlEnvironment
{
    /** @var list<string> */
    private const URL_KEYS = ['URL', 'PUBLIC_URL', 'BASE_URL', 'APP_URL', 'ASSET_URL', 'SITE_URL', 'ENDURAIN_HOST'];

    /**
     * The same fact spelled as a bare hostname: an Apache `php:*-apache` image
     * ships a `ServerName ${SERVERNAME}` vhost whose default answers 403 over an
     * empty document root. `VIRTUAL_HOST` is excluded because it tells a proxy
     * sidecar where to route, which is a different claim from the app's own name.
     *
     * @var list<string>
     */
    private const HOST_KEYS = ['SERVERNAME', 'SERVER_NAME', 'DEFAULT_DOMAIN'];

    /** @var array<string, string> */
    private const HTTPS_FLAGS = ['HTTPS' => 'on', 'SSL' => 'true', 'FORCE_SSL' => 'true'];

    /**
     * @return array<string, string>
     */
    public static function for(?string $publicUrl): array
    {
        $url = is_string($publicUrl) ? trim($publicUrl) : '';
        if (preg_match('#^https?://#i', $url) !== 1) {
            return [];
        }

        $env = array_fill_keys(self::URL_KEYS, $url);

        $host = self::hostOf($url);
        if ($host !== null) {
            $env = array_merge($env, array_fill_keys(self::HOST_KEYS, $host));
        }

        return self::isHttps($url) ? array_merge($env, self::HTTPS_FLAGS) : $env;
    }

    /** The host a vhost would match on, without the scheme, port or path. */
    private static function hostOf(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }

    private static function isHttps(string $url): bool
    {
        return str_starts_with(strtolower($url), 'https://');
    }
}

<?php
declare(strict_types=1);

/*
 * Prepended to every web request (auto_prepend_file), so an application sees
 * the scheme the visitor used rather than the one that reached the container.
 *
 * The engine terminates TLS at its own proxy and forwards plain HTTP. Without
 * this, every URL an application generates comes out http:// on an https://
 * site: wrong canonical URLs, mixed-content blocks, and OAuth redirects that
 * bounce. Apache is told nothing about the proxy, so PHP has to be.
 *
 * Not a router. Apache resolves paths and honours .htaccess itself; this only
 * corrects what the proxy hid.
 */

// The ini applies to every SAPI, but a console run -- a migration, Matomo's
// own console -- has no request to correct and no $_SERVER worth touching.
if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
    return;
}

$forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['HTTP_X_FORWARDED_SCHEME'] ?? ''));
$https = $forwarded === 'https'
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on';
if (!$https) {
    // No forwarded header on this request — but the engine told the container
    // its own address at deploy time, and an https:// one settles it.
    foreach (['APP_URL', 'ASSET_URL', 'PUBLIC_URL', 'BASE_URL', 'SITE_URL', 'URL'] as $key) {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key) ?: '';
        if (is_string($value) && str_starts_with($value, 'https://')) {
            $https = true;
            break;
        }
    }
}
if ($https) {
    $GLOBALS['_SERVER']['HTTPS'] = 'on';
    $GLOBALS['_SERVER']['REQUEST_SCHEME'] = 'https';
    $GLOBALS['_SERVER']['SERVER_PORT'] = '443';
    $GLOBALS['_SERVER']['HTTP_X_FORWARDED_PROTO'] = 'https';
}

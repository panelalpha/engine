<?php

namespace App\Lib\Deploy\Platform\Runtime\Php;

use App\Lib\Deploy\Template\Template;

/**
 * Blocks until the app's database accepts connections, up to two minutes.
 *
 * Reads DB_HOST rather than assuming 127.0.0.1, so it covers both shapes: a
 * sidecar still starting alongside the app, and the account's own MySQL
 * server, which is normally already up and lets this return on the first try.
 *
 * Only meaningful when the app has a database at all, which the engine knows
 * and a manifest cannot — so this is handed to the entrypoint rather than
 * declared in laravel.yaml, where it would also fire for SQLite projects and
 * cost them two minutes of nothing.
 */
final class MysqlWait
{
    public static function command(string $php = 'php'): string
    {
        return rtrim(Template::named('script/wait-for-mysql')->render(['php' => $php]), "\n");
    }
}

<?php

namespace App\Lib\Deploy\Platform\Runtime\Php;

use App\Lib\Deploy\Compose\GeneratedCompose;

/**
 * The database container a MySQL app gets when the account has no MySQL of
 * its own.
 *
 * It shares the app's network namespace, so the app reaches it on 127.0.0.1
 * with the credentials it was already configured with.
 */
final class MysqlSidecar
{
    public const IMAGE = 'mariadb:11';

    private const FALLBACK_PASSWORD = 'app';

    /**
     * @param array{connection?: string} $db
     */
    public static function isNeeded(array $db): bool
    {
        return DatabaseSettings::fromArray($db)->isMysql();
    }

    /**
     * @param array{connection?: string, host?: string, port?: string, database?: string, username?: string, password?: string} $db
     * @return array<string, mixed>
     */
    public static function service(array $db): array
    {
        $settings = DatabaseSettings::fromArray($db);

        return [
            'image' => self::IMAGE,
            'network_mode' => 'service:app',
            'depends_on' => ['app'],
            'restart' => 'unless-stopped',
            'environment' => self::environment($settings),
            'volumes' => ['dbdata:/var/lib/mysql'],
            'labels' => [GeneratedCompose::LABEL => 'framework-db'],
        ];
    }

    /**
     * The connection variables for the **application**, which shares this
     * container's network namespace.
     *
     * `network_mode: service:app` means the database is reached at
     * 127.0.0.1 and the service name `db` resolves to nothing. The repository
     * does not know that: Firefly III's `.env.example` sets `DB_HOST=db` for
     * its own workstation compose, and that file is written to `.env` and
     * carried into the container by `env_file:`, where the engine's generated
     * `environment:` still outranks it -- so without this the app dies on
     * `getaddrinfo for db failed` beside a database that is running and
     * healthy.
     *
     * Returned as compose `environment:` rather than written into `.env`,
     * because compose gives `environment:` precedence over `env_file:`: it
     * reaches the container whatever the file says, and the file the customer
     * opens still reads the way its author wrote it.
     *
     * The credentials are restated rather than assumed to survive: they are
     * what the container was provisioned with ({@see service()}), so they are
     * the ones that work, and a `.env.example` password is a placeholder like
     * any other.
     *
     * The connection is stated as `mysql` even when the example says
     * `mariadb`: this image is MariaDB, and Laravel's driver for both is
     * spelled `mysql`.
     *
     * @return array<string, string>
     */
    public static function connectionEnvironment(DatabaseSettings $db): array
    {
        return [
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => $db->port(),
            'DB_DATABASE' => $db->database(),
            'DB_USERNAME' => $db->username(),
            'DB_PASSWORD' => $db->password(),
            // Empty, meaning "not a unix socket": a `.env.example` that
            // leaves it blank is fine, one that fills it in would otherwise
            // send the driver to a socket the sidecar does not create.
            'DB_SOCKET' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function environment(DatabaseSettings $db): array
    {
        return array_merge(
            ['MYSQL_DATABASE' => $db->database()],
            self::isRootAccount($db) ? self::rootCredentials($db) : self::userCredentials($db)
        );
    }

    private static function isRootAccount(DatabaseSettings $db): bool
    {
        $user = trim((string) ($db->username()));

        return $user === '' || $user === 'root';
    }

    /**
     * @return array<string, string>
     */
    private static function rootCredentials(DatabaseSettings $db): array
    {
        return $db->password() === ''
            ? ['MYSQL_ALLOW_EMPTY_PASSWORD' => 'yes']
            : ['MYSQL_ROOT_PASSWORD' => $db->password()];
    }

    /**
     * @return array<string, string>
     */
    private static function userCredentials(DatabaseSettings $db): array
    {
        $password = $db->password() ?: self::FALLBACK_PASSWORD;

        return [
            'MYSQL_USER' => $db->username(),
            'MYSQL_PASSWORD' => $password,
            'MYSQL_ROOT_PASSWORD' => $password,
        ];
    }
}

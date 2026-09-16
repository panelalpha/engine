<?php

/*
 * Reading and deleting Kimai users, directly from its own tables.
 *
 * It runs inside the application container and speaks plain PDO, for the same
 * reason Kimai's own `.docker/dbtest.php` does: creating a user needs the
 * framework (the password has to be hashed with the configured algorithm,
 * and the system defaults — timezone, language, skin — are applied by
 * UserService), so that half goes through `kimai:user:create`. Listing and
 * deleting need none of it, and going through PDO means no table to parse and
 * no dependence on which console commands a given Kimai release ships.
 *
 * The `roles` column is a PHP-serialised array (Doctrine's `array` type), so
 * it is unserialised here rather than parsed out of a console table.
 *
 * Output is JSON on stdout; an error is JSON on stderr with a non-zero exit.
 */

const KIMAI_TABLE = 'kimai2_users';

function fail(string $message): never
{
    fwrite(STDERR, json_encode(['error' => $message], JSON_UNESCAPED_SLASHES) . "\n");
    exit(1);
}

$url = getenv('DATABASE_URL');
if (!is_string($url) || trim($url) === '') {
    fail('DATABASE_URL is not set in the container');
}

$parts = parse_url($url);
if (!is_array($parts) || !isset($parts['host'])) {
    fail('DATABASE_URL could not be read');
}

$dsn = sprintf(
    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
    $parts['host'],
    (int) ($parts['port'] ?? 3306),
    ltrim((string) ($parts['path'] ?? ''), '/')
);

try {
    $pdo = new PDO($dsn, rawurldecode((string) ($parts['user'] ?? '')), rawurldecode((string) ($parts['pass'] ?? '')), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    fail('Could not reach the Kimai database: ' . $e->getMessage());
}

/** Kimai stores roles as a serialised PHP array. */
function rolesOf(?string $raw): array
{
    if ($raw === null || $raw === '') {
        return [];
    }
    $decoded = @unserialize($raw, ['allowed_classes' => false]);
    if (is_array($decoded)) {
        return array_values(array_filter($decoded, 'is_string'));
    }

    // A future Kimai may store JSON; accept it rather than reporting none.
    $json = json_decode($raw, true);

    return is_array($json) ? array_values(array_filter($json, 'is_string')) : [];
}

$command = $argv[1] ?? '';

switch ($command) {
    case 'list':
        $rows = $pdo->query('SELECT id, username, email, roles FROM `' . KIMAI_TABLE . '` ORDER BY id')->fetchAll();
        $users = [];
        foreach ($rows as $row) {
            $roles = rolesOf($row['roles'] ?? null);
            $users[] = [
                'id' => (string) $row['id'],
                'username' => (string) $row['username'],
                'email' => (string) ($row['email'] ?? ''),
                // The engine's contract has one role per user; Kimai's
                // hierarchy means the highest one is the one to show.
                'role' => $roles === [] ? 'ROLE_USER' : end($roles),
            ];
        }
        echo json_encode($users, JSON_UNESCAPED_SLASHES) . "\n";
        break;

    case 'show':
        // One user, by id: the panel holds the id it read from `list`, and
        // Kimai's own console addresses a user by username or email, so
        // either has to be looked up before it can be passed to a command.
        $id = $argv[2] ?? '';
        if (!ctype_digit($id)) {
            fail('show needs a numeric user id');
        }
        $statement = $pdo->prepare(
            'SELECT id, username, email, roles FROM `' . KIMAI_TABLE . '` WHERE id = ? LIMIT 1'
        );
        $statement->execute([(int) $id]);
        $row = $statement->fetch();
        if ($row === false) {
            fail("No Kimai user with id '{$id}'");
        }
        $roles = rolesOf($row['roles'] ?? null);
        echo json_encode([
            'id' => (string) $row['id'],
            'username' => (string) $row['username'],
            'email' => (string) ($row['email'] ?? ''),
            'role' => $roles === [] ? 'ROLE_USER' : end($roles),
        ], JSON_UNESCAPED_SLASHES) . "\n";
        break;

    case 'id':
        $username = $argv[2] ?? '';
        $statement = $pdo->prepare('SELECT id FROM `' . KIMAI_TABLE . '` WHERE username = ? LIMIT 1');
        $statement->execute([$username]);
        $id = $statement->fetchColumn();
        if ($id === false) {
            fail("No Kimai user named '{$username}'");
        }
        echo json_encode(['id' => (string) $id], JSON_UNESCAPED_SLASHES) . "\n";
        break;

    case 'delete':
        // By id when the caller has one, by username otherwise: the panel
        // holds ids from `users:list`, but an id pasted from elsewhere may be
        // a username.
        $identifier = $argv[2] ?? '';
        if ($identifier === '') {
            fail('users:delete needs a user id or username');
        }
        if (ctype_digit($identifier)) {
            $statement = $pdo->prepare('DELETE FROM `' . KIMAI_TABLE . '` WHERE id = ?');
            $statement->execute([(int) $identifier]);
        } else {
            $statement = $pdo->prepare('DELETE FROM `' . KIMAI_TABLE . '` WHERE username = ?');
            $statement->execute([$identifier]);
        }
        if ($statement->rowCount() === 0) {
            fail("No Kimai user matched '{$identifier}'");
        }
        // Kimai's schema cascades its preferences, timesheets and teams from
        // this row, so nothing else has to be removed by hand.
        echo json_encode(['deleted' => 1], JSON_UNESCAPED_SLASHES) . "\n";
        break;

    default:
        fail("Unknown command '{$command}'");
}

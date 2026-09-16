<?php

/**
 * Admin-user management for the panel, run inside the app container.
 *
 * Magento's core CLI covers `admin:user:create` and nothing else -- there is
 * no list, no delete, no password reset. Rather than replicate Magento's
 * password hashing against the admin_user table (it has changed twice, and a
 * wrong guess writes a hash nobody can log in with), this bootstraps Magento
 * itself and asks its own models. The cost is a couple of seconds of
 * bootstrap; the benefit is that hashing, validation and the ACL stay
 * Magento's problem.
 *
 * Lives outside pub/, so it is not reachable over HTTP.
 */

declare(strict_types=1);

const PANELALPHA_ADMIN_ROLE = 'Administrators';

function fail(string $message): never
{
    fwrite(STDERR, json_encode(['error' => $message], JSON_UNESCAPED_SLASHES) . "\n");
    exit(1);
}

function emit(mixed $value): never
{
    echo json_encode($value, JSON_UNESCAPED_SLASHES), "\n";
    exit(0);
}

$command = $argv[1] ?? '';
if ($command === '') {
    fail('no command given');
}

if (!is_file('/app/app/bootstrap.php')) {
    // The engine reinstalls file snippets and retries once when it sees this.
    fwrite(STDERR, "MISSING_SNIPPET\n");
    exit(1);
}
if (!is_file('/app/app/etc/env.php')) {
    fail('Magento is not installed yet: app/etc/env.php does not exist');
}

require '/app/app/bootstrap.php';

try {
    $bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
    $objectManager = $bootstrap->getObjectManager();
    $objectManager->get(\Magento\Framework\App\State::class)
        ->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);
} catch (\Throwable $e) {
    fail('could not bootstrap Magento: ' . $e->getMessage());
}

/** Magento's own roles, so a caller cannot invent one the ACL does not have. */
function roleNames(\Magento\Framework\ObjectManagerInterface $om): array
{
    $roles = $om->create(\Magento\Authorization\Model\ResourceModel\Role\CollectionFactory::class)
        ->create()
        ->addFieldToFilter('role_type', \Magento\Authorization\Model\Acl\Role\Group::ROLE_TYPE);

    $names = [];
    foreach ($roles as $role) {
        $names[$role->getRoleName()] = (int) $role->getId();
    }

    return $names;
}

function userRow(\Magento\User\Model\User $user): array
{
    $role = $user->getRole();

    return [
        'id' => (string) $user->getId(),
        'username' => (string) $user->getUserName(),
        'email' => (string) $user->getEmail(),
        'role' => $role !== null ? (string) $role->getRoleName() : '',
    ];
}

function loadUser(\Magento\Framework\ObjectManagerInterface $om, string $id): \Magento\User\Model\User
{
    $user = $om->create(\Magento\User\Model\User::class)->load((int) $id);
    if (!$user->getId()) {
        fail("no admin user with id {$id}");
    }

    return $user;
}

try {
    switch ($command) {
        case 'roles:list':
            emit(array_keys(roleNames($objectManager)));

            // no break — emit() exits
        case 'users:list':
            $collection = $objectManager
                ->create(\Magento\User\Model\ResourceModel\User\CollectionFactory::class)
                ->create();
            emit(array_map(userRow(...), array_values(iterator_to_array($collection))));

        case 'users:id':
            // Internal: app.sh asks for the id of the account setup:install
            // just created, because the installer prints a name and the
            // engine's install contract answers with an id.
            $collection = $objectManager
                ->create(\Magento\User\Model\ResourceModel\User\CollectionFactory::class)
                ->create()
                ->addFieldToFilter('username', $argv[2] ?? fail('users:id needs a username'));
            $user = $collection->getFirstItem();
            if (!$user->getId()) {
                fail('no admin user named ' . ($argv[2] ?? ''));
            }
            emit(['id' => (string) $user->getId()]);

        case 'users:add':
            [$login, $email, $password, $role] = [
                $argv[2] ?? fail('users:add needs a login'),
                $argv[3] ?? fail('users:add needs an email'),
                $argv[4] ?? fail('users:add needs a password'),
                $argv[5] ?? PANELALPHA_ADMIN_ROLE,
            ];
            $roles = roleNames($objectManager);
            if (!isset($roles[$role])) {
                fail("no such role: {$role}. Magento has: " . implode(', ', array_keys($roles)));
            }
            $user = $objectManager->create(\Magento\User\Model\User::class);
            $user->setData([
                'username' => $login,
                'firstname' => 'Store',
                'lastname' => 'User',
                'email' => $email,
                'password' => $password,
                'interface_locale' => 'en_US',
                'is_active' => 1,
            ])->save();
            // The role is a second write: Magento's User::save() does not
            // read role_id off the data it was given.
            $user->setRoleId($roles[$role])->save();
            emit(['id' => (string) $user->getId()]);

        case 'users:delete':
            $user = loadUser($objectManager, $argv[2] ?? fail('users:delete needs a user id'));
            $user->delete();
            emit(['success' => true]);

        case 'users:reset-password':
            $user = loadUser($objectManager, $argv[2] ?? fail('users:reset-password needs a user id'));
            $user->setPassword($argv[3] ?? fail('users:reset-password needs a password'))->save();
            emit(['success' => true]);

        default:
            fail("unknown command: {$command}");
    }
} catch (\Magento\Framework\Validator\Exception $e) {
    // Magento's own validation, which is the useful message here: password
    // length, an email already taken, a username that exists.
    fail(implode('; ', array_map('strval', $e->getMessages() ?: [$e->getMessage()])));
} catch (\Throwable $e) {
    fail($e->getMessage());
}

<?php

namespace App\System\Services;

use App\Models\User as ModelsUser;
use App\System as EngineSystem;
use Illuminate\Support\Facades\Log;

/**
 * Host SFTP collaborator — logins.conf and sync into the sftp container.
 */
class Sftp
{
    public function __construct(
        private EngineSystem $system,
    ) {
    }

    public function loginsConfPath(): string
    {
        return $this->system->engineDirPath() . '/config/sftp/logins.conf';
    }

    public function writeLogins(string $config): void
    {
        $this->system->filesystem()->filePutContents($this->loginsConfPath(), $config);
    }

    public function syncLogins(): void
    {
        $this->system->runProcess([
            'sudo',
            'docker',
            'compose',
            '-f',
            $this->system->composeFilePath(),
            'exec',
            'sftp',
            'bash',
            '/etc/sftp/sync-logins.sh',
        ]);
    }

    public function applyLogins(string $config): void
    {
        $this->writeLogins($config);
        $this->syncLogins();
    }

    public function rebuildSftpAccounts(): void
    {
        $config = '';
        $users = ModelsUser::with('sftpAccounts')
            ->whereHas('sftpAccounts')
            ->get();

        foreach ($users as $user) {
            /** @var ModelsUser $user */
            try {
                $osUser = $user->project()->syncLinuxUser();
                $home = $user->project()->homeDirPath();
                $accs = $user->getSftpAccounts();
                foreach ($accs as $acc) {
                    $auth = $acc->getAuthMethods();
                    $username = $acc->username;
                    $password = in_array('password', $auth, true) ? $acc->password : '';
                    $pubKey = in_array('public_key', $auth, true) ? $acc->public_key : '';
                    $config .= "{$username}:{$password}:{$osUser['UID']}:{$osUser['GID']}:{$home}:{$pubKey}\n";
                }
            } catch (\Exception $e) {
                Log::warning('Could not rebuild SFTP accounts for user ' . $user->username, [
                    'exception' => $e,
                ]);
            }
        }

        $this->applyLogins($config);
    }
}

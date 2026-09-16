<?php

namespace App\System\Project\Dind;

use App\Lib\Deploy\Platform\AppConfig\AppConfig;
use App\System\Project\Dind as DindProject;
use Illuminate\Support\Str;

/**
 * App-level management (users, roles, install, info) for a deployed DinD project.
 *
 * Delegates to overrides/app.sh via the app config CLI contract.
 */
class AppManager
{
    public function __construct(
        private DindProject $dind,
    ) {
    }

    /**
     * @return array<int, array{id: string, username: string, email: string, role: string}>
     */
    public function listUsers(): array
    {
        return json_decode($this->run('users:list'), true) ?? [];
    }

    /**
     * @return array{id: string}
     */
    public function addUser(string $login, string $email, string $password, string $role): array
    {
        return json_decode($this->run('users:add', $login, $email, $password, $role), true) ?? [];
    }

    public function deleteUser(string $userId): void
    {
        $this->run('users:delete', $userId);
    }

    public function resetUserPassword(string $userId, string $password): void
    {
        $this->run('users:reset-password', $userId, $password);
    }

    /**
     * @return array<string, mixed>
     */
    public function ssoCredentials(string $userId): array
    {
        return json_decode($this->run('users:sso', $userId), true) ?? [];
    }

    public function install(
        string $url,
        string $title,
        string $adminUser,
        string $adminEmail,
        string $adminPassword
    ): void {
        $this->run('install', $url, $title, $adminUser, $adminEmail, $adminPassword);
    }

    /**
     * @return string[]
     */
    public function info(): array
    {
        return json_decode($this->run('info'), true) ?? [];
    }

    /**
     * @return string[]
     */
    public function listRoles(): array
    {
        return json_decode($this->run('roles:list'), true) ?? [];
    }

    private function scriptPath(): string
    {
        return $this->dind->userAppDirPath() . '/' . AppConfig::APP_SCRIPT;
    }

    private function run(string ...$args): string
    {
        $scriptPath = $this->scriptPath();
        $shell = $this->dind->shell();

        $process = $shell->runProcessAsUser(['bash', $scriptPath, ...$args]);

        if (
            $process->getExitCode() !== 0
            && Str::contains($process->getErrorOutput(), 'no such file', true)
        ) {
            $this->installScript($scriptPath);
            $process = $shell->runProcessAsUser(['bash', $scriptPath, ...$args]);
        }

        if (
            $process->getExitCode() !== 0
            && Str::contains($process->getErrorOutput(), 'MISSING_SNIPPET', true)
        ) {
            $this->dind->installFileSnippets();
            $process = $shell->runProcessAsUser(['bash', $scriptPath, ...$args]);
        }

        if ($process->getExitCode() !== 0) {
            $message = $process->getErrorOutput() ?: $process->getOutput();
            $decoded = json_decode($message, true);
            if (is_array($decoded) && !empty($decoded['error'])) {
                throw new \Exception($decoded['error']);
            }
            throw new \Exception($message);
        }

        return $process->getOutput();
    }

    private function installScript(string $scriptPath): void
    {
        $script = $this->dind
            ->appConfig($this->dind->userModel()->getGitRepo())
            ?->appScript();
        if ($script === null) {
            throw new \Exception('App management is not supported for this application');
        }
        $this->dind->system()->filesystem()->filePutContents(
            $scriptPath,
            $script,
            $this->dind->userModel()->getChownString(),
            '700'
        );
    }
}

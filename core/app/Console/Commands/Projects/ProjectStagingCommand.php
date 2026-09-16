<?php

namespace App\Console\Commands\Projects;

use App\Http\Requests\ProjectStagingRequest;
use App\System;
use App\Lib\Helper;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectStagingCommand extends Command
{
    protected $aliases = ['users:staging'];

    protected $signature = 'project:staging {project : Live project username} {--new-username=} {--domain=}';

    protected $description = 'Create a linked staging mirror of a live dind project (synchronous)';

    public function handle(): int
    {
        $username = (string) $this->argument('project');
        $source = User::findByUsername($username);
        if (!$source) {
            $this->error("No such user: '{$username}'");
            return 1;
        }

        $dest = null;

        try {
            $system = new System();

            $newUsername = $this->option('new-username');
            $newUsername = is_string($newUsername) && $newUsername !== '' ? strtolower($newUsername) : null;
            if ($newUsername === null) {
                $newUsername = Helper::generateCloneUsername($source->username);
                if ($newUsername === null) {
                    $this->error('Could not generate an available username for the staging project.');
                    return 1;
                }
            }

            if (User::existsByUsername($newUsername)) {
                $this->error('User already exists.');
                return 1;
            }
            if (!$system->isUsernameAvailable($newUsername)) {
                $this->error('Username not available.');
                return 1;
            }

            $destDomain = $this->option('domain');
            $destDomain = is_string($destDomain) && $destDomain !== '' ? strtolower($destDomain) : null;
            if ($destDomain === null) {
                $destDomain = Helper::generateCloneDomain($source->domain);
                if ($destDomain === null) {
                    $this->error('Could not generate an available staging domain.');
                    return 1;
                }
            } elseif (Str::startsWith($destDomain, 'www.')) {
                $destDomain = Str::after($destDomain, 'www.');
            }

            if (Domain::domainOrAliasExists($destDomain)) {
                $this->error('Domain already exists.');
                return 1;
            }

            $probe = User::make([
                'username' => $newUsername,
                'domain'   => $destDomain,
                'email'    => $source->email,
            ]);
            if ($probe->project()->hostingExists()) {
                $this->error('Username not available.');
                return 1;
            }

            ProjectStagingRequest::assertCanCreate($source);
            $dest = $source->makePendingStaging($newUsername, $destDomain, 'artisan');
            $system->projects()->copy($source->username, $dest->username);

            $this->info($dest->username . ' ' . $dest->domain);

            return 0;
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->first() ?? $e->getMessage());
            $this->deleteDestIfExists($dest);

            return 1;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->deleteDestIfExists($dest);

            return 1;
        }
    }

    private function deleteDestIfExists(?User $dest): void
    {
        if ($dest === null) {
            return;
        }

        $existing = User::findByUsername($dest->username);
        if ($existing === null) {
            return;
        }

        try {
            $existing->project()->destroy();
        } catch (\Throwable $cleanup) {
            Log::error('project:staging cleanup failed', [
                'dest'      => $dest->username,
                'exception' => $cleanup,
            ]);
        }
    }
}

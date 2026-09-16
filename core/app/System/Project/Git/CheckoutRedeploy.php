<?php

namespace App\System\Project\Git;

use App\System\Project as ProjectAggregate;
use App\System\Project\Dind;
use App\System\Project\Git as ProjectGit;

/**
 * After a mutating git porcelain call on a deploy-managed checkout, rebuild
 * the running app from the files already in ~/project.
 *
 * Must not live in {@see ProjectGit}: that class talks to git, not to DinD.
 * Must not call a wipe-and-reclone rebuild: that path would undo pull/revert.
 */
class CheckoutRedeploy
{
    public function afterMutation(ProjectGit $git, ProjectAggregate $project): void
    {
        if (!$git->isDeployManaged()) {
            return;
        }

        $this->rebuild($project);
    }

    protected function rebuild(ProjectAggregate $project): void
    {
        $runtime = $project->runtime();
        if (!$runtime instanceof Dind) {
            return;
        }

        $runtime->deployment()->rebuildFromCheckout();
        $project->system()->webserver()->rebuildDomains();

        $user = $project->model();
        if ($user->getDeploymentStatus() === 'success') {
            return;
        }
        $user->setDetails(['deployment_status' => 'success']);
        if ($user->exists) {
            $user->save();
        }
    }
}

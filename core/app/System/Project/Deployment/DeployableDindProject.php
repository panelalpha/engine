<?php

namespace App\System\Project\Deployment;

use App\Models\User as ModelsUser;

/**
 * Minimal DinD Project surface required for Deploy orchestration.
 */
interface DeployableDindProject
{
    public function userModel(): ModelsUser;
}

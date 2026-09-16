<?php

namespace App\System\Project\Dind;

use App\System\Project\Dind as DindProject;

/**
 * Outer account-container compose up/down/teardown on the host.
 */
final class OuterLifecycle
{
    public function __construct(
        private DindProject $project,
    ) {
    }

    public function up(): void
    {
        $path = $this->project->composeFilePath();
        $this->project->system()->exec([
            'sudo',
            'docker',
            'compose',
            '-f',
            $path,
            'up',
            '-d',
            '--remove-orphans',
        ]);
    }

    public function down(): void
    {
        $path = $this->project->composeFilePath();
        $this->project->system()->exec([
            'sudo',
            'docker',
            'compose',
            '-f',
            $path,
            'down',
        ]);
    }

    public function tearDown(): void
    {
        if (!$this->project->exists()) {
            throw new \Exception(
                "Project of the user '{$this->project->username()}' doesn't exist"
            );
        }

        $this->deleteOuterStack();
    }

    /**
     * Outer compose down (when present) and force-remove the named DinD container.
     * Safe when compose was never written or was already removed.
     */
    public function deleteOuterStack(): void
    {
        if ($this->project->exists()) {
            $path = $this->project->composeFilePath();
            $this->project->system()->runProcess([
                'sudo',
                'docker',
                'compose',
                '-f',
                $path,
                'down',
                '-v',
                '--remove-orphans',
            ]);
        }

        $this->project->system()->runProcess([
            'sudo',
            'docker',
            'rm',
            '-f',
            $this->project->username(),
        ]);
    }
}

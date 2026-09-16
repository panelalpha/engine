<?php

namespace App\System\Project\Dind\Source;

use App\System\Project\Dind as DindProject;

/**
 * ~/project tree helpers shared by git and archive ingest.
 */
final class ProjectTree
{
    public function __construct(
        private DindProject $project,
    ) {
    }

    public function appDirPath(): string
    {
        return $this->project->homeDirPath() . '/project';
    }

    /**
     * Clear ~/project without removing the directory itself.
     */
    public function clearContents(string $projectDir): void
    {
        if (preg_match('#^/home/[a-zA-Z0-9_.-]+/project$#', $projectDir) !== 1) {
            throw new \InvalidArgumentException('Refusing to clear a path outside ~/project.');
        }

        $this->project->system()->exec([
            'sudo', 'find', $projectDir,
            '-mindepth', '1', '-maxdepth', '1',
            '-exec', 'rm', '-rf', '{}', '+',
        ], [], 120);
    }
}

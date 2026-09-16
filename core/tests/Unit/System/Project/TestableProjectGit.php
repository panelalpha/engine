<?php

namespace Tests\Unit\System\Project;

use App\System\Project\Git\Exception as GitException;
use App\Lib\Deploy\Source\GitUrl;
use App\System\Project;
use App\System\Project\Git as ProjectGit;
use Tests\Unit\System\Project\FakeGitRunner;

/**
 * Project Git with injectable execute() for unit tests (no DinD / host sudo).
 *
 * @internal
 */
final class TestableProjectGit extends ProjectGit
{
    /**
     * @param (callable(list<string>, ?string, int): string)|FakeGitRunner $execute
     */
    public function __construct(
        Project $project,
        ?string $path,
        private $execute,
        ?string $absolutePathOverride = null,
    ) {
        parent::__construct($project, $path ?? 'public_html');
        if ($absolutePathOverride !== null) {
            $this->absolutePath = $absolutePathOverride;
            $this->pathKey = '';
        }
    }

    /**
     * @param list<string> $gitCommand
     */
    protected function execute(array $gitCommand, ?string $token = null, int $timeout = 600): string
    {
        try {
            if ($this->execute instanceof FakeGitRunner) {
                return $this->execute->run($gitCommand, $token, $timeout);
            }

            return ($this->execute)($gitCommand, $token, $timeout);
        } catch (GitException $e) {
            throw $e;
        } catch (\InvalidArgumentException $e) {
            throw new GitException($e->getMessage(), 422);
        } catch (\Throwable $e) {
            throw new GitException(GitUrl::sanitize($e->getMessage()), 400);
        }
    }

    protected function ensureWorkTreeDirectory(): void
    {
    }

    protected function removeCreatedGitDir(): void
    {
    }
}

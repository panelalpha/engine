<?php

namespace Tests\Unit\System\Project;

use App\System\Project\Dind;
use App\System\Project\Dind\Source\GitRepository;
use App\System\Project\Git\Exception as GitException;

/**
 * @internal
 */
final class TestableGitRepository extends GitRepository
{
    /**
     * @param (callable(list<string>, ?string, int): string)|FakeGitRunner $execute
     */
    public function __construct(
        Dind $project,
        private $execute,
    ) {
        parent::__construct($project);
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
            throw new GitException($e->getMessage(), 400);
        }
    }

    protected function ensureWorkTreeDirectory(): void
    {
    }

    protected function removeCreatedGitDir(): void
    {
    }
}

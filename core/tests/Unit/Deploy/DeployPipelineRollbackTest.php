<?php

namespace Tests\Unit\Deploy;

use App\Exceptions\ProblemException;
use App\Http\Controllers\UserController;
use App\System\Project as SystemProject;
use App\Lib\Deploy\DeployLog\DeployLogger;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\Unit\Task\SqliteTaskTestCase;

/**
 * The before-rollback hook on a failed create: it sees the deploy log while it
 * still exists, and a failing hook never replaces the deploy's own error.
 */
class DeployPipelineRollbackTest extends SqliteTaskTestCase
{
    private string $username;

    protected function setUp(): void
    {
        parent::setUp();

        // No row: the rollback's account lookup finds nothing to delete.
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->json('details')->nullable();
            $table->timestamps();
        });
        $this->username = 'rollback-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        gc_collect_cycles();
        DeployLogger::deleteUserLogs($this->username);
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    private function failingUser(): User
    {
        $project = Mockery::mock(SystemProject::class);
        $project->shouldReceive('createDirectories')
            ->andThrow(new \Exception('process "/bin/sh -c npm run build" did not complete successfully: exit code: 1'));

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('getMainDomain')->andReturn(Mockery::mock(Domain::class));
        $user->shouldReceive('project')->andReturn($project);
        $user->shouldReceive('getTemplate')->andReturn('default');
        $user->shouldReceive('hasGitProject')->andReturn(false);
        $project->shouldReceive('runtime')->andReturn(Mockery::mock(\App\System\Project\PhpHosting::class));
        $user->shouldReceive('usedCustomEnvVars')->andReturn(false);
        $user->username = $this->username;

        return $user;
    }

    public function test_hook_reads_the_deploy_log_before_the_rollback(): void
    {
        $logger = DeployLogger::start($this->username);
        $seen = null;

        try {
            (new UserController())->runDeployPipeline(
                $this->failingUser(),
                $logger,
                function (DeployLogger $failed) use (&$seen): void {
                    $seen = array_column($failed->tail(150), 'msg');
                },
            );
            $this->fail('Expected the failed deploy to throw');
        } catch (ProblemException $e) {
            $this->assertStringContainsString('A build step failed (exit code 1)', $e->getMessage());
        }

        $this->assertIsArray($seen);
        $this->assertStringStartsWith('Deploy failed: A build step failed', (string) end($seen));
    }

    public function test_failing_hook_does_not_mask_the_deploy_error(): void
    {
        $logger = DeployLogger::start($this->username);

        try {
            (new UserController())->runDeployPipeline(
                $this->failingUser(),
                $logger,
                static function (): void {
                    throw new RuntimeException('tail copy exploded');
                },
            );
            $this->fail('Expected the failed deploy to throw');
        } catch (ProblemException $e) {
            $this->assertStringContainsString('A build step failed (exit code 1)', $e->getMessage());
            $this->assertStringNotContainsString('tail copy exploded', $e->getMessage());
        }
    }
}

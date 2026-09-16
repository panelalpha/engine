<?php

namespace Tests\Unit;

use App\Exceptions\ProblemException;
use App\Http\Controllers\UserController;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

/**
 * deployArchive()'s pipeline closure used to re-throw a bare \Exception on
 * any failure that wasn't already an \InvalidArgumentException -- with no
 * try/catch around the synchronous $run() call, that propagated as a raw,
 * message-less 500 instead of the ProblemException (a 422 the rest of the
 * deploy pipeline already uses) runDeployPipeline()'s identical catch
 * produces. This exercises the same deployProblem() helper directly.
 */
class DeployArchiveExceptionMappingTest extends TestCase
{
    public function test_deploy_problem_is_a_validation_exception_not_a_raw_500(): void
    {
        $method = new ReflectionMethod(UserController::class, 'deployProblem');
        $problem = $method->invoke(null, 'deploy_failed', 'compose stop failed: no such file or directory', 'running');

        $this->assertInstanceOf(ProblemException::class, $problem);
        $this->assertInstanceOf(ValidationException::class, $problem);
        $this->assertSame(422, $problem->status);
        $this->assertSame('deploy_failed', $problem->problems[0]['code']);
        $this->assertSame('running', $problem->problems[0]['stage']);
        $this->assertSame('compose stop failed: no such file or directory', $problem->problems[0]['message']);
    }
}

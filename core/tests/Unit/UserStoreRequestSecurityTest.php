<?php

namespace Tests\Unit;

use App\Http\Requests\UserStoreRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserStoreRequestSecurityTest extends TestCase
{
    #[DataProvider('unsafeRepositoryProvider')]
    public function test_git_token_rejects_unsafe_repository_url(string $repo): void
    {
        $validator = $this->validator([
            'git_repo' => $repo,
            'git_token' => 'secret',
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('git_token', $validator->errors()->toArray());
    }

    public static function unsafeRepositoryProvider(): array
    {
        return [
            'HTTP' => ['http://git.example.com/org/repo.git'],
            'embedded credentials' => ['https://user:password@git.example.com/org/repo.git'],
        ];
    }

    public function test_git_token_accepts_clean_https_repository_url(): void
    {
        $validator = $this->validator([
            'git_repo' => 'https://git.example.com/org/repo.git',
            'git_token' => 'secret',
        ]);

        $this->assertFalse($validator->fails(), (string) $validator->errors());
    }

    public function test_git_token_requires_repository_url(): void
    {
        $validator = $this->validator(['git_token' => 'secret']);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('git_token', $validator->errors()->toArray());
    }

    private function validator(array $payload): \Illuminate\Contracts\Validation\Validator
    {
        $request = UserStoreRequest::create('/api/users', 'POST', $payload);
        $request->setContainer($this->app);

        return Validator::make($payload, $request->rules());
    }

    // ---- `name` is an alias for `username` ------------------------------

    /**
     * Every other client calls the field `name`. The MCP tool maps it to
     * `username` on the way in, so only a direct REST caller was affected --
     * and affected silently: the request validated, and the engine derived a
     * username from the git repository instead.
     *
     * suroi's test sent `name: suroi2dd4`; the engine created `suroi`, which
     * then collided with an account already of that name and failed the deploy
     * from inside a stage, reported as `code: deploy_failed` on a request that
     * was in fact misread.
     */
    public function test_name_is_accepted_as_the_username(): void
    {
        $request = UserStoreRequest::create('/api/users', 'POST', [
            'name' => 'shop4a2f',
            'email' => 'ops@example.com',
        ]);
        $request->setContainer($this->app);
        $request->validateResolved();

        $this->assertSame('shop4a2f', $request->validated()['username']);
    }

    /** `username` still wins, and still works on its own. */
    public function test_username_still_works_and_wins_over_name(): void
    {
        $only = UserStoreRequest::create('/api/users', 'POST', [
            'username' => 'shop4a2f',
            'email' => 'ops@example.com',
        ]);
        $only->setContainer($this->app);
        $only->validateResolved();
        $this->assertSame('shop4a2f', $only->validated()['username']);

        $both = UserStoreRequest::create('/api/users', 'POST', [
            'username' => 'firstname',
            'name' => 'secondname',
            'email' => 'ops@example.com',
        ]);
        $both->setContainer($this->app);
        $both->validateResolved();
        $this->assertSame('firstname', $both->validated()['username']);
    }

    /**
     * The alias is validated like the field it aliases, not waved through.
     *
     * Asserted against the *merged* input rather than through
     * `validateResolved()`, because a failing validation there goes to the
     * exception handler and needs a URL generator this unit test has no
     * reason to build. What matters is that the aliased value ends up under
     * `username` and is then judged by `username`'s own rules.
     */
    public function test_the_aliased_name_is_held_to_the_username_rules(): void
    {
        foreach (['has space', 'has_underscore', str_repeat('a', 16)] as $bad) {
            $request = UserStoreRequest::create('/api/users', 'POST', ['name' => $bad]);
            $request->setContainer($this->app);

            $prepare = new \ReflectionMethod($request, 'prepareForValidation');
            $prepare->setAccessible(true);
            $prepare->invoke($request);

            $validator = Validator::make($request->all(), $request->rules());

            $this->assertTrue($validator->fails(), $bad);
            $this->assertArrayHasKey('username', $validator->errors()->toArray(), $bad);
        }
    }

    /** A name that is already a valid username passes. */
    public function test_a_valid_name_is_accepted_by_the_username_rules(): void
    {
        $request = UserStoreRequest::create('/api/users', 'POST', ['name' => 'shop4a2f']);
        $request->setContainer($this->app);

        $prepare = new \ReflectionMethod($request, 'prepareForValidation');
        $prepare->setAccessible(true);
        $prepare->invoke($request);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertFalse($validator->fails(), (string) $validator->errors());
        $this->assertSame('shop4a2f', $request->input('username'));
    }
}

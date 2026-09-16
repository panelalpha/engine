<?php

namespace Tests\Unit\Deploy\Env;

use App\Lib\Deploy\Env\ComposeEnvFiles;
use PHPUnit\Framework\TestCase;

/**
 * Which env files a compose stack expects to find beside it.
 *
 * Compose refuses to start a service whose `env_file` is missing, and the
 * repositories that declare one almost never commit it - so the engine has to
 * know which ones to create. The paths come from a customer's own file and go
 * on to be written to, which is why anything that could point outside the
 * project is dropped rather than resolved.
 */
class ComposeEnvFilesTest extends TestCase
{
    public function test_a_declared_env_file_is_found(): void
    {
        $this->assertSame(['.env'], ComposeEnvFiles::declaredIn(<<<'YAML'
        services:
          app:
            image: acme/app
            env_file: .env
        YAML));
    }

    public function test_the_list_form_is_read(): void
    {
        $this->assertSame(['.env', '.env.production'], ComposeEnvFiles::declaredIn(<<<'YAML'
        services:
          app:
            image: acme/app
            env_file:
              - .env
              - .env.production
        YAML));
    }

    public function test_the_long_form_is_read(): void
    {
        // Compose 2.24 added `- path: ... required: false`.
        $this->assertSame(['.env'], ComposeEnvFiles::declaredIn(<<<'YAML'
        services:
          app:
            image: acme/app
            env_file:
              - path: .env
                required: false
        YAML));
    }

    public function test_a_leading_dot_slash_is_normalised_away(): void
    {
        // `./env/app.env` and `env/app.env` are the same file, and creating
        // it twice under two names would leave one of them stale.
        $this->assertSame(['env/app.env'], ComposeEnvFiles::declaredIn(<<<'YAML'
        services:
          app:
            image: acme/app
            env_file: ./env/app.env
        YAML));
    }

    public function test_one_file_shared_by_two_services_is_listed_once(): void
    {
        $this->assertSame(['.env'], ComposeEnvFiles::declaredIn(<<<'YAML'
        services:
          web:
            image: acme/app
            env_file: .env
          worker:
            image: acme/app
            env_file: .env
        YAML));
    }

    public function test_a_path_that_leaves_the_project_is_dropped(): void
    {
        // The engine creates these files. One resolving outside the checkout
        // would have it writing into the account's home, or worse.
        $this->assertSame([], ComposeEnvFiles::declaredIn(<<<'YAML'
        services:
          app:
            image: acme/app
            env_file:
              - ../secrets/.env
              - /etc/app/.env
              - config/../../.env
        YAML));
    }

    public function test_a_path_the_engine_cannot_resolve_is_dropped(): void
    {
        // `${ENV_DIR}/.env` depends on a variable nothing sets here;
        // creating a file literally named that would help no one.
        $this->assertSame([], ComposeEnvFiles::declaredIn(<<<'YAML'
        services:
          app:
            image: acme/app
            env_file: ${ENV_DIR}/.env
        YAML));
    }

    public function test_a_stack_declaring_no_env_files_needs_none(): void
    {
        $this->assertSame([], ComposeEnvFiles::declaredIn("services:\n  app:\n    image: acme/app\n"));
    }

    public function test_a_file_with_no_services_needs_none(): void
    {
        $this->assertSame([], ComposeEnvFiles::declaredIn("volumes:\n  dbdata:\n"));
        $this->assertSame([], ComposeEnvFiles::declaredIn(''));
    }

    public function test_unparseable_yaml_needs_none_rather_than_throwing(): void
    {
        $this->assertSame([], ComposeEnvFiles::declaredIn("services:\n  app:\n   - [\n"));
    }

    public function test_an_already_parsed_file_can_be_read_directly(): void
    {
        $this->assertSame(
            ['.env'],
            ComposeEnvFiles::pathsIn(['services' => ['app' => ['env_file' => '.env']]])
        );
    }

    /**
     * `env_file` is written four ways in the wild and only some were readable:
     * the literal `.env` (which the engine already creates), a list, a
     * `${VAR:-default}` whose default *is* the file compose will read, and a
     * bare `${VAR}` whose value is the caller's to choose.
     *
     * The third was refused along with the fourth, so ideon --
     * `env_file: ${ENV_FILE:-.env}` -- got no `.env` and compose failed on
     * `env file ... not found`, while the same thing spelled literally got one.
     */
    public function test_an_env_file_default_is_the_file_compose_will_read(): void
    {
        $this->assertSame(['.env'], ComposeEnvFiles::declaredIn(<<<'YAML'
        services:
          app:
            env_file:
              - ${ENV_FILE:-.env}
        YAML));

        $this->assertSame(['.env.local'], ComposeEnvFiles::declaredIn(<<<'YAML'
        services:
          app:
            env_file: ${ENV_FILE-.env.local}
        YAML));
    }

    /**
     * A bare `${VAR}` has no answer of ours. Inventing one of several possible
     * files would leave the one compose reads still missing -- the bug rather
     * than the fix.
     */
    public function test_an_env_file_without_a_default_is_still_refused(): void
    {
        $this->assertSame([], ComposeEnvFiles::declaredIn(<<<'YAML'
        services:
          app:
            env_file:
              - ${ENV_FILE}
        YAML));

        $this->assertSame([], ComposeEnvFiles::declaredIn(<<<'YAML'
        services:
          app:
            env_file:
              - ${A:-a.env}${B:-b.env}
        YAML));
    }

    /**
     * The default is substituted *before* the path guards, so one that is
     * absolute or escapes the project is refused exactly as the literal
     * spelling of it would be: a value moved into an interpolation cannot
     * reach outside ~/project.
     */
    public function test_a_default_is_still_subject_to_the_path_guards(): void
    {
        $this->assertSame([], ComposeEnvFiles::declaredIn(<<<'YAML'
        services:
          app:
            env_file:
              - ${ENV_FILE:-/etc/passwd}
        YAML));

        $this->assertSame([], ComposeEnvFiles::declaredIn(<<<'YAML'
        services:
          app:
            env_file:
              - ${ENV_FILE:-../outside.env}
        YAML));
    }
}

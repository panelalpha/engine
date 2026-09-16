<?php

namespace Tests\Unit\Deploy\Platform\Dockerfile;

use App\Lib\Deploy\Platform\Dockerfile\EntrypointInstall;
use App\Lib\Deploy\Platform\Dockerfile\EnvironmentLines;
use App\Lib\Deploy\Platform\PlatformStage;
use App\Lib\Deploy\Platform\StageScript;
use Tests\TestCase;

/**
 * The two smallest pieces every generated Dockerfile is assembled from.
 *
 * EnvironmentLines preserves the caller's ordering, which is how a recipe's
 * own value ends up overriding a generator default; EntrypointInstall is
 * ENTRYPOINT rather than CMD, which is what lets the same image serve an
 * install-once first deploy and a plain restart.
 */
class EnvironmentLinesTest extends TestCase
{
    public function test_each_entry_becomes_an_env_line(): void
    {
        $this->assertSame(
            ['ENV NODE_ENV=production', 'ENV PORT=3000'],
            EnvironmentLines::of(['NODE_ENV' => 'production', 'PORT' => '3000'])
        );
    }

    public function test_the_callers_order_is_preserved(): void
    {
        // Insertion order, not sorted. The generators build their map by
        // merging the recipe's own env over their defaults, and a Dockerfile
        // takes the last ENV for a key - so reordering here would silently
        // flip which of the two wins.
        $env = array_merge(['HOST' => '0.0.0.0', 'PORT' => '3000'], ['HOST' => '::']);

        $this->assertSame(['ENV HOST=::', 'ENV PORT=3000'], EnvironmentLines::of($env));
    }

    public function test_a_value_is_written_exactly_as_given(): void
    {
        // Including one with spaces: the generators pass through what a
        // manifest wrote, and quoting it here would change its value.
        $this->assertSame(
            ['ENV JAVA_OPTS=-Xmx256m -XX:+UseSerialGC'],
            EnvironmentLines::of(['JAVA_OPTS' => '-Xmx256m -XX:+UseSerialGC'])
        );
    }

    public function test_nothing_to_set_produces_no_lines(): void
    {
        $this->assertSame([], EnvironmentLines::of([]));
    }

    public function test_a_build_arg_is_written_for_the_build_and_not_the_image(): void
    {
        // `ARG` reaches every process a `RUN` spawns and is absent from the
        // resulting image's config, which is what a value sized for the build
        // container -- not the account -- has to be.
        $this->assertSame(
            ['ARG NODE_OPTIONS=--max-old-space-size=1433'],
            EnvironmentLines::buildArgs(['NODE_OPTIONS' => '--max-old-space-size=1433'])
        );
    }

    public function test_nothing_to_default_produces_no_build_args(): void
    {
        $this->assertSame([], EnvironmentLines::buildArgs([]));
    }

    public function test_the_entrypoint_is_installed_and_made_executable(): void
    {
        $lines = EntrypointInstall::lines();

        $this->assertStringContainsString('COPY ' . StageScript::FILENAME, $lines);
        $this->assertStringContainsString('RUN chmod +x /' . StageScript::FILENAME, $lines);
    }

    public function test_the_script_is_the_entrypoint_and_not_a_command(): void
    {
        // CMD could only say "run this every time"; the entrypoint branches on
        // the deploy phase, so install-once work does not repeat on a restart.
        // And ENTRYPOINT lets the script's final `exec` make the server PID 1,
        // so `docker stop` reaches it instead of burning the kill timer.
        $lines = EntrypointInstall::lines();

        $this->assertStringContainsString('ENTRYPOINT ["/' . StageScript::FILENAME . '"]', $lines);
        $this->assertStringNotContainsString('CMD', $lines);
    }

    /**
     * The deploy phase never reaches the image.
     *
     * It flips from `install` to `upgrade` on the second deploy of every
     * project, and as an `ENV` above the dependency install it invalidated
     * that layer and everything below it — a Django rebuild of an unchanged
     * commit re-ran `pip install` and cached 1 layer of 10. Compose passes it
     * at runtime, which is where a runtime value belongs.
     */
    public function test_the_deploy_phase_is_not_baked_into_the_image(): void
    {
        $lines = EnvironmentLines::of([
            'HOST' => '0.0.0.0',
            PlatformStage::PHASE_ENV => 'upgrade',
            'PORT' => '8000',
        ]);

        $this->assertSame(['ENV HOST=0.0.0.0', 'ENV PORT=8000'], $lines);
    }
}

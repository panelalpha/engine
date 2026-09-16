<?php

namespace App\Lib\Deploy\Platform\Runtime\Ruby;

use App\Lib\Deploy\Platform\Runtime\NodeRuntime;
use App\Lib\Deploy\Platform\Runtime\Images;
use App\Lib\Deploy\Platform\Runtime\JsPackageManager;
use App\Lib\Deploy\Template\Template;

/**
 * The Node stage that compiles a Rails app's frontend.
 *
 * The package.json build script runs directly rather than through
 * `rails assets:precompile` or vite_ruby's `bin/vite`: both boot the full
 * application, and the application blocks on a database that does not exist
 * during an image build.
 *
 * Install with NODE_ENV unset so the build tooling in devDependencies is
 * present; production only for the build step itself.
 */
final class FrontendStage
{
    private const PNPM_INSTALL = 'pnpm install';

    private const IGNORE_SCRIPTS = ' --ignore-scripts';

    /** @var array<string, mixed> */
    private readonly array $package;

    private readonly string $packageManager;

    public function __construct(private readonly RubyApp $app)
    {
        $this->package = $app->project->package() ?? [];
        $this->packageManager = JsPackageManager::detectPackageManager($app->project->files, $this->package);
    }

    public static function render(RubyApp $app): string
    {
        return (new self($app))->build();
    }

    public function build(): string
    {
        if ($this->app->project->script('build') === '') {
            return '';
        }

        return Template::named('dockerfile/ruby-assets')->render([
            'node_image' => NodeRuntime::defaultImage(),
            'install_command' => $this->installCommand(),
            'build_command' => JsPackageManager::scriptCommand($this->packageManager, 'build'),
        ]);
    }

    /**
     * pnpm runs a project's own postinstall scripts, which for a Rails app
     * often shell out to `bundle` — absent from the Node stage.
     */
    private function installCommand(): string
    {
        $install = JsPackageManager::installCommand(
            $this->packageManager,
            $this->app->project->files,
            $this->package,
            $this->app->project->projectDir
        );

        return str_contains($install, self::PNPM_INSTALL) ? $install . self::IGNORE_SCRIPTS : $install;
    }
}

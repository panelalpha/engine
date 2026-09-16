<?php

namespace Tests\Unit\Deploy\Platform\Runtime\Php;

use App\Lib\Deploy\Platform\Runtime\Php\PhpHostBuild;
use PHPUnit\Framework\TestCase;

/**
 * `--no-plugins` is dropped only when the project's own lock pins installer
 * plugins and nothing else.
 *
 * Composer plugins are arbitrary PHP out of the customer's repository, and the
 * host install runs outside the account's sandbox, so the engine refuses them
 * by default. That default broke a whole class of frameworks: for Drupal the
 * plugin *is* the installer. `drupal/recommended-project` scaffolds `web/`,
 * `web/core` and `web/index.php` through `composer/installers`, so with
 * plugins disabled the install reported "46 packages installed", produced only
 * `vendor/`, and the deploy ended on "there is no index.php anywhere in
 * ~/project" while Composer had claimed success.
 *
 * Verified on the host against drupal/recommended-project, same manifest and
 * same lock, differing only in the flag:
 *
 *   --no-plugins   -> web/index.php: no   web/core: no   (composer.json lock vendor)
 *   plugins allowed-> web/index.php: YES  web/core: YES  (+ recipes web)
 *
 * The permission is bounded by the lock, not granted by a manifest: a plugin
 * runs only if `composer.lock` lists it as `composer-plugin`, so a project
 * cannot acquire one of the allow-listed names without committing it.
 */
class PhpHostBuildPluginTest extends TestCase
{
    /** A lock whose only plugin is the installer Drupal and Magento both use. */
    private function lockWith(array $plugins, array $types = []): string
    {
        $packages = [];
        foreach ($plugins as $name) {
            $packages[] = [
                'name' => $name,
                'type' => $types[$name] ?? 'composer-plugin',
            ];
        }

        return (string) json_encode(['packages' => $packages]);
    }

    public function test_an_installer_plugin_may_run(): void
    {
        $lock = $this->lockWith(['composer/installers']);

        $this->assertSame(['composer/installers'], PhpHostBuild::lockedPlugins($lock));
        $this->assertTrue(PhpHostBuild::mayRunPlugins($lock));
    }

    /**
     * Every plugin `drupal/recommended-project` actually pins, taken from its
     * published lock. All five have to be here: the rule is all-or-nothing, so
     * one absent name disables plugins for the whole install -- which is how
     * `symfony/runtime` was found, after a first deploy with the other four
     * still ran `--no-plugins`.
     */
    public function test_every_plugin_drupal_pins_may_run(): void
    {
        $lock = $this->lockWith([
            'composer/installers',
            'drupal/core-composer-scaffold',
            'drupal/core-recipe-unpack',
            'drupal/core-project-message',
            'symfony/runtime',
        ]);

        $this->assertTrue(PhpHostBuild::mayRunPlugins($lock));
    }

    /**
     * ...and one unnamed plugin is enough to refuse all of them, which is what
     * makes the list load-bearing rather than decorative.
     */
    public function test_a_sixth_plugin_would_refuse_the_whole_install(): void
    {
        $lock = $this->lockWith([
            'composer/installers',
            'symfony/runtime',
            'some/unknown-plugin',
        ]);

        $this->assertFalse(PhpHostBuild::mayRunPlugins($lock));
    }

    /**
     * All or nothing. Composer cannot allow one plugin and refuse another, so
     * a project pinning an application's build plugin alongside an installer
     * gets neither -- which is exactly the behaviour it had before this
     * existed, so refusing is never a regression.
     */
    public function test_an_application_build_plugin_refuses_all_of_them(): void
    {
        $lock = $this->lockWith(['composer/installers', 'magento/magento-composer-installer']);

        $this->assertFalse(PhpHostBuild::mayRunPlugins($lock));
    }

    /** A project with no lock, or no plugins, keeps the safe default. */
    public function test_no_lock_or_no_plugin_keeps_plugins_disabled(): void
    {
        $this->assertSame([], PhpHostBuild::lockedPlugins(null));
        $this->assertSame([], PhpHostBuild::lockedPlugins('not json'));
        $this->assertFalse(PhpHostBuild::mayRunPlugins(null));
        $this->assertFalse(PhpHostBuild::mayRunPlugins($this->lockWith([])));
        $this->assertFalse(PhpHostBuild::mayRunPlugins($this->lockWith(['psr/log'], ['psr/log' => 'library'])));
    }

    /**
     * Read from `packages`, never `packages-dev`: `--no-dev` means a dev-time
     * plugin is not installed, so allowing one would be permission to run code
     * the install then skips.
     */
    public function test_a_dev_only_plugin_does_not_grant_the_permission(): void
    {
        $lock = (string) json_encode([
            'packages' => [],
            'packages-dev' => [['name' => 'composer/installers', 'type' => 'composer-plugin']],
        ]);

        $this->assertSame([], PhpHostBuild::lockedPlugins($lock));
        $this->assertFalse(PhpHostBuild::mayRunPlugins($lock));
    }

    public function test_the_default_install_loses_the_flag_when_allowed(): void
    {
        $install = PhpHostBuild::installCommand(PhpHostBuild::DEFAULT_INSTALL, $this->lockWith(['composer/installers']));

        $this->assertStringNotContainsString('--no-plugins', $install);
        // Everything else is untouched.
        $this->assertStringContainsString('--no-dev', $install);
        $this->assertStringContainsString('--no-scripts', $install);
        $this->assertStringContainsString('--optimize-autoloader', $install);
    }

    public function test_a_manifest_install_loses_the_flag_too(): void
    {
        $declared = 'composer install --no-dev --no-interaction --no-scripts --no-plugins';

        $this->assertSame(
            'composer install --no-dev --no-interaction --no-scripts',
            PhpHostBuild::installCommand($declared, $this->lockWith(['composer/installers']))
        );
    }

    public function test_the_flag_stays_when_the_permission_is_not_granted(): void
    {
        $declared = 'composer install --no-dev --no-plugins';

        $this->assertSame($declared, PhpHostBuild::installCommand($declared, null));
        $this->assertSame($declared, PhpHostBuild::installCommand($declared, $this->lockWith(['some/build-plugin'])));
    }

    /**
     * The whole path: script() is what the host container runs. Asserted on
     * the install line rather than the whole script, because the platform pin
     * that precedes it is `composer config --no-plugins platform.php ...` and
     * must keep its own flag -- that one only writes a config value, and
     * allowing plugins there would run them before the tree exists.
     */
    public function test_the_script_drops_the_flag_for_a_scaffolding_project(): void
    {
        $script = PhpHostBuild::script('', '', true, '8.2', false, $this->lockWith(['composer/installers']));
        $install = $this->installLine($script);

        $this->assertStringNotContainsString('--no-plugins', $install);
        $this->assertStringContainsString('composer install', $install);
        // The pin keeps its flag.
        $this->assertStringContainsString('composer config --no-plugins platform.php', $script);
    }

    public function test_the_script_keeps_the_flag_without_a_lock(): void
    {
        $script = PhpHostBuild::script('', '', true, '8.2', false, null);

        $this->assertStringContainsString('--no-plugins', $this->installLine($script));
    }

    /** The install command in a script, not the pin above it. */
    private function installLine(string $script): string
    {
        foreach (explode("\n", $script) as $line) {
            if (str_contains($line, 'composer install')) {
                return $line;
            }
        }

        return '';
    }
}

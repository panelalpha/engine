<?php

namespace Tests\Unit\Deploy\CacheManager;

use App\Lib\Deploy\CacheManager\PhpBaseImage;
use App\Lib\Deploy\Platform\Runtime\Php\PhpApacheConfig;
use PHPUnit\Framework\TestCase;

/**
 * The baked extension list and the recipe date have to move together.
 *
 * The image tag is the catalogue's `recipe` date, not a hash of what the
 * image contains — `PhpBaseImage::fingerprint()` returns
 * `RuntimeImageCatalog::recipeDate('php')`. The comment beside it says the
 * consequence out loud: change the extension list without bumping the date
 * and every host already holding the old image keeps serving it under a name
 * that now promises something else. Nothing detected that.
 *
 * This does. It fails whenever EXTENSIONS changes until the date is bumped
 * too, which turns a silent stale image into a failing test.
 *
 * **When this test fails:** bump `recipe` in `config/core/images.yaml`,
 * then update the fingerprint below to the value the failure prints. Both, in
 * the same edit. If the date is already today's, either move to the next day
 * or drop the built images from every host that holds them, so this date
 * rebuilds them — the format is day-granular and cannot express two changes
 * in one day on its own.
 */
class RecipeDateBumpTest extends TestCase
{
    /**
     * sha1 of the baked extension list, as of the recipe date below.
     */
    private const EXTENSIONS_FINGERPRINT = '8f8e4c4c6e9c11ddcbc915e0aa2006d07fb4595c';

    private const RECIPE_DATE        = '20260910';

    public function test_the_recipe_date_was_bumped_with_the_extension_list(): void
    {
        $actual = sha1(implode(' ', PhpBaseImage::EXTENSIONS));

        if ($actual !== self::EXTENSIONS_FINGERPRINT) {
            $this->assertNotSame(
                self::RECIPE_DATE,
                PhpBaseImage::fingerprint(),
                "PhpBaseImage::EXTENSIONS changed but the catalogue's `recipe` date did not.\n"
                . "Every host holding the old image would keep serving it under the same tag.\n"
                . "Bump `recipe` in config/core/images.yaml, then set\n"
                . "  EXTENSIONS_FINGERPRINT = '{$actual}'\n"
                . "  RECIPE_DATE            = '" . PhpBaseImage::fingerprint() . "'\n"
                . 'in this test.'
            );
        }

        $this->assertSame(
            self::EXTENSIONS_FINGERPRINT,
            $actual,
            "The extension list changed. Bump `recipe` in config/core/images.yaml,\n"
            . "then set EXTENSIONS_FINGERPRINT = '{$actual}' here."
        );
    }

    /**
     * sha1 of the Apache configuration baked into the same image, as of the
     * same recipe date.
     */
    private const APACHE_FINGERPRINT = '9afd3076f3f03412ed8426840dc816e3d6299444';

    /**
     * The extension list is not the only thing baked into that image.
     *
     * {@see PhpBaseImage::dockerfile()} writes the vhost, the ports file and
     * the module list into it as well, and they are every bit as invisible: a
     * host holding the old image keeps serving the old Apache configuration
     * under a tag that now promises the new one. Koel is what made the gap
     * concrete -- the fix it suggests, teaching the vhost to route a Laravel
     * app that ships no `public/.htaccess` of its own, is a change to a file
     * that lives inside the image and is guarded by nothing.
     *
     * Same rule and same remedy as the extension list above.
     */
    public function test_the_recipe_date_was_bumped_with_the_apache_config(): void
    {
        $actual = sha1(
            PhpApacheConfig::vhost(8000) . "\n"
            . PhpApacheConfig::ports(8000) . "\n"
            . PhpApacheConfig::modules()
        );

        if ($actual !== self::APACHE_FINGERPRINT) {
            $this->assertNotSame(
                self::RECIPE_DATE,
                PhpBaseImage::fingerprint(),
                "The baked Apache configuration changed but the catalogue's `recipe` date did not.\n"
                . "Every host holding the old image would keep serving the old vhost.\n"
                . "Bump `recipe` in config/core/images.yaml, then set\n"
                . "  APACHE_FINGERPRINT = '{$actual}'\n"
                . "  RECIPE_DATE        = '" . PhpBaseImage::fingerprint() . "'\n"
                . 'in this test.'
            );
        }

        $this->assertSame(
            self::APACHE_FINGERPRINT,
            $actual,
            "The Apache configuration changed. Bump `recipe` in config/core/images.yaml,\n"
            . "then set APACHE_FINGERPRINT = '{$actual}' here."
        );
    }

    /** The extensions two applications in the supported-apps series died for. */
    public function test_the_extensions_real_applications_required_are_baked(): void
    {
        // ownCloud: "Root composer.json requires PHP extension ext-memcached".
        $this->assertContains('memcached', PhpBaseImage::EXTENSIONS);
        // Passbolt: "Root composer.json requires PHP extension ext-gnupg".
        $this->assertContains('gnupg', PhpBaseImage::EXTENSIONS);
    }

    /** A date the catalogue does not declare would mean an untagged image. */
    public function test_the_recipe_date_is_declared(): void
    {
        $this->assertMatchesRegularExpression('/^\d{8}$/', (string) PhpBaseImage::fingerprint());
    }
}

<?php

namespace Tests\Unit\Deploy\Platform\Runtime;

use App\Lib\Deploy\Platform\Runtime\RustRuntime;
use App\Lib\Deploy\Platform\Runtime\RuntimeImageCatalog;
use PHPUnit\Framework\TestCase;

/**
 * A Rust host compile does not run in the image the app runs in.
 *
 * `rust:1-slim-bookworm` ships the toolchain and nothing a build script can
 * link against. Measured on this host:
 *
 *     rust:1-bookworm       g++ present   pkg-config present
 *                           /usr/include/openssl/ssl.h present   libssl.so present
 *     rust:1-slim-bookworm  g++ MISSING   pkg-config MISSING
 *                           no openssl headers                  no libssl.so
 *
 * A host compile runs as the account, so `RustRuntime::systemPackages()` --
 * which wants to apt-get `build-essential pkg-config libssl-dev` -- cannot
 * install any of it. Every crate with a build script that probes for a
 * compiler, a header or a library therefore fails, and says so about the
 * crate: `rust-librocksdb-sys` for want of libclang, `openssl-sys` for want of
 * the headers, `glib-sys`, `libsodium-sys`, `libduckdb-sys` the same way.
 *
 * The catalogue can point the runtime somewhere else, and when an operator has
 * done that the compile has to follow -- guessing the other way would compile
 * in an image the app does not run in, which is the bug this exists to avoid.
 */
class RustBuildImageTest extends TestCase
{
    /** @var list<string> */
    private array $written = [];

    protected function setUp(): void
    {
        parent::setUp();
        RuntimeImageCatalog::flush();
    }

    protected function tearDown(): void
    {
        RuntimeImageCatalog::useConfig(null);
        RuntimeImageCatalog::flush();
        foreach ($this->written as $path) {
            @unlink($path);
        }
        $this->written = [];

        parent::tearDown();
    }

    private function config(string $yaml): void
    {
        $path = tempnam(sys_get_temp_dir(), 'runtime-images-') . '.yaml';
        file_put_contents($path, $yaml);
        $this->written[] = $path;
        RuntimeImageCatalog::useConfig($path);
    }

    /**
     * The whole point: the compile image is not the runtime image.
     *
     * If these two ever collapse back into one, the slim tag is what the
     * compile gets again and the crates above fail again.
     */
    public function test_the_compile_image_is_not_the_slim_runtime_image(): void
    {
        RuntimeImageCatalog::useConfig(null);

        $this->assertSame('rust:1-slim-bookworm', RustRuntime::imageTag(RustRuntime::VERSION));
        $this->assertSame('rust:1-bookworm', RustRuntime::buildImageTag(RustRuntime::VERSION));
        $this->assertNotSame(
            RustRuntime::imageTag(RustRuntime::VERSION),
            RustRuntime::buildImageTag(RustRuntime::VERSION),
            'a Rust host compile must not run in the slim runtime image'
        );
    }

    /**
     * A host that points the runtime at an image of its own gets that image
     * for the compile too, unless it names a separate one.
     */
    public function test_the_operators_image_is_what_the_compile_uses(): void
    {
        $this->config(<<<'YAML'
        runtimes:
          rust:
            default: "1"
            image:
              from: "registry.internal/rust:1-debian"
              build_from: "registry.internal/rust:1-debian-toolchain"
        YAML);

        $this->assertSame('registry.internal/rust:1-debian', RustRuntime::imageTag('1'));
        $this->assertSame('registry.internal/rust:1-debian-toolchain', RustRuntime::buildImageTag('1'));

        // ...and without a `build_from`, the runtime image is the compile
        // image -- never a Docker Hub tag the operator replaced.
        $this->config(<<<'YAML'
        runtimes:
          rust:
            default: "1"
            image:
              from: "registry.internal/rust:1-debian"
        YAML);

        $this->assertSame('registry.internal/rust:1-debian', RustRuntime::buildImageTag('1'));
    }

    /**
     * A config that names no Rust at all -- the common case, and the one a
     * half-edited file leaves behind -- still gets a compile-capable image.
     */
    public function test_a_config_silent_about_rust_still_gets_a_compile_image(): void
    {
        $this->config(<<<'YAML'
        runtimes:
          php:
            default: "8.3"
            versions: ["8.3"]
            image:
              from: "php:{version}-apache-bookworm"
        YAML);

        $this->assertSame('rust:1-bookworm', RustRuntime::buildImageTag('1'));
    }
}

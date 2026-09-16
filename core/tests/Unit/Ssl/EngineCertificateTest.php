<?php

namespace Tests\Unit\Ssl;

use App\Lib\Ssl\EngineCertificate;
use App\System;
use App\System\Filesystem;
use PHPUnit\Framework\TestCase;

/**
 * Domain::create() asks whether the engine certificate already covers the
 * project name before it mkdirs public_html. After filesystem moved off
 * System, covering() called methods that no longer exist — an Error, not an
 * Exception — so the pipeline never reached createDomainRootDir.
 */
class EngineCertificateTest extends TestCase
{
    public function test_covering_returns_null_when_engine_certificate_files_are_absent(): void
    {
        $system = new class extends System {
            /** @var list<string> */
            public array $probed = [];

            public function engineDirPath(): string
            {
                return '/opt/panelalpha/shared-hosting';
            }

            public function filesystem(): Filesystem
            {
                $engine = $this;

                return new class ($engine) extends Filesystem {
                    public function __construct(private System $engine)
                    {
                        parent::__construct($engine);
                    }

                    public function fileExists(string $path): bool
                    {
                        $this->engine->probed[] = $path;

                        return false;
                    }

                    public function fileGetContents(string $path): string
                    {
                        throw new \RuntimeException("should not read {$path}");
                    }
                };
            }
        };

        $this->assertNull(
            (new EngineCertificate($system))->covering('pwf414751b.157-180-19-206.panelalpha.direct')
        );
        $this->assertSame(
            ['/opt/panelalpha/shared-hosting/crt/server.cert'],
            $system->probed
        );
    }
}

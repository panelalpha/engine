<?php

namespace Tests\Unit\Deploy\Source;

use App\Lib\Deploy\Source\ProjectArchive;
use PHPUnit\Framework\TestCase;

class ProjectArchiveTest extends TestCase
{
    private string $tmpDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/project-archive-' . bin2hex(random_bytes(8));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
        parent::tearDown();
    }

    public function test_unwraps_single_subdirectory(): void
    {
        mkdir($this->tmpDir . '/my-app');
        file_put_contents($this->tmpDir . '/my-app/index.html', 'hi');

        $this->assertSame(
            $this->tmpDir . '/my-app',
            ProjectArchive::resolveProjectRoot($this->tmpDir)
        );
    }

    public function test_keeps_flat_root_when_files_present(): void
    {
        file_put_contents($this->tmpDir . '/index.html', 'hi');
        mkdir($this->tmpDir . '/assets');

        $this->assertSame($this->tmpDir, ProjectArchive::resolveProjectRoot($this->tmpDir));
    }

    public function test_keeps_root_when_multiple_directories(): void
    {
        mkdir($this->tmpDir . '/a');
        mkdir($this->tmpDir . '/b');

        $this->assertNull(ProjectArchive::findSingleDir($this->tmpDir));
        $this->assertSame($this->tmpDir, ProjectArchive::resolveProjectRoot($this->tmpDir));
    }

    public function test_ignores_ds_store_when_finding_single_dir(): void
    {
        mkdir($this->tmpDir . '/app');
        file_put_contents($this->tmpDir . '/.DS_Store', '');

        $this->assertSame($this->tmpDir . '/app', ProjectArchive::findSingleDir($this->tmpDir));
    }

    private function removeDir(string $dir): void
    {
        if ($dir === '' || !is_dir($dir)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($dir);
    }
}

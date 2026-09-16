<?php

namespace Tests\Unit\System\Project;

use App\Models\User as ModelsUser;
use App\System;
use App\System\Filesystem;
use App\System\Project;
use App\System\Project\FileManager;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProjectFileManagerTest extends TestCase
{
    private string $homeDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->homeDir = sys_get_temp_dir() . '/pa-project-files-' . bin2hex(random_bytes(4));
        mkdir($this->homeDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->homeDir);
        parent::tearDown();
    }

    public function test_project_files_returns_collaborator_rooted_at_project_home(): void
    {
        $system = $this->systemWithDirectFilesystem();
        $project = new Project($system, $this->userModel('alice'));

        $files = $project->fileManager();

        $this->assertInstanceOf(FileManager::class, $files);
        $this->assertSame($this->homeDir, $files->homeDirPath());
    }

    public function test_resolve_builds_absolute_path_under_home(): void
    {
        $files = $this->fileManager();

        $this->assertSame($this->homeDir . '/project', $files->resolvePath('project'));
        $this->assertSame($this->homeDir . '/public_html/', $files->resolvePath('public_html/'));
    }

    public function test_resolve_rejects_path_traversal_and_control_characters(): void
    {
        $files = $this->fileManager();

        try {
            $files->resolvePath('../etc/passwd');
            $this->fail('Expected ValidationException');
        } catch (ValidationException) {
        }

        $this->expectException(ValidationException::class);
        $files->resolvePath("foo\0bar");
    }

    public function test_aggregate_resolve_path_matches_files_collaborator(): void
    {
        $system = $this->systemWithDirectFilesystem();
        $project = new Project($system, $this->userModel('alice'));

        $this->assertSame($project->fileManager()->resolvePath('project'), $project->resolvePath('project'));
    }

    public function test_aggregate_resolve_path_replaces_legacy_var_www(): void
    {
        $model = $this->createMock(ModelsUser::class);
        $model->method('getHomeDir')->willReturn($this->homeDir);
        $model->username = 'alice';

        $system = $this->systemWithDirectFilesystem();
        $project = new Project($system, $model);

        $this->assertSame(
            $this->homeDir . '/project',
            $project->resolvePath('/var/www/project'),
        );
    }

    public function test_put_contents_mkdir_and_storage_round_trip(): void
    {
        $files = $this->fileManager();

        $files->mkdir('nested/dir', true);
        $files->putContents('nested/dir/hello.txt', 'panelalpha');

        $this->assertFileExists($this->homeDir . '/nested/dir/hello.txt');
        $this->assertSame('panelalpha', file_get_contents($this->homeDir . '/nested/dir/hello.txt'));

        $files->storage()->put('via-storage.txt', 'storage');
        $this->assertSame('storage', $files->storage()->get('via-storage.txt'));
        $this->assertTrue($files->storage()->exists('nested/dir/hello.txt'));
        $this->assertContains('nested', $files->storage()->directories('/'));
        $this->assertContains('via-storage.txt', $files->storage()->files('/'));
    }

    private function userModel(string $username): ModelsUser
    {
        $model = new ModelsUser();
        $model->username = $username;
        $model->setDetails(['template' => 'dind', 'deploy_strategy' => 'php']);

        return $model;
    }

    private function fileManager(): FileManager
    {
        return new FileManager(new Project($this->systemWithDirectFilesystem(), $this->userModel('alice')));
    }

    private function systemWithDirectFilesystem(): System
    {
        return new class ($this->homeDir) extends System {
            public function __construct(private string $homeRoot)
            {
            }

            public function projectHomeDirPath(string $username): string
            {
                return $this->homeRoot;
            }

            public function projectDirPath(string $username): string
            {
                return $this->homeRoot . '/engine';
            }

            public function filesystem(): Filesystem
            {
                return new class ($this) extends Filesystem {
                    public function filePutContents(
                        string $path,
                        string $contents,
                        ?string $chown = null,
                        ?string $chmod = null,
                    ): void {
                        $dir = dirname($path);
                        if (!is_dir($dir)) {
                            mkdir($dir, 0777, true);
                        }
                        file_put_contents($path, $contents);
                    }

                    public function makeDirWithParents(string $target, ?string $chown = null): void
                    {
                        if (!is_dir($target)) {
                            mkdir($target, 0777, true);
                        }
                    }
                };
            }
        };
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}

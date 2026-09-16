<?php

namespace Tests\Unit\System\Project;

use App\System\Project\Git\Exception as GitException;
use App\Models\User as ModelsUser;
use App\System;
use App\System\Project as ProjectAggregate;
use App\System\Project\Dind;
use App\System\Project\Dind\Source\GitRepository;
use PHPUnit\Framework\TestCase;
use Tests\Unit\System\Project\FakeGitRunner;

class DindGitRepositoryTest extends TestCase
{
    private string $tmpRoot;
    private string $homeRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpRoot = sys_get_temp_dir() . '/pa-dind-git-' . bin2hex(random_bytes(4));
        $this->homeRoot = $this->tmpRoot . '/home';
        mkdir($this->homeRoot . '/alice/project', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tmpRoot);
        parent::tearDown();
    }

    public function test_status_without_repository_reports_not_connected_tree(): void
    {
        $project = $this->dindProject();
        $git = new TestableGitRepository($project, new FakeGitRunner());

        $status = $git->status();

        $this->assertFalse($status['connected']);
        $this->assertFalse($status['repository_exists']);
        $this->assertSame('project', $status['path_key']);
        $this->assertSame('site_git', $status['managed_by']);
    }

    public function test_deploy_managed_git_repo_uses_deploy_managed_by(): void
    {
        $model = $this->dindModel([
            'git_repo' => 'https://github.com/org/repo.git',
            'git_branch' => 'main',
        ]);
        $project = $this->dind($model);
        $git = new TestableGitRepository($project, new FakeGitRunner());

        $this->assertTrue($git->isDeployManaged());
        $runner = new FakeGitRunner();
        $runner->stdout = [
            'rev-parse --is-inside-work-tree' => "true\n",
            'status --porcelain' => '',
            'config --get remote.origin.url' => "https://github.com/org/repo.git\n",
            'branch --show-current' => "main\n",
            'rev-parse --abbrev-ref @{upstream}' => "origin/main\n",
            'rev-list --count @{upstream}..HEAD' => "0\n",
            'rev-list --count HEAD..@{upstream}' => "0\n",
        ];
        $git = new TestableGitRepository($project, $runner);
        $status = $git->status();

        $this->assertSame('deploy', $status['managed_by']);
    }

    public function test_pull_ff_rejects_dirty_tree_before_fetch(): void
    {
        $model = $this->dindModel();
        $model->putSiteGit('project', [
            'repo_url' => 'https://github.com/org/repo.git',
            'branch' => 'main',
            'token' => null,
        ]);
        $project = $this->dind($model);
        $runner = new FakeGitRunner();
        $runner->stdout = [
            'rev-parse --is-inside-work-tree' => "true\n",
            'status --porcelain' => " M file\n",
        ];
        $git = new TestableGitRepository($project, $runner);

        try {
            $git->pull(GitRepository::STRATEGY_FF);
            $this->fail('Expected GitException');
        } catch (GitException $e) {
            $this->assertSame('Working tree is dirty.', $e->getMessage());
        }

        foreach ($runner->commands as $cmd) {
            $this->assertNotContains('fetch', $cmd);
        }
    }

    public function test_disconnect_blocked_on_deploy_managed_checkout(): void
    {
        $model = $this->dindModel([
            'git_repo' => 'https://github.com/org/repo.git',
            'git_branch' => 'main',
        ]);
        $project = $this->dind($model);
        $git = new TestableGitRepository($project, new FakeGitRunner());

        $this->expectException(GitException::class);
        $this->expectExceptionMessage('Git is managed by deploy.');
        $git->disconnect();
    }

    public function test_branches_parses_for_each_ref_output(): void
    {
        $project = $this->dindProject();
        $runner = new FakeGitRunner();
        $runner->stdout = [
            'rev-parse --is-inside-work-tree' => "true\n",
            'for-each-ref' => implode("\n", [
                'refs/heads/main' . "\x1f" . 'main' . "\x1f" . 'origin/main' . "\x1f" . '*',
                'refs/remotes/origin/develop' . "\x1f" . 'origin/develop' . "\x1f" . '' . "\x1f" . '',
            ]),
        ];
        $git = new TestableGitRepository($project, $runner);

        $branches = $git->branches();

        $this->assertCount(2, $branches);
        $this->assertSame('main', $branches[0]['name']);
        $this->assertTrue($branches[0]['current']);
    }

    public function test_connect_on_empty_dir_persists_site_git(): void
    {
        $model = $this->dindModel();
        $runner = new FakeGitRunner();
        $runner->stdout = [
            'status --porcelain' => '',
            'config --get remote.origin.url' => "https://github.com/org/repo.git\n",
            'branch --show-current' => "main\n",
            'rev-parse --abbrev-ref @{upstream}' => '',
        ];
        $git = new TestableGitRepository($this->dind($model), $runner);

        $status = $git->connect('https://github.com/org/repo.git', 'main', null);

        $this->assertTrue($status['connected']);
        $site = $model->getSiteGit('project');
        $this->assertNotNull($site);
        $this->assertSame('https://github.com/org/repo.git', $site['repo_url']);
        $this->assertSame('main', $site['branch']);
        $this->assertNull($site['token']);
    }

    public function test_commits_parses_log_output(): void
    {
        $project = $this->dindProject();
        $runner = new FakeGitRunner();
        $runner->stdout = [
            'rev-parse --is-inside-work-tree' => "true\n",
            'log' => 'abc123' . "\x1f" . 'abc' . "\x1f" . 'First' . "\x1f" . 'Ada' . "\x1f" . '2026-01-02T03:04:05Z',
        ];
        $git = new TestableGitRepository($project, $runner);

        $commits = $git->commits(10);

        $this->assertCount(1, $commits);
        $this->assertSame('abc123', $commits[0]['hash']);
        $this->assertSame('First', $commits[0]['subject']);
        $this->assertSame('Ada', $commits[0]['author']);
    }

    public function test_revert_hard_resets_and_cleans(): void
    {
        $project = $this->dindProject();
        $runner = new FakeGitRunner();
        $runner->stdout = [
            'rev-parse --is-inside-work-tree' => "true\n",
            'rev-parse --verify HEAD' => "abc123\n",
            'reset --hard' => '',
            'clean -fd' => '',
            'status --porcelain' => '',
            'config --get remote.origin.url' => "https://github.com/org/repo.git\n",
            'branch --show-current' => "main\n",
            'rev-parse --abbrev-ref @{upstream}' => '',
        ];
        $git = new TestableGitRepository($project, $runner);

        $status = $git->revert();

        $joined = array_map(fn (array $cmd) => implode(' ', $cmd), $runner->commands);
        $this->assertTrue($this->commandsContain($joined, 'reset --hard HEAD'));
        $this->assertTrue($this->commandsContain($joined, 'clean -fd'));
        $this->assertFalse($status['dirty']);
    }

    /**
     * @param list<string> $commands
     */
    private function commandsContain(array $commands, string $needle): bool
    {
        foreach ($commands as $cmd) {
            if (str_contains($cmd, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function dindProject(): Dind
    {
        return $this->dind($this->dindModel());
    }

    private function dind(ModelsUser $model): Dind
    {
        $runtime = (new ProjectAggregate($this->system(), $model))->runtime();
        $this->assertInstanceOf(Dind::class, $runtime);

        return $runtime;
    }

    private function dindModel(array $details = []): ModelsUser
    {
        $model = new ModelsUser();
        $model->username = 'alice';
        $model->setDetails(array_merge([
            'template' => 'dind',
            'UID' => 1000,
            'GID' => 1000,
        ], $details));

        return $model;
    }

    private function system(): System
    {
        return new class ($this->tmpRoot, $this->homeRoot) extends System {
            public function __construct(
                private string $engineRoot,
                private string $homesRoot,
            ) {
            }

            public function engineDirPath(): string
            {
                return $this->engineRoot;
            }

            public function homesDirPath(): string
            {
                return $this->homesRoot;
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

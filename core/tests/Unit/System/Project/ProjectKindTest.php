<?php

namespace Tests\Unit\System\Project;

use App\Models\User as ModelsUser;
use App\System;
use App\System\Project\Dind;
use App\System\Project\PhpHosting;
use App\System\Project\ProjectKind;
use PHPUnit\Framework\TestCase;

class ProjectKindTest extends TestCase
{
    public function test_git_project_resolves_to_dind(): void
    {
        $model = $this->createMock(ModelsUser::class);
        $model->method('hasGitProject')->willReturn(true);
        $model->method('getTemplate')->willReturn(null);

        $this->assertSame(ProjectKind::DIND, ProjectKind::fromModel($model));
    }

    public function test_dind_template_resolves_to_dind(): void
    {
        $model = $this->createMock(ModelsUser::class);
        $model->method('hasGitProject')->willReturn(false);
        $model->method('getTemplate')->willReturn('dind');

        $this->assertSame(ProjectKind::DIND, ProjectKind::fromModel($model));
    }

    public function test_missing_git_and_template_resolves_to_php_hosting(): void
    {
        $model = $this->createMock(ModelsUser::class);
        $model->method('hasGitProject')->willReturn(false);
        $model->method('getTemplate')->willReturn(null);

        $this->assertSame(ProjectKind::PHP_HOSTING, ProjectKind::fromModel($model));
    }

    public function test_classic_template_resolves_to_php_hosting_not_a_webserver_type(): void
    {
        $model = $this->createMock(ModelsUser::class);
        $model->method('hasGitProject')->willReturn(false);
        $model->method('getTemplate')->willReturn('default');

        $this->assertSame(ProjectKind::PHP_HOSTING, ProjectKind::fromModel($model));
    }

    public function test_system_project_factory_returns_aggregate_for_each_kind(): void
    {
        $system = new System();

        $dindModel = new ModelsUser();
        $dindModel->username = 'alice';
        $dindModel->setDetails(['git_repo' => 'https://example.com/repo.git']);

        $phpModel = new ModelsUser();
        $phpModel->username = 'bob';
        $phpModel->setDetails(['template' => 'default']);

        $dindProject = $system->project($dindModel);
        $phpProject = $system->project($phpModel);

        $this->assertInstanceOf(\App\System\Project::class, $dindProject);
        $this->assertInstanceOf(\App\System\Project::class, $phpProject);
        $this->assertNotInstanceOf(Dind::class, $dindProject);
        $this->assertNotInstanceOf(PhpHosting::class, $phpProject);
    }
}

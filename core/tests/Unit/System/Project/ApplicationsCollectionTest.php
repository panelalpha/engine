<?php

namespace Tests\Unit\System\Project;

use App\Models\Domain as DomainModel;
use App\Models\User as ModelsUser;
use App\System;
use App\System\Project;
use App\System\Project\Applications;
use App\System\Project\Dind\AbstractApplication as DindApplication;
use App\System\Project\PhpHosting\AbstractApplication as PhpApplication;
use PHPUnit\Framework\TestCase;

class ApplicationsCollectionTest extends TestCase
{
    public function test_dind_without_deploy_strategy_has_no_applications(): void
    {
        $system = new System();
        $model = new ModelsUser();
        $model->username = 'alice';
        $model->setDetails(['template' => 'dind']);

        $project = new Project($system, $model);
        $apps = $project->applications();

        $this->assertInstanceOf(Applications::class, $apps);
        $this->assertTrue($apps->isEmpty());
        $this->assertNull($apps->primary());
        $this->assertNull($project->app());
    }

    public function test_dind_with_deploy_strategy_has_one_application(): void
    {
        $system = new System();
        $model = new ModelsUser();
        $model->username = 'alice';
        $model->setDetails([
            'template' => 'dind',
            'deploy_strategy' => 'express',
        ]);

        $project = new Project($system, $model);
        $primary = $project->applications()->primary();

        $this->assertInstanceOf(DindApplication::class, $primary);
        $this->assertSame(1, $project->applications()->count());
        $this->assertSame($primary, $project->app());
    }

    public function test_php_hosting_groups_domains_by_document_root(): void
    {
        $system = new System();
        $model = $this->getMockBuilder(ModelsUser::class)
            ->onlyMethods(['getDomains'])
            ->getMock();
        $model->username = 'bob';
        $model->setDetails(['template' => 'default']);

        $a = new DomainModel();
        $a->domain = 'a.example.test';
        $a->setDetails(['document_root' => '/a.example.test/public_html']);

        $b = new DomainModel();
        $b->domain = 'b.example.test';
        $b->setDetails(['document_root' => '/a.example.test/public_html']);

        $c = new DomainModel();
        $c->domain = 'c.example.test';
        $c->setDetails(['document_root' => '/c.example.test/public_html']);

        $model->method('getDomains')->willReturn([$a, $b, $c]);

        $project = new Project($system, $model);
        $apps = $project->applications()->all();

        $this->assertCount(2, $apps);
        $this->assertContainsOnlyInstancesOf(PhpApplication::class, $apps);
        $this->assertCount(2, $apps[0]->domains());
        $this->assertCount(1, $apps[1]->domains());
    }
}

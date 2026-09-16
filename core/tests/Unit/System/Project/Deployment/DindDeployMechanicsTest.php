<?php

namespace Tests\Unit\System\Project\Deployment;

use PHPUnit\Framework\TestCase;

class DindDeployMechanicsTest extends TestCase
{
    private string $coreAppRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coreAppRoot = dirname(__DIR__, 5) . '/app';
    }

    public function test_workflow_defaults_to_dind_deploy_mechanics_not_lib_user(): void
    {
        $source = file_get_contents($this->coreAppRoot . '/System/Project/Deployment/DeploymentWorkflow.php');
        $this->assertStringContainsString('DindDeployMechanics', $source);
        $this->assertStringNotContainsString('new LibUserDeployMechanics', $source);
    }

    public function test_dind_deploy_mechanics_never_forwards_to_lib_connect(): void
    {
        $source = file_get_contents($this->coreAppRoot . '/System/Project/Deployment/DindDeployMechanics.php');
        $this->assertStringNotContainsString('->connect()', $source);
        $this->assertStringNotContainsString('LibUserDeployMechanics', $source);
    }

    public function test_prepare_from_source_lives_on_dind_not_git_repository(): void
    {
        $dind = file_get_contents($this->coreAppRoot . '/System/Project/Dind.php');
        $this->assertStringContainsString('prepareFromSources', $dind);
        $this->assertStringContainsString('PrepareFromSource', $dind);

        $git = file_get_contents($this->coreAppRoot . '/System/Project/Dind/Source/GitRepository.php');
        $this->assertStringNotContainsString('PrepareFromSource', $git);
        $this->assertStringNotContainsString('DetectProjectStrategy', $git);
    }

    public function test_ingest_composes_git_clone_then_prepare(): void
    {
        $source = file_get_contents($this->coreAppRoot . '/System/Project/Deployment/DindDeployMechanics.php');
        $this->assertStringContainsString('preCheckFromSources', $source);
        $this->assertStringContainsString('cloneConfiguredRepository', $source);
        $this->assertStringContainsString('prepareFromSources', $source);
    }

    public function test_source_rebuild_uses_project_outer_helpers_and_wipe_ingest(): void
    {
        $source = file_get_contents($this->coreAppRoot . '/System/Project/Deployment/DindDeployMechanics.php');
        $this->assertStringContainsString('function syncHostingForSourceRebuild', $source);
        $this->assertStringContainsString('function ingestForWipeRebuild', $source);
        $this->assertStringContainsString('prepareLinuxIsolation', $source);
        $this->assertStringContainsString('recreateOuterCompose', $source);
        $this->assertStringContainsString('importProjectArchive', $source);
    }
}

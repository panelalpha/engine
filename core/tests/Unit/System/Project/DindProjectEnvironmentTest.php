<?php

namespace Tests\Unit\System\Project;

use App\Lib\Deploy\EnvFile;
use App\Models\User as ModelsUser;
use App\System\Project as ProjectAggregate;
use App\System\Project\Dind;
use Tests\TestCase;

class DindProjectEnvironmentTest extends TestCase
{
    private string $tmpRoot;
    private string $homeRoot;
    private string $projectDir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:' . base64_encode(str_repeat('k', 32))]);
        $this->app->forgetInstance('encrypter');

        $this->tmpRoot = sys_get_temp_dir() . '/pa-dind-env-' . bin2hex(random_bytes(4));
        $this->homeRoot = $this->tmpRoot . '/home';
        $this->projectDir = $this->homeRoot . '/alice/project';
        mkdir($this->projectDir, 0777, true);
        mkdir($this->tmpRoot . '/users/alice', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tmpRoot);
        parent::tearDown();
    }

    public function test_apply_writes_env_from_example_and_fills_blank_app_key(): void
    {
        file_put_contents($this->projectDir . '/.env.example', "APP_NAME=Demo\nAPP_KEY=\nAPP_DEBUG=true\n");

        $model = $this->dindModel();
        $this->dind($model)->applyProjectEnvVars();

        $this->assertFileExists($this->projectDir . '/.env.default');
        $this->assertFileExists($this->projectDir . '/.env');

        $env = $this->vars((string) file_get_contents($this->projectDir . '/.env'));
        $this->assertSame('Demo', $env['APP_NAME']);
        $this->assertNotSame('', $env['APP_KEY']);
        $this->assertStringStartsWith('base64:', $env['APP_KEY']);
        $this->assertFalse($model->usedCustomEnvVars());
    }

    public function test_apply_merges_non_empty_overrides_onto_example_base(): void
    {
        file_put_contents($this->projectDir . '/.env.example', "APP_NAME=Demo\nAPP_KEY=\n");

        $model = $this->dindModel(['env_vars' => ['APP_NAME' => 'Custom', 'EMPTY' => '']]);
        $this->dind($model)->applyProjectEnvVars();

        $default = $this->vars((string) file_get_contents($this->projectDir . '/.env.default'));
        $env = $this->vars((string) file_get_contents($this->projectDir . '/.env'));

        $this->assertSame('Demo', $default['APP_NAME']);
        $this->assertSame('Custom', $env['APP_NAME']);
        $this->assertArrayNotHasKey('EMPTY', $env);
        $this->assertTrue($model->usedCustomEnvVars());
    }

    public function test_defer_to_compose_defaults_matches_lib_lychee_case(): void
    {
        $envExample = <<<'ENV'
APP_NAME=Lychee
APP_KEY=base64:generatedbytheengine
DB_CONNECTION=sqlite
DB_HOST=
DB_DATABASE=lychee
ENV;
        $compose = <<<'YAML'
x-common-env: &common-env
  APP_KEY: "${APP_KEY}"
  DB_CONNECTION: "${DB_CONNECTION:-mysql}"
  DB_HOST: "${DB_HOST:-lychee_db}"
  DB_DATABASE: "${DB_DATABASE:-lychee}"
YAML;

        [$trimmed, $dropped] = Dind\ProjectEnvironment::deferToComposeDefaults($envExample, $compose);

        $this->assertContains('DB_CONNECTION', $dropped);
        $this->assertArrayNotHasKey('DB_CONNECTION', $this->vars($trimmed));
        $this->assertSame('Lychee', $this->vars($trimmed)['APP_NAME']);
    }

    /**
     * @return array<string, string>
     */
    private function vars(string $contents): array
    {
        $values = [];
        foreach (EnvFile::parse($contents) as $row) {
            if (($row['type'] ?? '') === 'variable') {
                $values[(string) $row['key']] = (string) ($row['value'] ?? '');
            }
        }

        return $values;
    }

    private function dind(ModelsUser $model): Dind
    {
        $runtime = (new ProjectAggregate(new LocalHostSystem($this->tmpRoot, $this->homeRoot), $model))->runtime();
        $this->assertInstanceOf(Dind::class, $runtime);

        return $runtime;
    }

    private function dindModel(array $details = []): ModelsUser
    {
        $model = new class extends ModelsUser {
            public function save(array $options = []): bool
            {
                return true;
            }
        };
        $model->username = 'alice';
        $model->setDetails(array_merge([
            'template' => 'dind',
            'UID' => 1000,
            'GID' => 1000,
        ], $details));

        return $model;
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

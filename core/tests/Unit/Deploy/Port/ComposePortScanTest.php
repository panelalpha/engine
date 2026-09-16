<?php

namespace Tests\Unit\Deploy\Port;

use App\Lib\Deploy\Port\ComposePortScan;
use PHPUnit\Framework\TestCase;

/**
 * The port the proxy is pointed at, read out of the repository's own compose
 * file.
 *
 * Getting this wrong is the difference between a working site and a
 * connection reset, and the failure is invisible from the deploy log: every
 * container is up and healthy, the proxy is just talking to the wrong one.
 */
class ComposePortScanTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/pa-ports-' . bin2hex(random_bytes(8));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
        parent::tearDown();
    }

    private function compose(string $yaml): string
    {
        $path = $this->dir . '/compose.yaml';
        file_put_contents($path, $yaml);

        return $path;
    }

    public function test_a_single_published_port_is_the_primary(): void
    {
        $path = $this->compose(<<<'YAML'
        services:
          app:
            image: acme/app
            ports:
              - "8080:8080"
        YAML);

        $this->assertSame(['all' => [8080], 'primary' => 8080], ComposePortScan::of($path));
    }

    public function test_the_preferred_port_wins_over_a_lower_number(): void
    {
        // 3000 is numerically smaller, but 80 is what a browser will ask for.
        $path = $this->compose(<<<'YAML'
        services:
          web:
            image: nginx
            ports:
              - "80:80"
          api:
            image: acme/api
            ports:
              - "3000:3000"
        YAML);

        $this->assertSame([80, 3000], ComposePortScan::of($path)['all']);
    }

    public function test_unpreferred_ports_sort_numerically_after_preferred_ones(): void
    {
        $path = $this->compose(<<<'YAML'
        services:
          app:
            image: acme/app
            ports:
              - "9999:9999"
              - "4200:4200"
              - "8000:8000"
        YAML);

        $this->assertSame([8000, 4200, 9999], ComposePortScan::of($path)['all']);
    }

    public function test_a_database_port_is_not_offered(): void
    {
        $path = $this->compose(<<<'YAML'
        services:
          app:
            image: acme/app
            ports:
              - "8080:8080"
          db:
            image: postgres:16
            ports:
              - "5432:5432"
        YAML);

        $this->assertSame(['all' => [8080], 'primary' => 8080], ComposePortScan::of($path));
    }

    public function test_a_remapped_database_is_still_not_offered(): void
    {
        // Host 5434 looks like an ordinary port. The container side and the
        // image both say otherwise.
        $path = $this->compose(<<<'YAML'
        services:
          db:
            image: postgres:16
            ports:
              - "5434:5432"
        YAML);

        $this->assertSame(['all' => []], ComposePortScan::of($path));
    }

    public function test_expose_is_read_when_nothing_is_published(): void
    {
        $path = $this->compose(<<<'YAML'
        services:
          app:
            image: acme/app
            expose:
              - "3000"
        YAML);

        $this->assertSame(3000, ComposePortScan::primaryOf($path));
    }

    public function test_a_loopback_binding_offers_nothing(): void
    {
        $path = $this->compose(<<<'YAML'
        services:
          app:
            image: acme/app
            ports:
              - "127.0.0.1:8080:8080"
        YAML);

        $this->assertSame(['all' => []], ComposePortScan::of($path));
    }

    public function test_an_env_var_default_is_honoured(): void
    {
        $path = $this->compose(<<<'YAML'
        services:
          app:
            image: acme/app
            ports:
              - "${APP_PORT:-8090}:8000"
        YAML);

        $this->assertSame(8090, ComposePortScan::primaryOf($path));
    }

    public function test_duplicate_ports_across_services_appear_once(): void
    {
        $path = $this->compose(<<<'YAML'
        services:
          blue:
            image: acme/app
            ports:
              - "8080:8080"
          green:
            image: acme/app
            ports:
              - "8080:8080"
        YAML);

        $this->assertSame([8080], ComposePortScan::of($path)['all']);
    }

    public function test_a_stack_that_publishes_nothing_falls_back_to_the_default(): void
    {
        // Common for repos whose compose relies on an external proxy. 8080 is
        // what the engine's generated entrypoint listens on.
        $path = $this->compose(<<<'YAML'
        services:
          app:
            image: acme/app
        YAML);

        $this->assertSame(['all' => []], ComposePortScan::of($path));
        $this->assertSame(8080, ComposePortScan::primaryOf($path));
    }

    public function test_a_missing_file_falls_back_to_the_default(): void
    {
        $this->assertSame(['all' => []], ComposePortScan::of($this->dir . '/nothing.yaml'));
        $this->assertSame(8080, ComposePortScan::primaryOf($this->dir . '/nothing.yaml'));
    }

    public function test_unparseable_yaml_falls_back_to_the_default(): void
    {
        // A broken compose file is the project's problem, but it must not be
        // an exception out of detection.
        $path = $this->compose("services:\n  app:\n   - ports: [\n");

        $this->assertSame(8080, ComposePortScan::primaryOf($path));
    }

    public function test_a_non_mapping_service_entry_is_ignored(): void
    {
        $path = $this->compose(<<<'YAML'
        services:
          app: null
          web:
            image: nginx
            ports:
              - "80:80"
        YAML);

        $this->assertSame([80], ComposePortScan::of($path)['all']);
    }
}

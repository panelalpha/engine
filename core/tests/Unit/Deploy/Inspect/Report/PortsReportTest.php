<?php

namespace Tests\Unit\Deploy\Inspect\Report;

use App\Lib\Deploy\Inspect\Report\PortsReport;

/**
 * Which port the engine would proxy, and where that number came from.
 *
 * The sources disagree often enough that reporting one number would be a
 * guess. What is asserted here is the precedence — platform, then compose,
 * then the Dockerfile's EXPOSE — and that the losing answers are still
 * reported, so a disagreement is visible rather than silently resolved.
 */
class PortsReportTest extends ReportTestCase
{
    public function test_the_platform_hint_outranks_every_file(): void
    {
        $this->write('docker-compose.yml', "services:\n  web:\n    image: app\n    ports:\n      - \"3000:3000\"\n");
        $this->write('Dockerfile', "FROM node\nEXPOSE 8080\n");

        $report = PortsReport::of($this->tmpDir, ['port_hint' => 4000]);

        $this->assertSame(4000, $report['primary']);
        $this->assertSame('platform', $report['source']);
        // The others are still shown, so the disagreement is visible.
        $this->assertSame([3000], $report['compose']);
        $this->assertSame(8080, $report['dockerfile_expose']);
    }

    public function test_compose_wins_when_no_platform_claimed_a_port(): void
    {
        $this->write('docker-compose.yml', "services:\n  web:\n    image: app\n    ports:\n      - \"3000:3000\"\n");
        $this->write('Dockerfile', "FROM node\nEXPOSE 8080\n");

        $report = PortsReport::of($this->tmpDir, []);

        $this->assertSame(3000, $report['primary']);
        $this->assertSame('compose', $report['source']);
    }

    public function test_the_dockerfile_expose_is_the_last_resort(): void
    {
        $this->write('Dockerfile', "FROM node\nEXPOSE 8080\n");

        $report = PortsReport::of($this->tmpDir, []);

        $this->assertSame(8080, $report['primary']);
        $this->assertSame('dockerfile', $report['source']);
        $this->assertSame([], $report['compose']);
    }

    public function test_a_project_that_names_no_port_anywhere_reports_none(): void
    {
        $report = PortsReport::of($this->tmpDir, []);

        $this->assertNull($report['primary']);
        $this->assertNull($report['source']);
        $this->assertSame([], $report['compose']);
        $this->assertNull($report['dockerfile_expose']);
    }

    public function test_it_finds_the_compose_file_the_decision_did_not_name(): void
    {
        $this->write('compose.yaml', "services:\n  web:\n    image: app\n    ports:\n      - \"5000:5000\"\n");

        $this->assertSame(5000, PortsReport::of($this->tmpDir, [])['primary']);
    }

    public function test_a_compose_path_that_no_longer_exists_falls_back_to_the_directory(): void
    {
        $this->write('docker-compose.yml', "services:\n  web:\n    image: app\n    ports:\n      - \"3000:3000\"\n");

        $report = PortsReport::of($this->tmpDir, ['compose_path' => $this->tmpDir . '/gone.yml']);

        $this->assertSame([3000], $report['compose']);
    }

    /**
     * A datastore's published port is not the application's. Reporting 5432
     * as the primary would point the proxy at Postgres and leave the site
     * unreachable.
     */
    public function test_a_datastore_port_is_not_offered_as_the_applications(): void
    {
        $this->write('docker-compose.yml', <<<YAML
        services:
          web:
            image: app
            ports:
              - "8000:8000"
          db:
            image: postgres:16
            ports:
              - "5432:5432"
        YAML);

        $report = PortsReport::of($this->tmpDir, []);

        $this->assertSame(8000, $report['primary']);
        $this->assertNotContains(5432, $report['compose']);
    }

    public function test_it_reads_the_dockerfile_the_decision_named(): void
    {
        $this->write('Dockerfile.web', "FROM node\nEXPOSE 7000\n");

        $report = PortsReport::of($this->tmpDir, ['dockerfile' => 'Dockerfile.web']);

        $this->assertSame(7000, $report['dockerfile_expose']);
        $this->assertSame('dockerfile', $report['source']);
    }

    public function test_a_trailing_slash_on_the_project_dir_changes_nothing(): void
    {
        $this->write('Dockerfile', "FROM node\nEXPOSE 8080\n");

        $this->assertSame(8080, PortsReport::of($this->tmpDir . '/', [])['dockerfile_expose']);
    }

    /**
     * Fusion ships a packaging Dockerfile that copies a binary CI built, and
     * EXPOSEs 8080. Detection refuses to build it, so its EXPOSE is not a
     * port this project publishes and must not be reported as one.
     */
    public function test_an_unbuildable_dockerfile_contributes_no_exposed_port(): void
    {
        $this->write('Dockerfile', "FROM alpine\nEXPOSE 8080\nCOPY build/fusion-\${TARGETOS} ./fusion\n");

        $report = PortsReport::of($this->tmpDir, []);

        $this->assertNull($report['dockerfile_expose']);
        $this->assertNull($report['primary']);
        $this->assertNull($report['source']);
    }

    /** The same file, with the binary it copies present, is fine. */
    public function test_a_buildable_dockerfile_still_contributes_its_exposed_port(): void
    {
        mkdir($this->tmpDir . '/build');
        touch($this->tmpDir . '/build/fusion-linux');
        $this->write('Dockerfile', "FROM alpine\nEXPOSE 8080\nCOPY build/fusion-\${TARGETOS} ./fusion\n");

        $report = PortsReport::of($this->tmpDir, []);

        $this->assertSame(8080, $report['dockerfile_expose']);
        $this->assertSame('dockerfile', $report['source']);
    }
}

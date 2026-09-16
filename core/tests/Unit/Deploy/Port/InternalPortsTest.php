<?php

namespace Tests\Unit\Deploy\Port;

use App\Lib\Deploy\Port\InternalPorts;
use App\Lib\Deploy\Port\PortMapping;
use PHPUnit\Framework\TestCase;

/**
 * Which published ports belong to a datastore rather than to the app.
 *
 * A stack that publishes both 8080 and 5432 offers the proxy two choices,
 * and pointing it at Postgres gives every visitor a connection reset. The
 * mistake in the other direction is worse, though: filtering the app's own
 * port leaves the site unreachable with nothing to point at, which is why
 * this only ever recognises, never guesses.
 */
class InternalPortsTest extends TestCase
{
    public function test_well_known_datastore_ports_are_internal(): void
    {
        foreach ([3306, 5432, 6379, 11211, 27017, 5672, 9200, 9042] as $port) {
            $this->assertTrue(InternalPorts::isKnown($port), (string) $port);
        }
    }

    public function test_web_ports_are_not_internal(): void
    {
        foreach ([80, 443, 3000, 8000, 8080, 8443, 5173] as $port) {
            $this->assertFalse(InternalPorts::isKnown($port), (string) $port);
        }
    }

    public function test_ports_overlapping_the_web_range_are_left_to_the_image_check(): void
    {
        // Cassandra's 7000/7001 and InfluxDB's 8086 are plausible app ports.
        // Listing them would cost more sites than it saves.
        foreach ([7000, 7001, 8086] as $port) {
            $this->assertFalse(InternalPorts::isKnown($port), (string) $port);
        }
    }

    public function test_a_binding_is_covered_by_its_host_port(): void
    {
        $this->assertTrue(InternalPorts::coversBinding(PortMapping::parse('5432:5432'), []));
    }

    public function test_a_binding_is_covered_by_its_container_port(): void
    {
        // Postgres remapped to 5434 on the host. The host side looks
        // unremarkable; the container side gives it away.
        $this->assertTrue(InternalPorts::coversBinding(PortMapping::parse('5434:5432'), []));
    }

    public function test_a_datastore_image_covers_a_remapped_binding(): void
    {
        // Neither end is a known port, so only the image says what this is.
        $this->assertTrue(InternalPorts::coversBinding(
            PortMapping::parse('9001:9001'),
            ['image' => 'postgres:16']
        ));
    }

    public function test_an_application_binding_is_not_covered(): void
    {
        $this->assertFalse(InternalPorts::coversBinding(
            PortMapping::parse('8080:8080'),
            ['image' => 'ghcr.io/acme/app:latest']
        ));
    }

    public function test_a_service_with_no_image_is_not_a_datastore(): void
    {
        // A `build:` service is the project's own code by definition.
        $this->assertFalse(InternalPorts::isDatastore(['build' => '.']));
        $this->assertFalse(InternalPorts::isDatastore([]));
    }

    public function test_datastore_images_are_recognised(): void
    {
        foreach (['postgres:16', 'mysql:8', 'redis:7', 'mongo:7', 'mariadb:11'] as $image) {
            $this->assertTrue(InternalPorts::isDatastore(['image' => $image]), $image);
        }
    }
}

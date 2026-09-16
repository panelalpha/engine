<?php

namespace Tests\Unit\Deploy\Telemetry;

use App\Lib\Deploy\Telemetry\NotificationPreferences;
use App\Lib\Deploy\Telemetry\Telemetry;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Where notification preferences are POSTed, and that the class that
 * builds that URL still exists.
 *
 * `telemetry:ship` calls {@see NotificationPreferences::sync()} after a
 * successful drain. The endpoint used to be assembled through
 * `App\Lib\PanelAlpha\Monitoring`, which was deleted when monitoring moved
 * to Integrations — so the shipper wrote a daily ERROR and monitoring never
 * saw probe targets. The assertion that would have caught it is "this
 * posts at {@see Telemetry::endpoint()}, and does not throw".
 */
class NotificationPreferencesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Setting::setRuntimeSettings([
            'email' => 'ops@example.test',
            'cert_domain' => 'engine.example.test',
            'license_key' => 'TEST-KEY',
            NotificationPreferences::SETTING_TELEMETRY_ENABLED => '1',
        ]);
        config([
            'monitoring.url' => 'https://monitoring.test',
            'hub.url' => 'https://hub.example.test',
            'telemetry.reports_path' => Telemetry::EVENTS_PATH,
            'telemetry.timeout' => 5,
            'telemetry.token' => '',
        ]);
    }

    protected function tearDown(): void
    {
        Setting::clearRuntimeSettings();
        parent::tearDown();
    }

    public function test_sync_posts_preferences_at_the_telemetry_endpoint(): void
    {
        Http::fake(['*' => Http::response(['accepted' => 1], 200)]);

        $result = NotificationPreferences::sync(true);

        $this->assertTrue($result['ok'], $result['message']);
        Http::assertSent(function ($request) {
            $events = $request['events'] ?? [];
            $event = $events[0] ?? [];

            return $request->url() === Telemetry::endpoint()
                && $request->url() === 'https://monitoring.test/api/v1/events'
                && $request->hasHeader('License-Key', 'TEST-KEY')
                && ($event['type'] ?? null) === 'notification.preferences'
                && ($event['payload']['enabled'] ?? null) === true
                && ($event['payload']['notify_email'] ?? null) === 'ops@example.test'
                && ($event['payload']['server_probe_url'] ?? null) === 'https://engine.example.test';
        });
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'hub.example.test'));
    }

    /**
     * Emptying the monitoring host is "report nowhere". The old class-not-found
     * path never reached this branch; a missing class is not an empty URL.
     */
    public function test_sync_holds_when_monitoring_is_unset(): void
    {
        config(['monitoring.url' => '']);
        Http::fake();

        $result = NotificationPreferences::sync(true);

        $this->assertFalse($result['ok']);
        $this->assertSame('No monitoring endpoint configured', $result['message']);
        Http::assertNothingSent();
    }
}

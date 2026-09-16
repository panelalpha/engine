<?php

namespace Tests\Unit;

use App\Http\Controllers\LighthouseController;
use ReflectionMethod;
use Tests\TestCase;

class LighthouseScreenshotStrippingTest extends TestCase
{
    /** @param array<string, mixed> $result */
    private function strip(array $result): array
    {
        $controller = new LighthouseController();
        $method = new ReflectionMethod($controller, 'stripScreenshotData');
        $method->invokeArgs($controller, [&$result]);

        return $result;
    }

    public function test_the_embedded_screenshot_is_replaced_with_its_byte_count(): void
    {
        $result = $this->strip([
            'audits' => [
                'final-screenshot' => [
                    'score' => 1,
                    'details' => ['data' => str_repeat('a', 98000)],
                ],
            ],
        ]);

        $this->assertNull($result['audits']['final-screenshot']['details']['data']);
        $this->assertSame(98000, $result['audits']['final-screenshot']['details']['dataStrippedBytes']);
        $this->assertSame(1, $result['audits']['final-screenshot']['score']);
    }

    public function test_an_audit_with_no_embedded_data_is_untouched(): void
    {
        $result = $this->strip([
            'audits' => [
                'performance' => ['score' => 0.98, 'details' => ['type' => 'metric']],
            ],
        ]);

        $this->assertSame(['score' => 0.98, 'details' => ['type' => 'metric']], $result['audits']['performance']);
    }

    public function test_a_report_with_no_audits_key_does_not_error(): void
    {
        $this->assertSame([], $this->strip([]));
    }
}

<?php

namespace Tests\Unit\Mcp;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The MCP catalogue is generated on demand (`php artisan mcp:catalogue`).
 * These tests check the renderer, not a committed copy of the page.
 */
class CatalogueTest extends TestCase
{
    private string $html = '';

    protected function setUp(): void
    {
        parent::setUp();

        $temp = tempnam(sys_get_temp_dir(), 'mcp-catalogue-');

        try {
            $this->assertSame(0, Artisan::call('mcp:catalogue', ['--out' => $temp]));
            $this->html = (string)file_get_contents($temp);
        } finally {
            @unlink($temp);
        }
    }

    public function test_the_catalogue_lists_every_tool(): void
    {
        $expected = count(require base_path('app/Mcp/Tools/Api/generated-tools.php')) + 2;

        $this->assertSame(
            $expected,
            preg_match_all('/class="tool /', $this->html),
            'The catalogue does not list every registered tool'
        );

        // A tool the page names but the server does not register would send a
        // reader to call something that answers "tool not found".
        foreach (['project_suspend', 'project_clone', 'mysql_database_get', 'metrics_latest'] as $name) {
            $this->assertStringContainsString('data-name="' . $name . '"', $this->html, "{$name} is missing from the catalogue");
        }
    }

    public function test_the_catalogue_is_a_self_contained_document(): void
    {
        $this->assertStringStartsWith('<!doctype html>', $this->html);
        $this->assertStringContainsString('</html>', $this->html);

        // Google Fonts is the one external host allowed; anything else would
        // make the page depend on something that may not be reachable.
        preg_match_all('#https?://[^/"\s]+#', $this->html, $matches);
        $hosts = array_values(array_unique(array_diff(
            $matches[0],
            ['https://fonts.googleapis.com', 'https://fonts.gstatic.com', 'https://example.com.']
        )));

        $this->assertSame([], $hosts, 'The catalogue references an external host');
    }
}

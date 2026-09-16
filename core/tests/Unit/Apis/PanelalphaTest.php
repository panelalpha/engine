<?php

namespace Tests\Unit\Apis;

use App\Lib\Apis\PanelAlpha;
use Tests\TestCase;

class PanelalphaTest extends TestCase
{
    public function test_url_joins_base_and_path(): void
    {
        $this->assertSame(
            'https://hub.example.test/api/without-dns/sites',
            PanelAlpha::url('https://hub.example.test', 'api/without-dns/sites')
        );
    }

    public function test_url_strips_trailing_and_leading_slashes(): void
    {
        $this->assertSame(
            'https://hub.example.test/api/v1/events',
            PanelAlpha::url('https://hub.example.test/', '/api/v1/events')
        );
    }

    public function test_empty_base_yields_empty_string_not_a_relative_url(): void
    {
        $this->assertSame('', PanelAlpha::url('', '/api/v1/events'));
        $this->assertSame('', PanelAlpha::url('   ', 'anything'));
    }
}

<?php

namespace Tests\Unit;

use App\Http\Requests\DomainUpdateRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DomainUpdateRequestTest extends TestCase
{
    private function rules(): array
    {
        return (new DomainUpdateRequest())->rules();
    }

    public function test_document_root_alone_passes(): void
    {
        $this->assertFalse(Validator::make(['document_root' => '/public_html'], $this->rules())->fails());
    }

    public function test_redirect_enabled_alone_passes(): void
    {
        $this->assertFalse(Validator::make(['redirect_enabled' => false], $this->rules())->fails());
    }

    public function test_force_https_redirect_alone_passes(): void
    {
        $this->assertFalse(Validator::make(['force_https_redirect' => true], $this->rules())->fails());
    }

    public function test_an_empty_body_passes_validation(): void
    {
        $this->assertFalse(Validator::make([], $this->rules())->fails());
    }

    public function test_a_full_body_still_passes(): void
    {
        $this->assertFalse(Validator::make([
            'document_root' => '/public_html',
            'redirect_enabled' => true,
            'redirect_url' => 'https://example.com',
            'force_https_redirect' => true,
        ], $this->rules())->fails());
    }

    public function test_a_non_boolean_redirect_enabled_still_fails(): void
    {
        $this->assertTrue(Validator::make(['redirect_enabled' => 'yes-please'], $this->rules())->fails());
    }

    public function test_a_document_root_escaping_the_account_still_fails(): void
    {
        $this->assertTrue(Validator::make(['document_root' => '/public_html/../../etc'], $this->rules())->fails());
    }
}

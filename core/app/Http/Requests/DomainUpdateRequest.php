<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DomainUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('aliases')) {
            $this->merge(['aliases' => array_map('strtolower', $this->aliases ?? [])]);
        }
    }

    public function rules(): array
    {
        return [
            // Concatenated straight into the vhost's DocumentRoot
            // ("/home/<user>" . $document_root), so it has to be a path under
            // the account and nothing else. A full URL lands in the config as
            // "/var/wwwhttps:/example.com/wp-admin", which Apache then denies
            // for the whole account; a ".." segment would point the site
            // outside the account altogether.
            'document_root' => [
                'sometimes',
                'string',
                'max:4096',
                'regex:/^\\/[^\\0]*$/',
                'not_regex:/(?:^|\\/)\\.\\.(?:\\/|$)/',
            ],
            'redirect_enabled' => 'sometimes|boolean',
            'redirect_url' => 'url|nullable',
            'force_https_redirect' => 'sometimes|boolean',
            'aliases' => 'array|nullable',
            'aliases.*' => 'string|nullable',
        ];
    }
}

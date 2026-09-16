<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DomainStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        if ($this->has('domain')) {
            $data['domain'] = strtolower($this->domain);
        }
        if ($this->has('parent_domain')) {
            $data['parent_domain'] = strtolower($this->parent_domain);
        }
        if ($this->has('aliases')) {
            $data['aliases'] = array_map('strtolower', $this->aliases ?? []);
        }
        // The documented enum value is `subdomain`; `sub` is the internal
        // storage value the rest of the codebase (DomainController, the
        // domains:create command) switches and filters on.
        if ($this->input('type') === 'subdomain') {
            $data['type'] = 'sub';
        }
        if (!empty($data)) {
            $this->merge($data);
        }
    }

    public function rules(): array
    {
        return [
            'domain' => 'string|required|regex:/^(?!:\/\/)(?=.{1,255}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i',
            'parent_domain' => 'string|nullable|regex:/^(?!:\/\/)(?=.{1,255}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i',
            'type' => 'string|required|in:addon,sub',
            'no_ssl' => 'boolean|nullable',
            'aliases' => 'array|nullable',
            'aliases.*' => 'string|nullable',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Lib\Deploy\Inspect\SourceResolver;
use App\Lib\Deploy\Source\GitUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SourceInspectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $source = $this->input('source');
        if (is_string($source)) {
            $this->merge(['source' => trim($source)]);
        }
    }

    public function rules(): array
    {
        return [
            'source' => 'required|string|max:2048',
            'type' => ['nullable', 'string', Rule::in(SourceResolver::TYPES)],
            'branch' => 'nullable|string|max:255',
            'subdirectory' => 'nullable|string|max:512',
            // Shape only; the grammar is checked by DeployPlanInput, which is
            // the same check the deploy endpoints run on the same field.
            'stages' => 'nullable|array',
            'recipe' => 'nullable|string|max:64',
            'git_token' => [
                'nullable',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $source = $this->input('source');
                    if (!is_string($source)
                        || !GitUrl::isHttpsWithoutCredentials(SourceResolver::normaliseGitUrl($source))
                    ) {
                        $fail('A Git token requires an HTTPS repository URL without embedded credentials.');
                    }
                },
            ],
        ];
    }
}

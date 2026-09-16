<?php

namespace App\Http\Requests\Git;

use App\System\Project\Git\Ref as GitRef;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GitCommitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => 'nullable|string',
            'branch' => 'nullable|string',
            'limit' => 'nullable|integer|min:1',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $branch = $validator->getData()['branch'] ?? null;
            if (is_string($branch) && $branch !== '' && !GitRef::isValidName($branch)) {
                $validator->errors()->add('branch', 'Invalid git ref name.');
            }
        });
    }
}

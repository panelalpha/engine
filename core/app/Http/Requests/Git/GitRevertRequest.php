<?php

namespace App\Http\Requests\Git;

use App\System\Project\Git\Ref as GitRef;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GitRevertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => 'nullable|string',
            'ref' => 'nullable|string',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $ref = $validator->getData()['ref'] ?? null;
            if (!is_string($ref) || $ref === '' || $ref === 'HEAD') {
                return;
            }
            if (!GitRef::isValidName($ref)) {
                $validator->errors()->add('ref', 'Invalid git ref name.');
            }
        });
    }
}

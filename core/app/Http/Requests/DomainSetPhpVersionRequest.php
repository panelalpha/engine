<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DomainSetPhpVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => 'string|required',
        ];
    }
}

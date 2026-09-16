<?php

namespace App\Http\Requests\Ip;

use Illuminate\Foundation\Http\FormRequest;

class AddSubnetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ip' => 'required|ip',
            'mask' => 'required|integer',
            'is_shared' => 'nullable|boolean',
        ];
    }
}

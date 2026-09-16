<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MysqlUsePmaSsoTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => 'string|required',
        ];
    }
}

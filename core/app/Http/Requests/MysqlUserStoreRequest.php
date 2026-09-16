<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MysqlUserStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'name' => 'string|required|regex:/^[A-Za-z_-][A-Za-z0-9_-]*$/|min:1|max:32',
            'password' => [
                'string',
                'required',
                'min:8',
                'max:255',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages()
    {
        return [
            'name.max' => 'MySQL user name cannot exceed 32 characters (MySQL limit).',
            'name.regex' => 'MySQL user name must start with a letter or underscore and can only contain letters, numbers, and underscores.',
            'password.min' => 'Password must be at least 8 characters long.',
        ];
    }
}

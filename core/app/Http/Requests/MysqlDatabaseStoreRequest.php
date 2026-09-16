<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MysqlDatabaseStoreRequest extends FormRequest
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
            'name' => 'string|required|regex:/^[A-Za-z_-][A-Za-z0-9_-]*$/|min:1|max:64',
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
            'name.max' => 'Database name cannot exceed 64 characters (MySQL limit).',
            'name.regex' => 'Database name must start with a letter or underscore and can only contain letters, numbers, and underscores.',
        ];
    }
}

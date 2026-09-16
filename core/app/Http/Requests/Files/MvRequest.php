<?php

namespace App\Http\Requests\Files;

use Illuminate\Foundation\Http\FormRequest;

class MvRequest extends FormRequest
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
            'source_path' => 'string|required',
            'dest_path' => 'string|required',
        ];
    }
}

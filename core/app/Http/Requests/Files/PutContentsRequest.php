<?php

namespace App\Http\Requests\Files;

use Illuminate\Foundation\Http\FormRequest;

class PutContentsRequest extends FormRequest
{
    /**
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules()
    {
        return [
            'path' => 'string|required',
            'contents' => 'string|required',
        ];
    }
}

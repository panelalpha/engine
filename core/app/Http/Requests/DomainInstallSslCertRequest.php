<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DomainInstallSslCertRequest extends FormRequest
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
            'cert' => 'string|required',
            'key' => 'string|required',
            'ca' => 'string|nullable'
        ];
    }
}

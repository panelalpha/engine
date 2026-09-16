<?php

namespace App\Http\Requests\Ip;

use Illuminate\Foundation\Http\FormRequest;

class AssignIpRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => 'int',
            'username' => 'string',
            'ip_subnet_id' => 'required|int',
            'ip_address' => 'required|ip',
        ];
    }
}

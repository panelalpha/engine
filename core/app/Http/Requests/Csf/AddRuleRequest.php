<?php

namespace App\Http\Requests\Csf;

use Illuminate\Foundation\Http\FormRequest;

class AddRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'protocol' => 'nullable|string|in:tcp,udp',
            'direction' => 'nullable|string|in:in,out',
            'port_prefix' => 'nullable|string|in:s=,d=',
            'port' => 'nullable|string',
            'target_prefix' => 'nullable|string|in:s=,d=,u=',
            'target' => 'required|string',
            'comment' => 'nullable|string',
        ];
    }
}

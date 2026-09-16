<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @method array{
 *   args: array
 * } validated($key = null, $default = null)
 */
class WpCliCommandRunRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'args' => 'required|array',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CronJobStoreRequest extends FormRequest
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
            'command' => 'string|required',
            'minute' => 'string|required',
            'hour' => 'string|required',
            'day_of_month' => 'string|required',
            'month' => 'string|required',
            'day_of_week' => 'string|required',
        ];
    }
}

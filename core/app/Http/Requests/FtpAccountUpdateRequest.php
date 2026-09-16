<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class FtpAccountUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function getQuota(): ?int
    {
        if (!empty($this->get('unlimited_quota'))) {
            return null;
        }
        return (int)$this->get('quota');
    }

    /**
     * This is a partial update: quota/unlimited_quota are optional, and an
     * omitted pair must mean "leave the quota as it is", not "set it to 0"
     * -- getQuota() alone can't tell those apart, since it always returns
     * a concrete int or null.
     */
    public function quotaProvided(): bool
    {
        return $this->has('quota') || $this->has('unlimited_quota');
    }

    public function rules(): array
    {
        return [
            'password' => 'nullable|string|min:8|max:255',
            'unlimited_quota' => 'boolean',
            'quota' => 'int|nullable|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'password.min' => 'Password must be at least 8 characters long.',
            'password.max' => 'Password cannot be longer than 255 characters.',
            'quota.min' => 'Quota must be a positive number.',
        ];
    }
}

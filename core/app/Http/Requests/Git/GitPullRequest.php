<?php

namespace App\Http\Requests\Git;

use Illuminate\Foundation\Http\FormRequest;

class GitPullRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => 'nullable|string',
            'strategy' => 'nullable|in:ff,force,push_first',
        ];
    }
}

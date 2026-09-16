<?php

namespace App\Http\Requests;

use App\Models\User;
use App\System\Projects;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProjectStagingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('domain')) {
            $this->merge(['domain' => strtolower($this->domain)]);
        }
        if ($this->has('new_username')) {
            $this->merge(['new_username' => strtolower($this->new_username)]);
        }
    }

    public function rules(): array
    {
        return [
            'new_username' => 'sometimes|nullable|string|alpha_num:ascii|regex:/^[a-z]{1}/|lowercase|between:3,15',
            'domain' => 'sometimes|nullable|string|regex:/^(?!:\/\/)(?=.{1,255}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $username = $this->route('username');
            if (!is_string($username) || $username === '') {
                return;
            }

            $source = User::findByUsername($username);
            if ($source === null) {
                return;
            }

            foreach (self::createProblems($source) as $problem) {
                $validator->errors()->add($problem['field'], $problem['message']);
            }
        });
    }

    public static function assertNotPending(User $user): void
    {
        Projects::assertNotPending($user);
    }

    public static function assertCanCreate(User $source): void
    {
        Projects::assertCanCreate($source);
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    public static function createProblems(User $source): array
    {
        return Projects::createProblems($source);
    }
}

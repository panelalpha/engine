<?php

namespace App\Http\Requests;

use App\Models\User;
use App\System\Projects;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProjectPushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target' => 'required|string',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $username = $this->route('username');
            $target = $this->input('target');
            if (!is_string($username) || $username === '' || !is_string($target) || $target === '') {
                return;
            }

            $from = User::findByUsername($username);
            $to = User::findByUsername($target);
            if ($from === null || $to === null) {
                return;
            }

            foreach (self::pushProblems($from, $to) as $problem) {
                $validator->errors()->add($problem['field'], $problem['message']);
            }
        });
    }

    public static function assertCanPush(User $from, User $to): void
    {
        Projects::assertCanPush($from, $to);
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    public static function pushProblems(User $from, User $to): array
    {
        return Projects::pushProblems($from, $to);
    }
}

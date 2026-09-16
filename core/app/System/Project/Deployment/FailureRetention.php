<?php

namespace App\System\Project\Deployment;

use App\Models\User as ModelsUser;
use Illuminate\Support\Facades\Log;

/**
 * ADR-0003: a failed Deploy keeps the Project; only explicit Delete tears it down.
 */
final class FailureRetention
{
    public static function retainAfterDeployFailure(ModelsUser $user, string $message): void
    {
        try {
            $user->setDetails([
                'error' => $message,
                'deployment_status' => 'failed',
            ]);
            $user->save();
        } catch (\Throwable $e) {
            Log::warning(
                "Failed to persist deploy failure state for {$user->username}: {$e->getMessage()}",
            );
        }
    }

    public static function retainAfterDeployCancelled(ModelsUser $user, string $message): void
    {
        try {
            $user->setDetails([
                'error' => $message,
                'deployment_status' => 'failed',
            ]);
            $user->save();
        } catch (\Throwable $e) {
            Log::warning(
                "Failed to persist deploy cancellation state for {$user->username}: {$e->getMessage()}",
            );
        }
    }
}

<?php

namespace App\Lib\Deploy\Platform;

/**
 * The recipe the deploy currently under way was told to use.
 *
 * A sibling of {@see DeployPlanContext} and it exists for the same reason:
 * the pipeline reaches the container through `$user->project()`,
 * both of which build a new object every call, so there is no chain to hand a
 * request-scoped choice down. It is registered as a singleton, lives as long
 * as the container that holds it, and dies with the response.
 *
 * Nothing is persisted, for the same reason a plan is not: the choice belongs
 * to the request that sent it. A rebuild that wants the same recipe sends it
 * again, and a deploy that sends none gets detection back — which is the whole
 * recovery story for a recipe that turned out to be the wrong one. What the
 * account does keep is the recipe the last deploy actually used, frozen as
 * `deploy_platform` and reported by an inspection, so a caller can always read
 * back what to send.
 *
 * No Laravel dependencies of its own — callers resolve it from the container.
 */
final class RecipeChoiceContext
{
    private ?string $recipe = null;

    public function set(?string $recipe): void
    {
        $recipe = is_string($recipe) ? trim($recipe) : null;
        $this->recipe = $recipe === '' ? null : $recipe;
    }

    public function get(): ?string
    {
        return $this->recipe;
    }

    public function clear(): void
    {
        $this->recipe = null;
    }
}

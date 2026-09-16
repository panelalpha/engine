<?php

namespace App\Lib\Deploy\Platform;

/**
 * The plan the deploy currently under way was asked to follow.
 *
 * `$user->project()` builds a new object on every call, so `preCheckUserApp()`
 * and `cloneUserApp()` in one request hold two different `Dind` instances and
 * cannot carry a plan between them. It is bound as a singleton instead, which
 * is exactly the request's lifetime. Nothing is persisted: deploy again without
 * a plan and the defaults are back.
 */
final class DeployPlanContext
{
    private ?DeployPlan $plan = null;

    public function set(?DeployPlan $plan): void
    {
        $this->plan = $plan;
    }

    public function get(): ?DeployPlan
    {
        return $this->plan;
    }

    public function clear(): void
    {
        $this->plan = null;
    }
}

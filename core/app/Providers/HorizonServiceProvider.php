<?php

namespace App\Providers;

use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();
    }

    protected function gate(): void
    {
        // Engine has no admin SPA. The dashboard must stay closed.
    }

    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(static fn (): bool => false);
    }
}

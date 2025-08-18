<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Only the workspace owner sees the queue dashboard outside local development, and nobody
     * does on the public demo, where anyone can sign in as the owner with one click.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user = null) => ! config('invoices.demo.enabled') && ($user?->isOwner() ?? false));
    }
}

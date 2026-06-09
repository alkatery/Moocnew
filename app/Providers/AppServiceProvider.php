<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contexts\Identity\Domain\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // API documentation (Scramble): open locally, otherwise restricted to
        // users who may view analytics (Super Admin / Supervisor).
        Gate::define('viewApiDocs', function (?User $user): bool {
            return $this->app->environment('local')
                || ($user !== null && $user->can(Permission::ViewAnalytics->value));
        });

        // Horizon queue dashboard: same restriction in non-local environments.
        Horizon::auth(function ($request): bool {
            return $this->app->environment('local')
                || ($request->user() !== null && $request->user()->can(Permission::ViewAnalytics->value));
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Contexts\Identity\Infrastructure\Providers;

use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Identity context's authorization rules. Individual permissions
 * are registered as Gates by spatie/laravel-permission; here we add the
 * Super Admin override so that the top role implicitly passes every check
 * (PRD §4).
 */
final class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->hasRole(Role::SuperAdmin->value) ? true : null;
        });
    }
}

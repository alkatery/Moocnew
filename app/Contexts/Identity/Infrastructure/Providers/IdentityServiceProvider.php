<?php

declare(strict_types=1);

namespace App\Contexts\Identity\Infrastructure\Providers;

use App\Contexts\Identity\Domain\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
        Gate::before(function (?User $user, string $ability): ?bool {
            return $user?->hasRole(Role::SuperAdmin->value) ? true : null;
        });

        // The verification mail should land the user on the SPA, which then
        // replays the (still-signed) API URL. This keeps the signature valid
        // for the API route while giving a human-friendly landing page.
        VerifyEmail::createUrlUsing(function (User $user): string {
            $apiUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes((int) config('auth.verification.expire', 60)),
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ],
            );

            $frontend = rtrim((string) config('app.frontend_url'), '/');

            return "{$frontend}/verify-email?verify_url=".urlencode($apiUrl);
        });
    }
}

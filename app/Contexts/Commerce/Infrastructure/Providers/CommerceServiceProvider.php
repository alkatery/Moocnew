<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Infrastructure\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Commerce bounded context (PRD §1, §5.د). The routes are always
 * registered but sit behind the `payments.enabled` middleware, so the whole
 * surface is dormant (404) until an admin turns payments on — no redeploy,
 * no schema change. The heavy machinery (orders, ledger, payouts, ZATCA)
 * arrives in Phase 2 within this same context.
 */
final class CommerceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'payments.enabled'])
            ->prefix('api/v1/commerce')
            ->name('api.commerce.')
            ->group(base_path('routes/commerce.php'));
    }
}

<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Infrastructure\Providers;

use App\Contexts\Commerce\Domain\PaymentGateway;
use App\Contexts\Commerce\Infrastructure\Gateway\FakePaymentGateway;
use App\Contexts\Commerce\Infrastructure\Gateway\MoyasarPaymentGateway;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Commerce bounded context (PRD §1, §5.د). The routes are always
 * registered but sit behind the `payments.enabled` middleware, so the whole
 * surface is dormant (404) until an admin turns payments on — no redeploy,
 * no schema change.
 *
 * The payment gateway binds to Moyasar when API keys are configured, and to
 * a working fake gateway otherwise (local/dev/test).
 */
final class CommerceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function (): PaymentGateway {
            $secret = config('commerce.moyasar.secret_key');

            if (! empty($secret)) {
                return new MoyasarPaymentGateway(
                    (string) $secret,
                    (string) config('commerce.moyasar.webhook_secret'),
                );
            }

            return new FakePaymentGateway(
                (string) (config('commerce.moyasar.webhook_secret') ?: 'fake-webhook-secret'),
            );
        });
    }

    public function boot(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'payments.enabled'])
            ->prefix('api/v1/commerce')
            ->name('api.commerce.')
            ->group(base_path('routes/commerce.php'));
    }
}

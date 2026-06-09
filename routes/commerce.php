<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Commerce\CommerceStatusController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Commerce Routes (Phase 2) — gated by payments.enabled
|--------------------------------------------------------------------------
|
| Loaded under /api/v1/commerce with the `payments.enabled` middleware, so
| every route here is invisible (404) while the platform runs in free mode
| (PRD §1). Phase 2 adds orders, checkout, coupons, payouts, refunds and
| ZATCA invoicing alongside the status endpoint.
|
*/

Route::get('status', CommerceStatusController::class)->name('status');

<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Commerce\CheckoutController;
use App\Http\Controllers\Api\V1\Commerce\CommerceStatusController;
use App\Http\Controllers\Api\V1\Commerce\CouponController;
use App\Http\Controllers\Api\V1\Commerce\OrderController;
use App\Http\Controllers\Api\V1\Commerce\PayoutController;
use App\Http\Controllers\Api\V1\Commerce\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Commerce Routes (Phase 2) — gated by payments.enabled
|--------------------------------------------------------------------------
|
| Loaded under /api/v1/commerce with the `payments.enabled` middleware, so
| every route here is invisible (404) while the platform runs in free mode
| (PRD §1). ZATCA e-invoicing is deferred.
|
*/

Route::get('status', CommerceStatusController::class)->name('status');

// Checkout & orders.
Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout');
Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
Route::post('orders/{order}/refund', [OrderController::class, 'refund'])->name('orders.refund');

// Coupons (admin).
Route::get('coupons', [CouponController::class, 'index'])->name('coupons.index');
Route::post('coupons', [CouponController::class, 'store'])->name('coupons.store');

// Instructor wallet & payouts.
Route::get('wallet', [WalletController::class, 'show'])->name('wallet');
Route::get('payouts', [PayoutController::class, 'index'])->name('payouts.index');
Route::post('payouts', [PayoutController::class, 'store'])->name('payouts.store');
Route::post('payouts/{payout}/approve', [PayoutController::class, 'approve'])->name('payouts.approve');
Route::post('payouts/{payout}/reject', [PayoutController::class, 'reject'])->name('payouts.reject');
Route::post('payouts/{payout}/pay', [PayoutController::class, 'pay'])->name('payouts.pay');

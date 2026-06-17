<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Application;

use App\Contexts\Commerce\Infrastructure\Persistence\Coupon;
use App\Contexts\Commerce\Infrastructure\Persistence\CouponRedemption;
use App\Contexts\Commerce\Infrastructure\Persistence\Order;
use App\Contexts\Shared\Domain\Money;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Validates and applies discount coupons (PRD §5.د).
 */
final class CouponService
{
    /**
     * Resolve a redeemable coupon by code, or throw a validation error.
     */
    public function resolve(string $code, User $user): Coupon
    {
        $coupon = Coupon::query()->where('code', $code)->first();

        if ($coupon === null || ! $coupon->isRedeemable()) {
            throw ValidationException::withMessages(['coupon' => ['الكوبون غير صالح أو منتهٍ.']]);
        }

        $alreadyUsed = CouponRedemption::query()
            ->where('coupon_id', $coupon->id)
            ->where('user_id', $user->getKey())
            ->exists();

        if ($alreadyUsed) {
            throw ValidationException::withMessages(['coupon' => ['تم استخدام هذا الكوبون مسبقاً.']]);
        }

        return $coupon;
    }

    public function discountFor(Coupon $coupon, Money $price): Money
    {
        return $coupon->type->discountFor($price, $coupon->value);
    }

    /**
     * Record a redemption against an order and advance the usage counter.
     */
    public function redeem(Coupon $coupon, User $user, Order $order): void
    {
        CouponRedemption::query()->create([
            'coupon_id' => $coupon->id,
            'user_id' => $user->getKey(),
            'order_id' => $order->id,
        ]);

        $coupon->increment('used_count');
    }
}

<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Application;

use App\Contexts\Catalog\Domain\Course\PricingType;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Commerce\Domain\OrderStatus;
use App\Contexts\Commerce\Domain\PaymentGateway;
use App\Contexts\Commerce\Domain\PaymentStatus;
use App\Contexts\Commerce\Infrastructure\Persistence\Order;
use App\Contexts\Commerce\Infrastructure\Persistence\Payment;
use App\Contexts\Shared\Domain\Money;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates an order for a paid course and initiates payment (PRD §5.د).
 * Idempotent: a learner has at most one pending order per course, so a
 * retried checkout returns the existing order rather than duplicating it.
 */
final class CheckoutService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly CouponService $coupons,
    ) {}

    public function checkout(User $user, Course $course, ?string $couponCode = null): Order
    {
        if ($course->pricing_type === PricingType::Free || $course->price_minor === 0) {
            throw ValidationException::withMessages(['course' => ['هذه الدورة مجانية ولا تحتاج دفعاً.']]);
        }

        $currency = (string) config('commerce.currency', 'SAR');
        $idempotencyKey = "u{$user->getKey()}:c{$course->getKey()}:pending";

        return DB::transaction(function () use ($user, $course, $couponCode, $currency, $idempotencyKey): Order {
            $existing = Order::query()
                ->where('idempotency_key', $idempotencyKey)
                ->where('status', OrderStatus::Pending->value)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $subtotal = Money::ofMinor($course->price_minor, $currency);
            $discount = Money::zero($currency);
            $coupon = null;

            if ($couponCode !== null && $couponCode !== '') {
                $coupon = $this->coupons->resolve($couponCode, $user);
                $discount = $this->coupons->discountFor($coupon, $subtotal);
            }

            $total = $subtotal->subtract($discount);

            $order = Order::query()->create([
                'user_id' => $user->getKey(),
                'course_id' => $course->getKey(),
                'status' => OrderStatus::Pending,
                'subtotal_minor' => $subtotal->minor,
                'discount_minor' => $discount->minor,
                'total_minor' => $total->minor,
                'currency' => $currency,
                'coupon_id' => $coupon?->id,
                'idempotency_key' => $idempotencyKey,
            ]);

            if ($coupon !== null) {
                $this->coupons->redeem($coupon, $user, $order);
            }

            $charge = $this->gateway->charge(
                $total->minor,
                $currency,
                "Course #{$course->getKey()}",
                ['order_id' => $order->id],
            );

            Payment::query()->create([
                'order_id' => $order->id,
                'gateway' => $this->gateway->name(),
                'gateway_ref' => $charge->reference,
                'status' => PaymentStatus::Initiated,
                'amount_minor' => $total->minor,
                'currency' => $currency,
            ]);

            return $order;
        });
    }
}

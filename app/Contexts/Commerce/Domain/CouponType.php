<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Domain;

use App\Contexts\Shared\Domain\Money;

/**
 * Discount kinds (PRD §5.د): a percentage of the price, or a fixed amount
 * in minor units.
 */
enum CouponType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';

    /**
     * Compute the discount this coupon yields on a given price.
     *
     * @param  int  $value  percent (0-100) for Percentage, minor units for Fixed
     */
    public function discountFor(Money $price, int $value): Money
    {
        return match ($this) {
            self::Percentage => $price->percentage((float) $value),
            self::Fixed => Money::ofMinor(min($value, $price->minor), $price->currency),
        };
    }
}

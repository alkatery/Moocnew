<?php

declare(strict_types=1);

namespace App\Contexts\Catalog\Domain\Course;

/**
 * How a course is priced (PRD §1). The amount itself lives in
 * `price_minor` as an integer number of minor units (halalas/cents) —
 * never a float. Pricing is stored regardless of the payments flag; the
 * Enrollment context decides whether it is actually charged.
 */
enum PricingType: string
{
    case Free = 'free';
    case OneTime = 'one_time';
    case Subscription = 'subscription';

    public function isPaid(): bool
    {
        return $this !== self::Free;
    }
}

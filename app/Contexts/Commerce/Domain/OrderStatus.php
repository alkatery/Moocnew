<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Domain;

/**
 * Order lifecycle (PRD §5.د).
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Paid, self::Failed],
            self::Paid => [self::Refunded],
            self::Failed => [self::Pending],
            self::Refunded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}

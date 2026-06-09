<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Domain;

/**
 * Enrollment lifecycle (PRD §5.ج): a free enrollment starts active; a paid
 * one (only reachable when the Commerce flag is on) starts pending until an
 * order is settled. From active it may complete, expire, or be refunded.
 */
enum EnrollmentStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Completed = 'completed';
    case Expired = 'expired';
    case Refunded = 'refunded';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Active, self::Refunded],
            self::Active => [self::Completed, self::Expired, self::Refunded],
            self::Completed => [self::Refunded, self::Expired],
            self::Expired => [self::Active],
            self::Refunded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function grantsAccess(): bool
    {
        return $this === self::Active || $this === self::Completed;
    }
}

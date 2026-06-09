<?php

declare(strict_types=1);

namespace App\Contexts\Shared\Domain;

use InvalidArgumentException;

/**
 * An immutable monetary amount stored as an integer number of minor units
 * (halalas/cents). Money is NEVER represented as a float (binding rule,
 * PRD §6). All arithmetic stays in integers; percentage math rounds to the
 * nearest minor unit.
 */
final readonly class Money
{
    public function __construct(
        public int $minor,
        public string $currency = 'SAR',
    ) {
        if ($minor < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }
    }

    public static function zero(string $currency = 'SAR'): self
    {
        return new self(0, $currency);
    }

    public static function ofMinor(int $minor, string $currency = 'SAR'): self
    {
        return new self($minor, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self(max(0, $this->minor - $other->minor), $this->currency);
    }

    /**
     * A percentage of this amount, rounded to the nearest minor unit.
     */
    public function percentage(float $percent): self
    {
        $value = (int) round($this->minor * $percent / 100);

        return new self(max(0, $value), $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor > $other->minor;
    }

    public function equals(self $other): bool
    {
        return $this->currency === $other->currency && $this->minor === $other->minor;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException("Currency mismatch: {$this->currency} vs {$other->currency}.");
        }
    }
}

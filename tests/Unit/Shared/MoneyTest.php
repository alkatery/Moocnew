<?php

declare(strict_types=1);

use App\Contexts\Shared\Domain\Money;

it('adds and subtracts in minor units', function () {
    $a = Money::ofMinor(10000);
    $b = Money::ofMinor(2500);

    expect($a->add($b)->minor)->toBe(12500);
    expect($a->subtract($b)->minor)->toBe(7500);
});

it('never goes negative on subtraction', function () {
    expect(Money::ofMinor(1000)->subtract(Money::ofMinor(5000))->minor)->toBe(0);
});

it('computes a percentage rounded to the nearest minor unit', function () {
    // 20% of 49900 = 9980
    expect(Money::ofMinor(49900)->percentage(20)->minor)->toBe(9980);
    // 15% of 333 = 49.95 -> 50
    expect(Money::ofMinor(333)->percentage(15)->minor)->toBe(50);
});

it('rejects a negative amount', function () {
    new Money(-1);
})->throws(InvalidArgumentException::class);

it('rejects mixing currencies', function () {
    Money::ofMinor(100, 'SAR')->add(Money::ofMinor(100, 'USD'));
})->throws(InvalidArgumentException::class);

it('knows zero and positive', function () {
    expect(Money::zero()->isZero())->toBeTrue();
    expect(Money::ofMinor(1)->isPositive())->toBeTrue();
});

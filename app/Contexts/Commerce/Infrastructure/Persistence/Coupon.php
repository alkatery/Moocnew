<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Infrastructure\Persistence;

use App\Contexts\Commerce\Domain\CouponType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $code
 * @property CouponType $type
 * @property int $value
 * @property int|null $max_uses
 * @property int $used_count
 * @property Carbon|null $expires_at
 * @property bool $active
 */
final class Coupon extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'max_uses', 'used_count', 'expires_at', 'active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'integer',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'expires_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function isRedeemable(): bool
    {
        if (! $this->active) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return $this->max_uses === null || $this->used_count < $this->max_uses;
    }
}

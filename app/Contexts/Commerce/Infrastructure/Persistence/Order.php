<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Infrastructure\Persistence;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Commerce\Domain\OrderStatus;
use App\Contexts\Shared\Domain\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $user_id
 * @property int $course_id
 * @property OrderStatus $status
 * @property int $subtotal_minor
 * @property int $discount_minor
 * @property int $total_minor
 * @property string $currency
 */
final class Order extends Model
{
    protected $fillable = [
        'user_id', 'course_id', 'status', 'subtotal_minor', 'discount_minor',
        'total_minor', 'currency', 'coupon_id', 'idempotency_key', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'total_minor' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function total(): Money
    {
        return Money::ofMinor($this->total_minor, $this->currency);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}

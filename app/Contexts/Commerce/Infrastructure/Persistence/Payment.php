<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Infrastructure\Persistence;

use App\Contexts\Commerce\Domain\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $order_id
 * @property string $gateway
 * @property string|null $gateway_ref
 * @property PaymentStatus $status
 * @property int $amount_minor
 */
final class Payment extends Model
{
    protected $fillable = [
        'order_id', 'gateway', 'gateway_ref', 'status', 'amount_minor', 'currency', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_minor' => 'integer',
            'payload' => 'array',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

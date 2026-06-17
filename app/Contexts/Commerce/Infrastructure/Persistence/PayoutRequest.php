<?php

declare(strict_types=1);

namespace App\Contexts\Commerce\Infrastructure\Persistence;

use App\Contexts\Commerce\Domain\PayoutStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $instructor_id
 * @property int $amount_minor
 * @property PayoutStatus $status
 */
final class PayoutRequest extends Model
{
    protected $fillable = [
        'instructor_id', 'amount_minor', 'currency', 'status', 'note', 'processed_by', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'amount_minor' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Contexts\Engagement\Infrastructure\Persistence;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A learner's running gamification totals: points and learning-day streak.
 *
 * @property int $user_id
 * @property int $points
 * @property int $current_streak
 * @property int $longest_streak
 * @property Carbon|null $last_active_on
 */
final class LearnerStat extends Model
{
    protected $fillable = [
        'user_id',
        'points',
        'current_streak',
        'longest_streak',
        'last_active_on',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'current_streak' => 'integer',
            'longest_streak' => 'integer',
            'last_active_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Contexts\Learning\Infrastructure\Persistence;

use App\Contexts\Learning\Domain\StudyPlanStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A learner's personal study plan: a list of courses plus a reminder
 * cadence the platform uses to keep nudging them until the plan is done.
 *
 * @property int $user_id
 * @property string $title
 * @property int $cadence_days
 * @property Carbon|null $target_date
 * @property StudyPlanStatus $status
 * @property Carbon|null $last_reminded_at
 * @property Carbon|null $completed_at
 */
final class StudyPlan extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'cadence_days',
        'target_date',
        'status',
        'last_reminded_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'cadence_days' => 'integer',
            'target_date' => 'date',
            'status' => StudyPlanStatus::class,
            'last_reminded_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<StudyPlanItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StudyPlanItem::class)->orderBy('position');
    }
}

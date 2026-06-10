<?php

declare(strict_types=1);

namespace App\Contexts\Learning\Infrastructure\Persistence;

use App\Contexts\Learning\Domain\PathEnrollmentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A learner's membership in a learning path.
 *
 * @property int $user_id
 * @property int $learning_path_id
 * @property PathEnrollmentStatus $status
 * @property Carbon|null $completed_at
 */
final class PathEnrollment extends Model
{
    protected $fillable = [
        'user_id',
        'learning_path_id',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PathEnrollmentStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<LearningPath, $this>
     */
    public function path(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class, 'learning_path_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

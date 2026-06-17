<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Infrastructure\Persistence;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Domain\EnrollmentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A user's enrollment in a course (PRD §5.ج).
 *
 * @property int $user_id
 * @property int $course_id
 * @property EnrollmentStatus $status
 * @property Carbon|null $access_expires_at
 * @property int|null $order_id
 * @property int $progress_percent
 */
final class Enrollment extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'status',
        'access_expires_at',
        'order_id',
        'progress_percent',
        'enrolled_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'access_expires_at' => 'datetime',
            'enrolled_at' => 'datetime',
            'completed_at' => 'datetime',
            'progress_percent' => 'integer',
        ];
    }

    /**
     * Whether this enrollment currently grants access to course content:
     * an access-granting status and a non-expired access window.
     */
    public function isActive(): bool
    {
        if (! $this->status->grantsAccess()) {
            return false;
        }

        return $this->access_expires_at === null || $this->access_expires_at->isFuture();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<LessonProgress, $this>
     */
    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }
}

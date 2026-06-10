<?php

declare(strict_types=1);

namespace App\Contexts\Certification\Infrastructure\Persistence;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Learning\Infrastructure\Persistence\LearningPath;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A completion certificate (PRD §5.ح) — for a single course or, since the
 * Learning context, for an entire learning path (exactly one of course_id /
 * learning_path_id is set).
 *
 * @property int $user_id
 * @property int|null $course_id
 * @property int|null $learning_path_id
 * @property string $serial
 * @property string $verification_uuid
 * @property string|null $pdf_path
 * @property Carbon $issued_at
 */
final class Certificate extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'learning_path_id',
        'serial',
        'verification_uuid',
        'pdf_path',
        'issued_at',
    ];

    /**
     * The certified subject's display title (course or path).
     */
    public function subjectTitle(): string
    {
        return $this->course?->title ?? $this->path?->title ?? '';
    }

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'verification_uuid';
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
     * @return BelongsTo<LearningPath, $this>
     */
    public function path(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class, 'learning_path_id');
    }
}

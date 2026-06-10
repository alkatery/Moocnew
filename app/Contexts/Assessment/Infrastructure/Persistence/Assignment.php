<?php

declare(strict_types=1);

namespace App\Contexts\Assessment\Infrastructure\Persistence;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An assignment within a course (PRD §5.هـ).
 *
 * @property int $course_id
 * @property string $title
 * @property Carbon|null $due_at
 * @property int $points
 */
final class Assignment extends Model
{
    protected $fillable = [
        'course_id',
        'section_id',
        'title',
        'description',
        'due_at',
        'points',
        'rubric',
        'weight',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'points' => 'integer',
            'rubric' => 'array',
            'weight' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<AssignmentSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }
}

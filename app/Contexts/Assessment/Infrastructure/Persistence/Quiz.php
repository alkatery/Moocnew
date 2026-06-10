<?php

declare(strict_types=1);

namespace App\Contexts\Assessment\Infrastructure\Persistence;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A quiz: an ordered selection of bank questions with timing, shuffle, a
 * pass mark and an attempt cap (PRD §5.هـ).
 *
 * @property int $id
 * @property int $course_id
 * @property string $title
 * @property int|null $time_limit_minutes
 * @property bool $shuffle
 * @property int|null $max_attempts
 * @property int $pass_mark
 */
final class Quiz extends Model
{
    protected $fillable = [
        'course_id',
        'section_id',
        'title',
        'time_limit_minutes',
        'shuffle',
        'draw_count',
        'max_attempts',
        'pass_mark',
        'weight',
    ];

    protected function casts(): array
    {
        return [
            'shuffle' => 'boolean',
            'draw_count' => 'integer',
            'time_limit_minutes' => 'integer',
            'max_attempts' => 'integer',
            'pass_mark' => 'integer',
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
     * @return BelongsToMany<Question, $this>
     */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'quiz_questions', 'quiz_id', 'question_id')
            ->withPivot('position')
            ->withTimestamps()
            ->orderBy('quiz_questions.position');
    }

    /**
     * @return HasMany<QuizAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }
}

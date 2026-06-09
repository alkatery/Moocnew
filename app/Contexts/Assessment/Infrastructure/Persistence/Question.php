<?php

declare(strict_types=1);

namespace App\Contexts\Assessment\Infrastructure\Persistence;

use App\Contexts\Assessment\Domain\QuestionType;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reusable question in a course's bank (PRD §5.هـ).
 *
 * @property int $id
 * @property int $course_id
 * @property QuestionType $type
 * @property string $body
 * @property array|null $choices
 * @property mixed $correct
 * @property int $points
 */
final class Question extends Model
{
    protected $table = 'question_bank';

    protected $fillable = [
        'course_id',
        'type',
        'body',
        'choices',
        'correct',
        'points',
    ];

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'choices' => 'array',
            'correct' => 'array',
            'points' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}

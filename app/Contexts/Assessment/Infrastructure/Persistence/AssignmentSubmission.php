<?php

declare(strict_types=1);

namespace App\Contexts\Assessment\Infrastructure\Persistence;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A learner's submission plus the instructor's grade and feedback
 * (PRD §5.هـ).
 *
 * @property int $assignment_id
 * @property int $user_id
 * @property int|null $grade
 * @property Carbon $submitted_at
 * @property Carbon|null $graded_at
 */
final class AssignmentSubmission extends Model
{
    protected $fillable = [
        'assignment_id',
        'user_id',
        'content',
        'file_path',
        'grade',
        'rubric_scores',
        'feedback',
        'graded_by',
        'graded_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'grade' => 'integer',
            'rubric_scores' => 'array',
            'graded_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function isGraded(): bool
    {
        return $this->graded_at !== null;
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

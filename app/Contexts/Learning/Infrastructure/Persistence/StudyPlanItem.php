<?php

declare(strict_types=1);

namespace App\Contexts\Learning\Infrastructure\Persistence;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One course inside a personal study plan.
 *
 * @property int $study_plan_id
 * @property int $course_id
 * @property int $position
 */
final class StudyPlanItem extends Model
{
    protected $fillable = [
        'study_plan_id',
        'course_id',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<StudyPlan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(StudyPlan::class, 'study_plan_id');
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Contexts\Catalog\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A course section (PRD §5.ب).
 *
 * @property int $id
 * @property int $course_id
 * @property string $title
 * @property int $position
 */
final class Section extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'position',
    ];

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position');
    }
}

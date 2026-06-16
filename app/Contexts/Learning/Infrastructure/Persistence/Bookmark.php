<?php

declare(strict_types=1);

namespace App\Contexts\Learning\Infrastructure\Persistence;

use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * علامة مرجعية خاصة بالمتعلّم على درس معيّن — مرآة LessonNote بحقلَي
 * user_id و lesson_id فقط، بلا at_seconds أو body (D1).
 *
 * @property int $id
 * @property int $user_id
 * @property int $lesson_id
 */
final class Bookmark extends Model
{
    protected $fillable = [
        'user_id',
        'lesson_id',
    ];

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}

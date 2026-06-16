<?php

declare(strict_types=1);

namespace App\Contexts\Notification\Infrastructure\Persistence;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * إعلان مقرر (C3 — PRD §5.ط).
 *
 * يمثّل هذا النموذج إعلاناً نشره طاقم المقرر ويظهر دائماً
 * في صفحة المقرر للمتعلّمين الملتحقين. الإعلان كيان تواصل
 * لا كيان كتالوج، لذا يقع في سياق Notification.
 *
 * @property int $id
 * @property int $course_id
 * @property int $author_id
 * @property string $title
 * @property string $body
 */
final class CourseAnnouncement extends Model
{
    protected $fillable = [
        'course_id',
        'author_id',
        'title',
        'body',
    ];

    /**
     * المقرر الذي ينتمي إليه الإعلان.
     *
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * عضو الطاقم الذي أنشأ الإعلان.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}

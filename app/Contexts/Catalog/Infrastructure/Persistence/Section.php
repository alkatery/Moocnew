<?php

declare(strict_types=1);

namespace App\Contexts\Catalog\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A course section (PRD §5.ب).
 *
 * @property int $id
 * @property int $course_id
 * @property string $title
 * @property int $position
 * @property Carbon|null $visible_from E2: تاريخ ظهور القسم للطالب؛ null = ظاهر دائماً.
 */
final class Section extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'position',
        'visible_from', // E2: جدولة الظهور
    ];

    protected function casts(): array
    {
        return [
            'visible_from' => 'datetime', // E2: يُحوَّل تلقائياً إلى Carbon
        ];
    }

    /**
     * E2 — scope: الأقسام الظاهرة الآن للطالب/الزائر.
     * الشرط: غير مجدول أو حان موعده (visible_from IS NULL OR visible_from <= now()).
     * مصدر الحقيقة الوحيد لدلالة الإخفاء.
     *
     * @param  Builder<Section>  $q
     * @return Builder<Section>
     */
    public function scopeVisibleNow(Builder $q): Builder
    {
        return $q->where(
            fn (Builder $w) => $w->whereNull('visible_from')->orWhere('visible_from', '<=', now())
        );
    }

    /**
     * E2 — scope: ظهور حسب المُشاهِد.
     * الطاقم ($isStaff = true) يرى الكل؛ غيره يرى الظاهر فقط عبر scopeVisibleNow.
     * قرار «من الطاقم؟» يُمرَّر من طبقة Application/HTTP للحفاظ على نقاوة الموديل.
     *
     * @param  Builder<Section>  $q
     * @return Builder<Section>
     */
    public function scopeVisibleTo(Builder $q, bool $isStaff): Builder
    {
        if ($isStaff) {
            return $q; // الطاقم يتجاوز الإخفاء — يرى كل الأقسام
        }

        return $q->visibleNow();
    }

    /**
     * E2 — دالة مساعدة: هل هذا القسم ظاهر الآن؟
     * تُستخدَم في Enrollment (LessonAccess/LessonProgressController) دون نسخ الدلالة.
     */
    public function isVisibleNow(): bool
    {
        return $this->visible_from === null || $this->visible_from->lte(now());
    }

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

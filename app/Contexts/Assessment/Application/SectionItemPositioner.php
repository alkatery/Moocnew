<?php

declare(strict_types=1);

namespace App\Contexts\Assessment\Application;

use App\Contexts\Assessment\Infrastructure\Persistence\Assignment;
use App\Contexts\Assessment\Infrastructure\Persistence\Quiz;
use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;

/**
 * يحسب الموضع التالي لعنصر جديد (اختبار/واجب) داخل وحدة، فيُلحَق بعد كل
 * عناصر الوحدة الحالية (دروس + اختبارات + واجبات) في مساحة ترتيب واحدة.
 * تُطبّع نقطة إعادة الترتيب الموحّدة المواضع لاحقاً إلى 1..N.
 */
final class SectionItemPositioner
{
    public function next(?int $sectionId): int
    {
        if ($sectionId === null) {
            return 0;
        }

        return max(
            (int) Lesson::query()->where('section_id', $sectionId)->max('position'),
            (int) Quiz::query()->where('section_id', $sectionId)->max('position'),
            (int) Assignment::query()->where('section_id', $sectionId)->max('position'),
        ) + 1;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Contexts\Assessment\Infrastructure\Persistence\Assignment;
use App\Contexts\Assessment\Infrastructure\Persistence\Quiz;
use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use App\Contexts\Catalog\Infrastructure\Persistence\Section;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * إعادة ترتيب موحّدة لعناصر الوحدة (#3): تُسند مواضع متسلسلة (1..N) عبر
 * الدروس والاختبارات والواجبات في مساحة ترتيب واحدة، فيرتّب المؤلّف ويُنوّع
 * تسلسل الوحدة بحرّية (فيديو ← سؤال ← واجب ← اختبار…). التخويل: CoursePolicy::update.
 */
final class SectionItemController extends Controller
{
    /** أنواع العناصر القابلة للترتيب داخل الوحدة وموديلاتها. */
    private const MODELS = [
        'lesson' => Lesson::class,
        'quiz' => Quiz::class,
        'assignment' => Assignment::class,
    ];

    public function reorder(Request $request, Section $section): JsonResponse
    {
        abort_unless($request->user()->can('update', $section->course), 403);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'string', 'in:lesson,quiz,assignment'],
            'items.*.id' => ['required', 'integer'],
        ]);

        // كل عنصر يجب أن ينتمي فعلاً لهذه الوحدة (منع التسرّب عبر الأقسام).
        foreach ($validated['items'] as $item) {
            $model = self::MODELS[$item['type']];
            $belongs = $model::query()
                ->whereKey($item['id'])
                ->where('section_id', $section->getKey())
                ->exists();

            abort_unless($belongs, 422, 'عنصر لا ينتمي لهذه الوحدة.');
        }

        // إسناد المواضع 1..N وفق الترتيب المُرسَل عبر الأنواع الثلاثة.
        foreach (array_values($validated['items']) as $index => $item) {
            $model = self::MODELS[$item['type']];
            $model::query()->whereKey($item['id'])->update(['position' => $index + 1]);
        }

        return response()->json(['message' => 'تم حفظ ترتيب الوحدة.']);
    }
}

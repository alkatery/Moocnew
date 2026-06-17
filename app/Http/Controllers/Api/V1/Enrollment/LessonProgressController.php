<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enrollment;

use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use App\Contexts\Enrollment\Application\LessonAccess;
use App\Contexts\Enrollment\Application\ProgressService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enrollment\RecordProgressRequest;
use App\Http\Resources\EnrollmentResource;
use App\Http\Resources\LessonProgressResource;
use Illuminate\Http\JsonResponse;

final class LessonProgressController extends Controller
{
    public function store(
        RecordProgressRequest $request,
        Lesson $lesson,
        LessonAccess $access,
        ProgressService $progress,
    ): JsonResponse {
        $lesson->loadMissing('section.course');

        // E2: الدرس ضمن قسم غير ظاهر بعد — يُرفض حتى بطلب مباشر بالـ id.
        // خط دفاع ثانٍ يمنع تسجيل تقدّم/استئناف على محتوى مجدول مسرَّب.
        abort_unless(
            $access->canAccess($request->user(), $lesson),
            403,
            'هذا الدرس غير متاح بعد.',
        );

        $enrollment = $access->activeEnrollmentFor($request->user(), $lesson);

        // Progress can only be recorded against an active enrollment.
        abort_if($enrollment === null, 403, 'لا يوجد التحاق نشط بهذه الدورة.');

        $record = $progress->record(
            $enrollment,
            $lesson,
            $request->has('video_position') ? $request->integer('video_position') : null,
            $request->boolean('completed'),
        );

        return response()->json([
            'progress' => new LessonProgressResource($record),
            'enrollment' => new EnrollmentResource($enrollment->refresh()),
        ]);
    }
}

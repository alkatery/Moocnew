<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notification;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\CourseAccess;
use App\Contexts\Identity\Application\ActivityLogger;
use App\Contexts\Notification\Application\CourseBroadcaster;
use App\Contexts\Notification\Infrastructure\Persistence\CourseAnnouncement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\StoreAnnouncementRequest;
use App\Http\Resources\CourseAnnouncementResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * إعلانات المقرر (C3 — PRD §5.ط).
 *
 * store: طاقم المقرر ينشر إعلاناً → يُخزَّن + يُبثّ للملتحقين النشطين (مُطابور).
 * index: قائمة الإعلانات — للطاقم أو المتعلّم النشط.
 */
final class CourseAnnouncementController extends Controller
{
    public function __construct(
        private readonly CourseBroadcaster $broadcaster,
        private readonly CourseAccess $access,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * نشر إعلان جديد في المقرر.
     * التخويل في StoreAnnouncementRequest::authorize() → isStaffFor.
     */
    public function store(StoreAnnouncementRequest $request, Course $course): JsonResponse
    {
        $validated = $request->validated();

        // البثّ: ينشئ الإعلان ويُطابور الإشعارات للملتحقين النشطين
        $count = $this->broadcaster->announce(
            course: $course,
            author: $request->user(),
            title: $validated['title'],
            body: $validated['body'],
        );

        // جلب الإعلان المُنشأ مع علاقة المؤلّف للاستجابة
        /** @var CourseAnnouncement $announcement */
        $announcement = CourseAnnouncement::query()
            ->with('author')
            ->where('course_id', $course->getKey())
            ->latest()
            ->first();

        // تدقيق: announcement.sent بالعدد فقط (لا نصّ — تقليل بيانات)
        $this->logger->log(
            event: 'announcement.sent',
            causer: $request->user(),
            subject: $announcement,
            properties: [
                'recipients' => $count,
                'course_id' => $course->getKey(),
            ],
        );

        return response()->json([
            'data' => new CourseAnnouncementResource($announcement),
            'recipients_queued' => $count,
        ], 201);
    }

    /**
     * قائمة إعلانات المقرر — للطاقم أو المتعلّم النشط.
     * التخويل: canParticipate (isStaffFor أو hasActiveEnrollment).
     */
    public function index(Request $request, Course $course): AnonymousResourceCollection
    {
        // التخويل: طاقم المقرر أو متعلّم له التحاق نشط
        abort_unless(
            $this->access->canParticipate($request->user(), $course),
            403,
            'ليس لديك صلاحية عرض إعلانات هذا المقرر.',
        );

        $announcements = CourseAnnouncement::query()
            ->with('author')
            ->where('course_id', $course->getKey())
            ->latest()
            ->paginate(15);

        return CourseAnnouncementResource::collection($announcements);
    }
}

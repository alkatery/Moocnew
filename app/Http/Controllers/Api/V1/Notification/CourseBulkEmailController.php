<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notification;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Identity\Application\ActivityLogger;
use App\Contexts\Notification\Application\CourseBroadcaster;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\SendBulkEmailRequest;
use Illuminate\Http\JsonResponse;

/**
 * بريد جماعي للمقرر (C3 — PRD §5.ط).
 *
 * يُرسَل لكل ملتحق نشط فردياً (مُطابور). لا يُخزَّن النصّ.
 * الاستجابة 202 Accepted لأنّ التسليم لاحق عبر الطابور.
 */
final class CourseBulkEmailController extends Controller
{
    public function __construct(
        private readonly CourseBroadcaster $broadcaster,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * إرسال بريد جماعي — 202 Accepted (التسليم مُطابور).
     * التخويل في SendBulkEmailRequest::authorize() → isStaffFor.
     */
    public function __invoke(SendBulkEmailRequest $request, Course $course): JsonResponse
    {
        $validated = $request->validated();
        $audience = $validated['audience'] ?? 'all_active';

        // البثّ: يُطابور بريداً فردياً لكل ملتحق نشط (لا BCC — لا كشف عناوين)
        $count = $this->broadcaster->bulkEmail(
            course: $course,
            subject: $validated['subject'],
            body: $validated['body'],
            audience: $audience,
        );

        // تدقيق: bulk_email.sent بالعدد والجمهور (لا نصّ البريد — تقليل بيانات)
        $this->logger->log(
            event: 'bulk_email.sent',
            causer: $request->user(),
            subject: $course,
            properties: [
                'recipients' => $count,
                'audience' => $audience,
            ],
        );

        // 202: الإرسال مُطابور لا منجَز بعد
        return response()->json([
            'recipients_queued' => $count,
            'audience' => $audience,
        ], 202);
    }
}

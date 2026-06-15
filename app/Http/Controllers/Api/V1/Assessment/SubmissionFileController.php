<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Assessment;

use App\Contexts\Assessment\Infrastructure\Persistence\AssignmentSubmission;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * §2.ب — تنزيل ملف التسليم المحمي (طاقم المقرر فقط).
 *
 * النمط مُعاد من MediaStreamController: قرص media المحمي.
 * التخويل عبر can('update', course) لا signed URL لأنّ Sanctum يحمي النقطة.
 */
final class SubmissionFileController extends Controller
{
    public function __invoke(Request $request, AssignmentSubmission $submission): StreamedResponse
    {
        // طاقم المقرر فقط (مالك / courses.review / super_admin عبر Gate::before)
        abort_unless(
            $request->user()->can('update', $submission->assignment->course),
            403,
        );

        // لا ملف مرفق بهذا التسليم
        abort_if($submission->file_path === null, 404);

        $disk = Storage::disk('media');

        // الملف غير موجود فعلياً على القرص
        abort_unless($disk->exists($submission->file_path), 404);

        // إجبار التنزيل بدلاً من البثّ (ملفات PDF/ZIP/إلخ، لا فيديو)
        return $disk->download($submission->file_path);
    }
}

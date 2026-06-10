<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Contexts\Catalog\Domain\Course\LessonType;
use App\Contexts\Catalog\Domain\Course\VideoProvider;
use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Requests automatic transcription of a lesson's video from the managed
 * video provider (Bunny Stream). The provider generates captions
 * asynchronously and burns them into playback; the editable `transcript`
 * field on the lesson remains the text shown in the player's transcript
 * panel and is always manually editable as well.
 */
final class LessonTranscriptController extends Controller
{
    public function auto(Request $request, Lesson $lesson): JsonResponse
    {
        abort_unless($request->user()->can('update', $lesson->section->course), 403);

        if ($lesson->type !== LessonType::Video || $lesson->video_id === null) {
            return response()->json([
                'message' => 'التفريغ التلقائي متاح لدروس الفيديو فقط بعد ربط الفيديو.',
            ], 422);
        }

        if ($lesson->video_provider !== VideoProvider::Bunny) {
            return response()->json([
                'message' => 'التفريغ التلقائي يتطلب استضافة الفيديو على الخدمة المُدارة (Bunny Stream). يمكنك دائماً لصق التفريغ يدوياً في حقل «التفريغ النصي».',
            ], 422);
        }

        $apiKey = (string) config('video.bunny.api_key', '');
        $libraryId = (string) config('video.bunny.library_id', '');

        if ($apiKey === '' || $libraryId === '') {
            return response()->json([
                'message' => 'لم تُضبط مفاتيح Bunny Stream على الخادم (BUNNY_STREAM_API_KEY / BUNNY_STREAM_LIBRARY_ID).',
            ], 422);
        }

        $base = rtrim((string) config('video.bunny.api_base'), '/');

        try {
            $response = Http::withHeaders(['AccessKey' => $apiKey])
                ->timeout(15)
                ->post("{$base}/library/{$libraryId}/videos/{$lesson->video_id}/transcribe", [
                    'language' => $request->input('language', 'ar'),
                ]);
        } catch (ConnectionException) {
            return response()->json(['message' => 'تعذّر الاتصال بمزوّد الفيديو. حاول لاحقاً.'], 502);
        }

        if (! $response->successful()) {
            return response()->json(['message' => 'رفض مزوّد الفيديو طلب التفريغ.'], 502);
        }

        return response()->json([
            'message' => 'تم إرسال طلب التفريغ — يولّد المزوّد الترجمة خلال دقائق وتظهر تلقائياً في مشغّل الفيديو.',
        ], 202);
    }
}

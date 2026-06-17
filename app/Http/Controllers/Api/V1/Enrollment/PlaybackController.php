<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enrollment;

use App\Contexts\Catalog\Domain\Course\LessonType;
use App\Contexts\Catalog\Domain\Course\VideoStatus;
use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use App\Contexts\Enrollment\Application\LessonAccess;
use App\Contexts\Enrollment\Domain\Video\VideoRef;
use App\Contexts\Enrollment\Infrastructure\Video\VideoSourceResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issues a short-lived playback target for a lesson's video, but only to a
 * viewer who is allowed to access it (PRD §5.ج). For managed providers the
 * video must have finished processing.
 */
final class PlaybackController extends Controller
{
    public function show(
        Request $request,
        Lesson $lesson,
        LessonAccess $access,
        VideoSourceResolver $resolver,
    ): JsonResponse {
        $lesson->loadMissing('section.course');

        abort_unless($access->canAccess($request->user(), $lesson), 403);

        abort_unless($lesson->type === LessonType::Video && $lesson->video_provider !== null && $lesson->video_id !== null, 404);

        $provider = $lesson->video_provider;

        // A managed provider's video must be ready before it can be played.
        if ($provider->hasAsyncProcessing() && $lesson->video_status !== VideoStatus::Ready) {
            return response()->json(['message' => 'الفيديو قيد المعالجة.'], 409);
        }

        $target = $resolver->for($provider)->playback(
            new VideoRef($provider, $lesson->video_id, $lesson->id),
        );

        return response()->json(['playback' => $target->toArray()]);
    }
}

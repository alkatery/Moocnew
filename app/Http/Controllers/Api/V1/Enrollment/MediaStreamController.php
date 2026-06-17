<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Enrollment;

use App\Contexts\Catalog\Domain\Course\VideoProvider;
use App\Contexts\Catalog\Infrastructure\Persistence\Lesson;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a storage-hosted lesson video. Reached only through a short-lived
 * signed URL (the `signed` middleware enforces the signature), which is why
 * this endpoint needs no session — the signature is the capability.
 */
final class MediaStreamController extends Controller
{
    public function __invoke(Request $request, Lesson $lesson): StreamedResponse
    {
        abort_unless(
            $lesson->video_provider === VideoProvider::Storage && $lesson->video_id !== null,
            404,
        );

        $disk = Storage::disk((string) config('video.storage.disk', 'media'));

        abort_unless($disk->exists($lesson->video_id), 404);

        return $disk->response($lesson->video_id);
    }
}

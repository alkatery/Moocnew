<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Scheduling;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Enrollment\Application\CourseAccess;
use App\Contexts\Scheduling\Application\LiveSessionService;
use App\Contexts\Scheduling\Domain\Meeting\MeetingProviderType;
use App\Contexts\Scheduling\Infrastructure\Persistence\LiveSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scheduling\ScheduleSessionRequest;
use App\Http\Resources\LiveSessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

final class LiveSessionController extends Controller
{
    public function index(Request $request, Course $course, CourseAccess $access): AnonymousResourceCollection
    {
        abort_unless($access->canParticipate($request->user(), $course), 403);

        return LiveSessionResource::collection(
            LiveSession::query()->where('course_id', $course->getKey())->orderBy('starts_at')->get(),
        );
    }

    public function store(ScheduleSessionRequest $request, Course $course, LiveSessionService $sessions): JsonResponse
    {
        $session = $sessions->schedule(
            $course,
            $request->validated('title'),
            MeetingProviderType::from($request->validated('provider')),
            Carbon::parse($request->validated('starts_at')),
            $request->filled('ends_at') ? Carbon::parse($request->validated('ends_at')) : null,
            $request->validated('capacity'),
            $request->validated('join_url'),
        );

        return (new LiveSessionResource($session))->response()->setStatusCode(201);
    }

    public function register(Request $request, LiveSession $session, CourseAccess $access, LiveSessionService $sessions): JsonResponse
    {
        abort_unless($access->canParticipate($request->user(), $session->course), 403);

        $sessions->register($session, $request->user());

        return (new LiveSessionResource($session->fresh()))->response();
    }
}

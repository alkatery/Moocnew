<?php

declare(strict_types=1);

namespace App\Contexts\Scheduling\Application;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Scheduling\Domain\Meeting\MeetingProviderType;
use App\Contexts\Scheduling\Domain\Meeting\MeetingRequest;
use App\Contexts\Scheduling\Infrastructure\Meeting\MeetingProviderResolver;
use App\Contexts\Scheduling\Infrastructure\Persistence\LiveSession;
use App\Contexts\Scheduling\Infrastructure\Persistence\SessionRegistration;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Schedules live sessions and books seats concurrency-safely (PRD §5.و).
 */
final class LiveSessionService
{
    public function __construct(
        private readonly MeetingProviderResolver $providers,
    ) {}

    public function schedule(
        Course $course,
        string $title,
        MeetingProviderType $provider,
        Carbon $startsAt,
        ?Carbon $endsAt = null,
        ?int $capacity = null,
        ?string $providedUrl = null,
        ?int $maxParticipants = null,
    ): LiveSession {
        $meeting = $this->providers->for($provider)->create(
            new MeetingRequest($title, $startsAt, $endsAt, $providedUrl),
        );

        return LiveSession::query()->create([
            'course_id' => $course->getKey(),
            'title' => $title,
            'provider' => $provider,
            'join_url' => $meeting->joinUrl,
            'external_id' => $meeting->externalId,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'capacity' => $capacity,
            // NELC: synchronous sessions are capped at 35 learners.
            'max_participants' => min($maxParticipants ?? 35, 35),
        ]);
    }

    /**
     * Reserve a seat. Capacity is enforced under a row lock so concurrent
     * registrations cannot oversell the session.
     */
    public function register(LiveSession $session, User $user): SessionRegistration
    {
        return DB::transaction(function () use ($session, $user): SessionRegistration {
            $locked = LiveSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            $existing = SessionRegistration::query()
                ->where('live_session_id', $locked->getKey())
                ->where('user_id', $user->getKey())
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            // Effective ceiling = min(instructor capacity, NELC 35-seat cap).
            $limit = $locked->effectiveCapacity();
            if ($limit !== null) {
                $taken = SessionRegistration::query()->where('live_session_id', $locked->getKey())->count();
                if ($taken >= $limit) {
                    throw ValidationException::withMessages(['session' => ['اكتمل عدد المقاعد.']]);
                }
            }

            return SessionRegistration::query()->create([
                'live_session_id' => $locked->getKey(),
                'user_id' => $user->getKey(),
            ]);
        });
    }
}

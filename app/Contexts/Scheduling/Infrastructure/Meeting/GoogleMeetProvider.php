<?php

declare(strict_types=1);

namespace App\Contexts\Scheduling\Infrastructure\Meeting;

use App\Contexts\Scheduling\Domain\Meeting\MeetingDetails;
use App\Contexts\Scheduling\Domain\Meeting\MeetingProvider;
use App\Contexts\Scheduling\Domain\Meeting\MeetingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google Meet provider (PRD §5.و): creates a Meet link via a Google Calendar
 * event with conferencing. Live use requires Google credentials; automated
 * tests drive it through Http::fake.
 */
final class GoogleMeetProvider implements MeetingProvider
{
    public function __construct(
        private readonly ?string $accessToken,
        private readonly string $baseUrl = 'https://www.googleapis.com/calendar/v3',
    ) {}

    public function key(): string
    {
        return 'google_meet';
    }

    public function create(MeetingRequest $request): MeetingDetails
    {
        if (empty($this->accessToken)) {
            throw new RuntimeException('Google Meet is not configured.');
        }

        $response = Http::withToken($this->accessToken)
            ->post("{$this->baseUrl}/calendars/primary/events?conferenceDataVersion=1", [
                'summary' => $request->title,
                'start' => ['dateTime' => $request->startsAt->toIso8601String()],
                'end' => ['dateTime' => ($request->endsAt ?? $request->startsAt->copy()->addHour())->toIso8601String()],
                'conferenceData' => [
                    'createRequest' => ['requestId' => uniqid('meet_', true)],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Google Meet creation failed: '.$response->status());
        }

        return new MeetingDetails(
            joinUrl: (string) $response->json('hangoutLink'),
            externalId: (string) $response->json('id'),
        );
    }
}

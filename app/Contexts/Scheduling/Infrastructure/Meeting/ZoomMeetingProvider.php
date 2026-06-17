<?php

declare(strict_types=1);

namespace App\Contexts\Scheduling\Infrastructure\Meeting;

use App\Contexts\Scheduling\Domain\Meeting\MeetingDetails;
use App\Contexts\Scheduling\Domain\Meeting\MeetingProvider;
use App\Contexts\Scheduling\Domain\Meeting\MeetingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Zoom Meetings provider (PRD §5.و). Creates a scheduled meeting via the
 * Zoom API using a server-to-server token. Live use requires Zoom
 * credentials; automated tests drive it through Http::fake.
 */
final class ZoomMeetingProvider implements MeetingProvider
{
    public function __construct(
        private readonly ?string $accountToken,
        private readonly string $baseUrl = 'https://api.zoom.us/v2',
    ) {}

    public function key(): string
    {
        return 'zoom';
    }

    public function create(MeetingRequest $request): MeetingDetails
    {
        if (empty($this->accountToken)) {
            throw new RuntimeException('Zoom is not configured.');
        }

        $response = Http::withToken($this->accountToken)
            ->post("{$this->baseUrl}/users/me/meetings", [
                'topic' => $request->title,
                'type' => 2, // scheduled
                'start_time' => $request->startsAt->toIso8601String(),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Zoom meeting creation failed: '.$response->status());
        }

        return new MeetingDetails(
            joinUrl: (string) $response->json('join_url'),
            externalId: (string) $response->json('id'),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Contexts\Scheduling\Domain\Meeting;

/**
 * The resolved join URL and provider reference for a meeting.
 */
final readonly class MeetingDetails
{
    public function __construct(
        public string $joinUrl,
        public ?string $externalId = null,
    ) {}
}

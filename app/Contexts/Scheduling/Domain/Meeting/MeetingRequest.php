<?php

declare(strict_types=1);

namespace App\Contexts\Scheduling\Domain\Meeting;

use Illuminate\Support\Carbon;

/**
 * Input for provisioning a meeting room.
 */
final readonly class MeetingRequest
{
    public function __construct(
        public string $title,
        public Carbon $startsAt,
        public ?Carbon $endsAt = null,
        public ?string $providedUrl = null, // for the manual/external-link provider
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Contexts\Scheduling\Domain\Meeting;

/**
 * Provider of a live meeting room (PRD §5.و). Implementations cover Zoom,
 * Google Meet, and a plain external link. Callers depend only on this, so
 * the provider is chosen per session and swapped freely.
 */
interface MeetingProvider
{
    public function key(): string;

    /**
     * Provision (or accept) a meeting room and return its details.
     */
    public function create(MeetingRequest $request): MeetingDetails;
}

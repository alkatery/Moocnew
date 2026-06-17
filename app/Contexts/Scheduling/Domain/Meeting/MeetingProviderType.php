<?php

declare(strict_types=1);

namespace App\Contexts\Scheduling\Domain\Meeting;

/**
 * Supported live-session providers (PRD §5.و). `Manual` simply stores a
 * link the instructor pastes, with no third-party integration.
 */
enum MeetingProviderType: string
{
    case Zoom = 'zoom';
    case GoogleMeet = 'google_meet';
    case Manual = 'manual';

    public function requiresUrl(): bool
    {
        return $this === self::Manual;
    }
}

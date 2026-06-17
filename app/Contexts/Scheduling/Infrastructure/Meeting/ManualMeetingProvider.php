<?php

declare(strict_types=1);

namespace App\Contexts\Scheduling\Infrastructure\Meeting;

use App\Contexts\Scheduling\Domain\Meeting\MeetingDetails;
use App\Contexts\Scheduling\Domain\Meeting\MeetingProvider;
use App\Contexts\Scheduling\Domain\Meeting\MeetingRequest;
use InvalidArgumentException;

/**
 * "Bring your own link" provider (PRD §5.و): the instructor supplies the
 * meeting URL directly, with no third-party integration. Fully functional —
 * the most portable option.
 */
final class ManualMeetingProvider implements MeetingProvider
{
    public function key(): string
    {
        return 'manual';
    }

    public function create(MeetingRequest $request): MeetingDetails
    {
        if ($request->providedUrl === null || $request->providedUrl === '') {
            throw new InvalidArgumentException('A meeting URL is required for the manual provider.');
        }

        return new MeetingDetails($request->providedUrl);
    }
}

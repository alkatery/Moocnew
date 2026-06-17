<?php

declare(strict_types=1);

namespace App\Contexts\Scheduling\Infrastructure\Meeting;

use App\Contexts\Scheduling\Domain\Meeting\MeetingProvider;
use App\Contexts\Scheduling\Domain\Meeting\MeetingProviderType;
use InvalidArgumentException;

/**
 * Resolves the {@see MeetingProvider} for a provider type.
 */
final class MeetingProviderResolver
{
    /** @param array<string, MeetingProvider> $providers */
    public function __construct(private readonly array $providers) {}

    public function for(MeetingProviderType $type): MeetingProvider
    {
        return $this->providers[$type->value]
            ?? throw new InvalidArgumentException("No meeting provider for [{$type->value}].");
    }
}

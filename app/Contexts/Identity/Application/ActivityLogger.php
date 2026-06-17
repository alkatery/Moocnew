<?php

declare(strict_types=1);

namespace App\Contexts\Identity\Application;

use App\Contexts\Identity\Infrastructure\Persistence\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes entries to the append-only audit trail (PRD §5.ي). Centralising
 * the write here keeps the event vocabulary consistent and gives callers
 * one logging seam to depend on.
 */
final class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(string $event, ?User $causer = null, ?Model $subject = null, array $properties = []): ActivityLog
    {
        return ActivityLog::query()->create([
            'causer_id' => $causer?->getKey(),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties === [] ? null : $properties,
        ]);
    }
}

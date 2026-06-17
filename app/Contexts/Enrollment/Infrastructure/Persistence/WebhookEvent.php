<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;

/**
 * A processed inbound webhook, recorded for idempotency (PRD §8).
 *
 * @property string $provider
 * @property string $external_id
 * @property array|null $payload
 */
final class WebhookEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'provider',
        'external_id',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}

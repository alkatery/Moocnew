<?php

declare(strict_types=1);

namespace App\Contexts\Scheduling\Infrastructure\Persistence;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Contexts\Scheduling\Domain\Meeting\MeetingProviderType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $course_id
 * @property string $title
 * @property MeetingProviderType $provider
 * @property string|null $join_url
 * @property Carbon $starts_at
 * @property int|null $capacity
 */
final class LiveSession extends Model
{
    protected $fillable = [
        'course_id', 'title', 'provider', 'join_url', 'external_id',
        'starts_at', 'ends_at', 'capacity', 'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => MeetingProviderType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reminded_at' => 'datetime',
            'capacity' => 'integer',
        ];
    }

    public function seatsRemaining(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        return max(0, $this->capacity - $this->registrations()->count());
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<SessionRegistration, $this> */
    public function registrations(): HasMany
    {
        return $this->hasMany(SessionRegistration::class);
    }
}

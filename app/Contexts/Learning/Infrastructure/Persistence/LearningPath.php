<?php

declare(strict_types=1);

namespace App\Contexts\Learning\Infrastructure\Persistence;

use Database\Factories\LearningPathFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A specialised learning path: an ordered, levelled bundle of courses that
 * ends with a path certificate.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property string|null $description
 * @property Carbon|null $published_at
 */
final class LearningPath extends Model
{
    /** @use HasFactory<LearningPathFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'summary',
        'description',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function newFactory(): Factory
    {
        return LearningPathFactory::new();
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    /**
     * Items in the strict learning order (level, then position).
     *
     * @return HasMany<LearningPathItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(LearningPathItem::class)
            ->orderBy('level')
            ->orderBy('position');
    }

    /**
     * @return HasMany<PathEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(PathEnrollment::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Contexts\Catalog\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A course category (PRD §6), optionally nested under a parent.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 */
final class Category extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'position',
    ];

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Course, $this>
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}

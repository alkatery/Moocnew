<?php

declare(strict_types=1);

namespace App\Contexts\Catalog\Infrastructure\Persistence;

use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Domain\Course\PricingType;
use App\Contexts\Engagement\Infrastructure\Persistence\CourseReview;
use App\Contexts\Enrollment\Infrastructure\Persistence\Enrollment;
use App\Models\User;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

/**
 * A course (PRD §6). Indexed for search via Laravel Scout; only published
 * courses are made searchable so drafts never leak into the catalogue.
 *
 * @property int $id
 * @property int $instructor_id
 * @property int|null $category_id
 * @property string $title
 * @property string $slug
 * @property CourseStatus $status
 * @property PricingType $pricing_type
 * @property int $price_minor
 */
final class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory, Searchable, SoftDeletes;

    protected $fillable = [
        'instructor_id',
        'category_id',
        'title',
        'slug',
        'summary',
        'cover_image',
        'description',
        'status',
        'pricing_type',
        'price_minor',
        'passing_grade',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CourseStatus::class,
            'pricing_type' => PricingType::class,
            'price_minor' => 'integer',
            'passing_grade' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * The model lives outside app/Models, so the factory is resolved
     * explicitly rather than by Laravel's naming convention.
     */
    protected static function newFactory(): Factory
    {
        return CourseFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<Section, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('position');
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return HasMany<CourseReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(CourseReview::class);
    }

    /**
     * المتطلّبات السابقة لهذا المقرر — many-to-many ذاتية.
     * الطالب يجب أن يُكمل كلّ مقرر في هذه القائمة قبل الالتحاق.
     *
     * @return BelongsToMany<self, $this>
     */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'course_prerequisites',
            'course_id',
            'prerequisite_course_id',
        );
    }

    /**
     * المؤلّفون المشاركون — many-to-many عبر course_members (role='co_author').
     * المالك (instructor_id) ليس هنا؛ هو خاصيّة على courses.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * هل هذا المستخدم مؤلّف مشارك على هذا المقرر؟
     * تُستهلك في CoursePolicy و CourseAccess::isStaffFor.
     */
    public function hasCoAuthor(User $user): bool
    {
        return $this->members()
            ->where('users.id', $user->getKey())
            ->wherePivot('role', 'co_author')
            ->exists();
    }

    /**
     * Keep drafts and in-review courses out of the search index.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->status === CourseStatus::Published;
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'summary' => $this->summary,
            'description' => $this->description,
            'category' => $this->category?->name,
            'instructor' => $this->instructor?->name,
            'pricing_type' => $this->pricing_type->value,
            'price_minor' => $this->price_minor,
        ];
    }
}

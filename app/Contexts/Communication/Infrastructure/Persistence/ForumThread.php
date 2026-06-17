<?php

declare(strict_types=1);

namespace App\Contexts\Communication\Infrastructure\Persistence;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $course_id
 * @property int $user_id
 * @property string $title
 * @property bool $locked
 * @property int|null $accepted_post_id
 */
final class ForumThread extends Model
{
    protected $fillable = ['course_id', 'user_id', 'title', 'locked', 'accepted_post_id'];

    protected function casts(): array
    {
        return ['locked' => 'boolean'];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<ForumPost, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(ForumPost::class, 'thread_id');
    }

    /** D2 — الإجابة المقبولة على الموضوع (إشارة واحدة nullOnDelete). */
    public function acceptedPost(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'accepted_post_id');
    }

    /** D2 — اشتراكات متابعة الموضوع. */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(ForumSubscription::class, 'thread_id');
    }
}

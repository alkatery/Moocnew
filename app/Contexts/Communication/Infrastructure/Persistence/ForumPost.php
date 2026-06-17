<?php

declare(strict_types=1);

namespace App\Contexts\Communication\Infrastructure\Persistence;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $thread_id
 * @property int $user_id
 * @property int|null $parent_id
 * @property string $body
 * @property Carbon|null $hidden_at
 */
final class ForumPost extends Model
{
    protected $fillable = ['thread_id', 'user_id', 'parent_id', 'body', 'hidden_at', 'hidden_by'];

    protected function casts(): array
    {
        return ['hidden_at' => 'datetime'];
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    /** @return BelongsTo<ForumThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'thread_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

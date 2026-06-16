<?php

declare(strict_types=1);

namespace App\Contexts\Communication\Infrastructure\Persistence;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * D2 — اشتراك مستخدم في متابعة موضوع بالمنتدى (PRD §5.ز).
 *
 * @property int $thread_id
 * @property int $user_id
 */
final class ForumSubscription extends Model
{
    protected $fillable = ['thread_id', 'user_id'];

    /** @return BelongsTo<ForumThread, $this> */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(ForumThread::class, 'thread_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

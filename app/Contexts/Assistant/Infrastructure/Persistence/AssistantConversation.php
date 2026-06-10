<?php

declare(strict_types=1);

namespace App\Contexts\Assistant\Infrastructure\Persistence;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $user_id
 * @property string $scope
 * @property int|null $course_id
 * @property string|null $title
 */
final class AssistantConversation extends Model
{
    protected $fillable = [
        'user_id',
        'scope',
        'course_id',
        'title',
    ];

    /**
     * @return HasMany<AssistantMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AssistantMessage::class, 'conversation_id')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Contexts\Identity\Infrastructure\Persistence;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Public-facing instructor profile (PRD §5.أ).
 *
 * @property int $user_id
 * @property string|null $bio
 * @property array|null $social_links
 */
final class InstructorProfile extends Model
{
    protected $fillable = [
        'user_id',
        'bio',
        'social_links',
    ];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

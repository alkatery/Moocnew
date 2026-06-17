<?php

declare(strict_types=1);

namespace App\Contexts\Identity\Infrastructure\Persistence;

use App\Contexts\Identity\Domain\Consent\ConsentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single PDPL consent record (PRD §5.أ, §7).
 *
 * @property int $user_id
 * @property ConsentType $type
 * @property string $policy_version
 * @property Carbon $consented_at
 * @property Carbon|null $revoked_at
 */
final class UserConsent extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'policy_version',
        'consented_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ConsentType::class,
            'consented_at' => 'datetime',
            'revoked_at' => 'datetime',
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

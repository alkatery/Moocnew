<?php

declare(strict_types=1);

namespace App\Contexts\Certification\Infrastructure\Persistence;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A course completion certificate (PRD §5.ح).
 *
 * @property int $user_id
 * @property int $course_id
 * @property string $serial
 * @property string $verification_uuid
 * @property string|null $pdf_path
 * @property Carbon $issued_at
 */
final class Certificate extends Model
{
    protected $fillable = [
        'user_id',
        'course_id',
        'serial',
        'verification_uuid',
        'pdf_path',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'verification_uuid';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}

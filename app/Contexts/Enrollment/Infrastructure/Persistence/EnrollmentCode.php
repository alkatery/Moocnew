<?php

declare(strict_types=1);

namespace App\Contexts\Enrollment\Infrastructure\Persistence;

use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A self-enrollment code for a course. Redeeming a valid code enrolls the
 * learner as an active student immediately, bypassing the catalogue flow —
 * the classroom model where an instructor hands a code to their cohort.
 *
 * @property int $id
 * @property int $course_id
 * @property string $code
 * @property int|null $max_uses
 * @property int $used_count
 * @property Carbon|null $expires_at
 */
final class EnrollmentCode extends Model
{
    protected $fillable = [
        'course_id',
        'code',
        'max_uses',
        'used_count',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->max_uses !== null && $this->used_count >= $this->max_uses;
    }

    /**
     * Generate a unique 8-character code (uppercase letters and digits,
     * ambiguous characters excluded for easy dictation).
     */
    public static function generateCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, Str::length($alphabet) - 1)];
            }
        } while (self::query()->where('code', $code)->exists());

        return $code;
    }
}

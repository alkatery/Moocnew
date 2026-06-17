<?php

declare(strict_types=1);

namespace App\Contexts\Content\Infrastructure\Persistence;

use App\Contexts\Content\Domain\ContactMessageStatus;
use Database\Factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A message submitted through the public contact form (no account needed).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $subject
 * @property string $message
 * @property ContactMessageStatus $status
 * @property Carbon|null $handled_at
 */
final class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => ContactMessageStatus::New->value,
    ];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'handled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContactMessageStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return ContactMessageFactory::new();
    }

    public function markHandled(): void
    {
        $this->update([
            'status' => ContactMessageStatus::Handled,
            'handled_at' => now(),
        ]);
    }
}

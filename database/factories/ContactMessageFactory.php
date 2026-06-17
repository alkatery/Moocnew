<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Contexts\Content\Domain\ContactMessageStatus;
use App\Contexts\Content\Infrastructure\Persistence\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactMessage>
 */
final class ContactMessageFactory extends Factory
{
    protected $model = ContactMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => null,
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'status' => ContactMessageStatus::New,
            'handled_at' => null,
        ];
    }
}

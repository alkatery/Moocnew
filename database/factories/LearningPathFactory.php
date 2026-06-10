<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Contexts\Learning\Infrastructure\Persistence\LearningPath;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LearningPath>
 */
final class LearningPathFactory extends Factory
{
    protected $model = LearningPath::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'summary' => fake()->sentence(12),
            'description' => fake()->paragraph(),
            'published_at' => null,
        ];
    }

    public function published(): self
    {
        return $this->state(fn (): array => [
            'published_at' => now()->subMinute(),
        ]);
    }
}

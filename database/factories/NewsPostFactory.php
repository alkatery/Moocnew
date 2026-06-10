<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Contexts\Content\Infrastructure\Persistence\NewsPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NewsPost>
 */
final class NewsPostFactory extends Factory
{
    protected $model = NewsPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'author_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'excerpt' => fake()->sentence(14),
            'body' => fake()->paragraphs(3, true),
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

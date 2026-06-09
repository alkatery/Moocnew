<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Contexts\Catalog\Domain\Course\CourseStatus;
use App\Contexts\Catalog\Domain\Course\PricingType;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
final class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'instructor_id' => User::factory(),
            'category_id' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'summary' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => CourseStatus::Draft,
            'pricing_type' => PricingType::Free,
            'price_minor' => 0,
            'published_at' => null,
        ];
    }

    public function published(): self
    {
        return $this->state(fn (): array => [
            'status' => CourseStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function pendingReview(): self
    {
        return $this->state(fn (): array => [
            'status' => CourseStatus::PendingReview,
        ]);
    }
}

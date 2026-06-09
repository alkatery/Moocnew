<?php

declare(strict_types=1);

use App\Contexts\Catalog\Domain\Course\PricingType;
use App\Contexts\Catalog\Infrastructure\Persistence\Category;
use App\Contexts\Catalog\Infrastructure\Persistence\Course;

it('lists only published courses to the public', function () {
    Course::factory()->published()->create(['title' => 'دورة منشورة']);
    Course::factory()->create(['title' => 'دورة مسودة']); // draft

    $response = $this->getJson('/api/v1/catalog/courses');

    $response->assertOk()->assertJsonCount(1, 'data');
    expect($response->json('data.0.title'))->toBe('دورة منشورة');
});

it('shows a published course with its sections and lessons but hides lesson content', function () {
    $course = Course::factory()->published()->create();
    $section = $course->sections()->create(['title' => 'القسم الأول', 'position' => 1]);
    $section->lessons()->create([
        'title' => 'الدرس الأول',
        'type' => 'article',
        'content' => 'محتوى سري لا يظهر للزائر',
        'position' => 1,
    ]);

    $response = $this->getJson("/api/v1/catalog/courses/{$course->slug}");

    $response->assertOk()
        ->assertJsonPath('data.sections.0.title', 'القسم الأول')
        ->assertJsonPath('data.sections.0.lessons.0.title', 'الدرس الأول');

    // The article body must not leak through the catalogue view.
    expect($response->getContent())->not->toContain('محتوى سري');
});

it('returns 404 when a guest requests a draft course', function () {
    $course = Course::factory()->create(); // draft

    $this->getJson("/api/v1/catalog/courses/{$course->slug}")->assertNotFound();
});

it('finds published courses by an Arabic keyword and excludes drafts', function () {
    Course::factory()->published()->create(['title' => 'أساسيات البرمجة بلغة بايثون']);
    Course::factory()->published()->create(['title' => 'مقدمة في التصميم الجرافيكي']);
    Course::factory()->create(['title' => 'بايثون متقدّم (مسودة)']); // draft, must not appear

    $response = $this->getJson('/api/v1/catalog/courses?q='.urlencode('بايثون'));

    $response->assertOk()->assertJsonCount(1, 'data');
    expect($response->json('data.0.title'))->toBe('أساسيات البرمجة بلغة بايثون');
});

it('filters the catalogue by category', function () {
    $programming = Category::query()->create(['name' => 'برمجة', 'slug' => 'programming']);
    $design = Category::query()->create(['name' => 'تصميم', 'slug' => 'design']);

    Course::factory()->published()->create(['category_id' => $programming->id]);
    Course::factory()->published()->create(['category_id' => $design->id]);

    $this->getJson('/api/v1/catalog/courses?category=programming')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.category.slug', 'programming');
});

it('filters the catalogue by pricing', function () {
    Course::factory()->published()->create(['pricing_type' => PricingType::Free, 'price_minor' => 0]);
    Course::factory()->published()->create(['pricing_type' => PricingType::OneTime, 'price_minor' => 10000]);

    $this->getJson('/api/v1/catalog/courses?pricing=paid')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.pricing_type', PricingType::OneTime->value);
});

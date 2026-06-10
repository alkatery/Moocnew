<?php

declare(strict_types=1);

use App\Contexts\Identity\Domain\Role;
use App\Contexts\Platform\Infrastructure\Persistence\SiteContent;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SiteContentSeeder::class);
});

it('exposes a public key→value map of non-empty content', function () {
    $map = $this->getJson('/api/v1/content/site')->assertOk()->json('data');

    expect($map['brand.name'])->toBe('منصة MOOC');
    // Empty-valued fields (e.g. the logo image) are omitted, not null.
    expect($map)->not->toHaveKey('brand.logo');
});

it('keeps the admin editor and updates away from non-staff', function () {
    $this->getJson('/api/v1/admin/site-content')->assertUnauthorized();

    Sanctum::actingAs(userWithRole(Role::Student));
    $this->getJson('/api/v1/admin/site-content')->assertForbidden();
    $this->patchJson('/api/v1/admin/site-content', ['values' => ['brand.name' => 'x']])->assertForbidden();
});

it('lists every field with its editing metadata for staff', function () {
    Sanctum::actingAs(userWithRole(Role::Supervisor));

    $fields = collect($this->getJson('/api/v1/admin/site-content')->assertOk()->json('data'));

    expect($fields->firstWhere('key', 'brand.logo')['type'])->toBe('image');
    expect($fields->firstWhere('key', 'brand.tagline')['type'])->toBe('textarea');
    expect($fields->firstWhere('key', 'brand.name')['type'])->toBe('text');
});

it('bulk-updates content values and reflects them publicly', function () {
    Sanctum::actingAs(userWithRole(Role::Supervisor));

    $updated = $this->patchJson('/api/v1/admin/site-content', [
        'values' => [
            'brand.name' => 'أكاديمية النور',
            'home.hero_title' => 'تعلّم بثقة',
        ],
    ])->assertOk()->json('data');
    expect($updated['brand.name'])->toBe('أكاديمية النور');

    $map = $this->getJson('/api/v1/content/site')->assertOk()->json('data');
    expect($map['brand.name'])->toBe('أكاديمية النور');
    expect($map['home.hero_title'])->toBe('تعلّم بثقة');
});

it('ignores unknown keys on update', function () {
    Sanctum::actingAs(userWithRole(Role::Supervisor));

    $this->patchJson('/api/v1/admin/site-content', [
        'values' => ['totally.unknown.key' => 'value'],
    ])->assertOk();

    $this->assertDatabaseMissing('site_contents', ['key' => 'totally.unknown.key']);
});

it('clears a value when an empty string is submitted', function () {
    Sanctum::actingAs(userWithRole(Role::Supervisor));

    $this->patchJson('/api/v1/admin/site-content', ['values' => ['footer.note' => '']])->assertOk();

    expect(SiteContent::query()->where('key', 'footer.note')->value('value'))->toBeNull();
});

it('uploads an image for an image field and stores its public url', function () {
    Storage::fake('public');
    Sanctum::actingAs(userWithRole(Role::Supervisor));

    $response = $this->postJson('/api/v1/admin/site-content/brand.logo/image', [
        'image' => UploadedFile::fake()->image('logo.png', 200, 80),
    ])->assertOk();

    $url = $response->json('data.value');
    expect($url)->toContain('/storage/site/');

    // The new URL is now part of the public map.
    $map = $this->getJson('/api/v1/content/site')->assertOk()->json('data');
    expect($map['brand.logo'])->toBe($url);

    expect(Storage::disk('public')->allFiles('site'))->toHaveCount(1);
});

it('rejects uploading to a non-image field', function () {
    Storage::fake('public');
    Sanctum::actingAs(userWithRole(Role::Supervisor));

    $this->postJson('/api/v1/admin/site-content/brand.name/image', [
        'image' => UploadedFile::fake()->image('x.png'),
    ])->assertStatus(422);
});

it('rejects a non-image upload', function () {
    Storage::fake('public');
    Sanctum::actingAs(userWithRole(Role::Supervisor));

    $this->postJson('/api/v1/admin/site-content/brand.logo/image', [
        'image' => UploadedFile::fake()->create('malware.pdf', 100, 'application/pdf'),
    ])->assertStatus(422)->assertJsonValidationErrors(['image']);
});

it('clears an uploaded image and removes the stored file', function () {
    Storage::fake('public');
    Sanctum::actingAs(userWithRole(Role::Supervisor));

    $this->postJson('/api/v1/admin/site-content/brand.logo/image', [
        'image' => UploadedFile::fake()->image('logo.png'),
    ])->assertOk();

    $this->deleteJson('/api/v1/admin/site-content/brand.logo/image')
        ->assertOk()
        ->assertJsonPath('data.value', null);

    expect(Storage::disk('public')->allFiles('site'))->toHaveCount(0);
});

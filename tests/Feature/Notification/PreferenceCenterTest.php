<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('returns the full preference matrix with channels enabled by default', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/v1/notifications/preferences')->assertOk();

    // 6 types × 5 channels = 30 rows, all enabled by default.
    expect($response->json('data'))->toHaveCount(30);
    expect(collect($response->json('data'))->every(fn ($r) => $r['enabled'] === true))->toBeTrue();
});

it('updates a preference and reflects it on read', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->putJson('/api/v1/notifications/preferences', [
        'preferences' => [
            ['type' => 'enrollment_confirmed', 'channel' => 'sms', 'enabled' => false],
        ],
    ])->assertOk();

    $rows = collect($this->getJson('/api/v1/notifications/preferences')->json('data'));
    $sms = $rows->firstWhere(fn ($r) => $r['type'] === 'enrollment_confirmed' && $r['channel'] === 'sms');

    expect($sms['enabled'])->toBeFalse();
});

it('rejects an unknown channel', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->putJson('/api/v1/notifications/preferences', [
        'preferences' => [
            ['type' => 'enrollment_confirmed', 'channel' => 'carrier_pigeon', 'enabled' => false],
        ],
    ])->assertStatus(422);
});

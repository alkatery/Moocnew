<?php

declare(strict_types=1);

use App\Contexts\Platform\Domain\Settings\SettingKey;
use App\Contexts\Platform\Domain\Settings\SettingsRepository;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('hides Commerce endpoints while payments are disabled (free mode)', function () {
    Sanctum::actingAs(User::factory()->create());

    // payments.enabled defaults to false → Commerce is invisible.
    $this->getJson('/api/v1/commerce/status')->assertNotFound();
});

it('exposes Commerce endpoints once payments are enabled', function () {
    app(SettingsRepository::class)->set(SettingKey::PaymentsEnabled, true);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/commerce/status')
        ->assertOk()
        ->assertJsonPath('enabled', true)
        ->assertJsonPath('gateway', 'moyasar');
});

it('still requires authentication for Commerce endpoints', function () {
    app(SettingsRepository::class)->set(SettingKey::PaymentsEnabled, true);

    $this->getJson('/api/v1/commerce/status')->assertUnauthorized();
});

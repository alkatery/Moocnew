<?php

declare(strict_types=1);

use App\Contexts\Platform\Domain\Settings\SettingKey;
use App\Contexts\Platform\Domain\Settings\SettingsRepository;

it('reports ok with payments disabled by default', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
            'features' => [
                'payments_enabled' => false,
            ],
        ]);
});

it('reflects the payments flag once enabled', function () {
    app(SettingsRepository::class)->set(SettingKey::PaymentsEnabled, true);

    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJson([
            'features' => [
                'payments_enabled' => true,
            ],
        ]);
});

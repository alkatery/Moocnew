<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Contexts\Platform\Domain\Settings\SettingKey;
use App\Contexts\Platform\Domain\Settings\SettingsRepository;
use Illuminate\Database\Seeder;

/**
 * Seeds the default platform settings. The platform boots in free mode,
 * so `payments.enabled` defaults to false; a Super Admin flips it later
 * from the settings panel.
 */
final class SettingsSeeder extends Seeder
{
    public function run(SettingsRepository $settings): void
    {
        if ($settings->get(SettingKey::PaymentsEnabled) === null) {
            $settings->set(
                SettingKey::PaymentsEnabled,
                (bool) config('platform.payments.enabled_default', false),
            );
        }
    }
}

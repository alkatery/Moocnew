<?php

declare(strict_types=1);

use App\Contexts\Platform\Application\FeatureFlags;
use App\Contexts\Platform\Domain\Settings\SettingKey;
use App\Contexts\Platform\Domain\Settings\SettingsRepository;

/**
 * In-memory settings repository so FeatureFlags can be tested in isolation,
 * without touching the database or cache.
 */
function inMemorySettings(array $initial = []): SettingsRepository
{
    return new class($initial) implements SettingsRepository
    {
        public function __construct(private array $values) {}

        public function get(SettingKey $key): mixed
        {
            return $this->values[$key->value] ?? null;
        }

        public function set(SettingKey $key, mixed $value): void
        {
            $this->values[$key->value] = $value;
        }

        public function forget(SettingKey $key): void
        {
            unset($this->values[$key->value]);
        }
    };
}

it('falls back to the config default when the setting is unset', function () {
    config()->set('platform.payments.enabled_default', false);

    $flags = new FeatureFlags(inMemorySettings());

    expect($flags->paymentsEnabled())->toBeFalse();
});

it('honours the config default of true when the setting is unset', function () {
    config()->set('platform.payments.enabled_default', true);

    $flags = new FeatureFlags(inMemorySettings());

    expect($flags->paymentsEnabled())->toBeTrue();
});

it('prefers the stored setting over the config default', function () {
    config()->set('platform.payments.enabled_default', false);

    $flags = new FeatureFlags(inMemorySettings([
        SettingKey::PaymentsEnabled->value => true,
    ]));

    expect($flags->paymentsEnabled())->toBeTrue();
});

it('treats a stored false as authoritative even when the default is true', function () {
    config()->set('platform.payments.enabled_default', true);

    $flags = new FeatureFlags(inMemorySettings([
        SettingKey::PaymentsEnabled->value => false,
    ]));

    expect($flags->paymentsEnabled())->toBeFalse();
});

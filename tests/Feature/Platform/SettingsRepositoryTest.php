<?php

declare(strict_types=1);

use App\Contexts\Platform\Domain\Settings\SettingKey;
use App\Contexts\Platform\Domain\Settings\SettingsRepository;
use App\Contexts\Platform\Infrastructure\Persistence\SettingModel;

beforeEach(function () {
    $this->repository = app(SettingsRepository::class);
});

it('returns null for a setting that has never been set', function () {
    expect($this->repository->get(SettingKey::PaymentsEnabled))->toBeNull();
});

it('persists and reads back a boolean setting with its native type', function () {
    $this->repository->set(SettingKey::PaymentsEnabled, true);

    expect($this->repository->get(SettingKey::PaymentsEnabled))->toBeTrue();

    $this->assertDatabaseHas('settings', [
        'key' => SettingKey::PaymentsEnabled->value,
        'type' => 'boolean',
    ]);
});

it('overwrites an existing setting rather than duplicating it', function () {
    $this->repository->set(SettingKey::PaymentsEnabled, true);
    $this->repository->set(SettingKey::PaymentsEnabled, false);

    expect($this->repository->get(SettingKey::PaymentsEnabled))->toBeFalse();
    expect(SettingModel::query()->where('key', SettingKey::PaymentsEnabled->value)->count())->toBe(1);
});

it('reflects a write made through the repository on the next read (cache invalidation)', function () {
    // Prime the cache with the unset state.
    expect($this->repository->get(SettingKey::PaymentsEnabled))->toBeNull();

    // Writing through the same repository must invalidate the cached null.
    $this->repository->set(SettingKey::PaymentsEnabled, true);

    expect($this->repository->get(SettingKey::PaymentsEnabled))->toBeTrue();
});

it('does not hit the database on a cached read', function () {
    $this->repository->set(SettingKey::PaymentsEnabled, true);

    // First read populates the cache.
    $this->repository->get(SettingKey::PaymentsEnabled);

    // Remove the underlying row directly; a cached read should still resolve.
    SettingModel::query()->where('key', SettingKey::PaymentsEnabled->value)->delete();

    expect($this->repository->get(SettingKey::PaymentsEnabled))->toBeTrue();
});

it('forgets a setting and clears its cache', function () {
    $this->repository->set(SettingKey::PaymentsEnabled, true);
    $this->repository->get(SettingKey::PaymentsEnabled);

    $this->repository->forget(SettingKey::PaymentsEnabled);

    expect($this->repository->get(SettingKey::PaymentsEnabled))->toBeNull();
    $this->assertDatabaseMissing('settings', [
        'key' => SettingKey::PaymentsEnabled->value,
    ]);
});

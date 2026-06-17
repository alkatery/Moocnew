<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Infrastructure\Persistence;

use App\Contexts\Platform\Domain\Settings\SettingKey;
use App\Contexts\Platform\Domain\Settings\SettingsRepository;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Cache-aside decorator over a {@see SettingsRepository}. Reads are served
 * from the cache (Redis in production) and writes invalidate the affected
 * key so the next read repopulates it from the source of truth.
 *
 * Settings are read on nearly every request (e.g. the payments feature
 * flag), change rarely, and must stay consistent across processes — a
 * textbook cache-aside case.
 */
final class CachedSettingsRepository implements SettingsRepository
{
    /**
     * Sentinel cached in place of a genuine null so that "known to be
     * unset" is distinguishable from "cache miss". Without it, an unset
     * key would hit the database on every request.
     */
    private const NULL_SENTINEL = '__mooc_setting_null__';

    public function __construct(
        private readonly SettingsRepository $inner,
        private readonly Cache $cache,
    ) {}

    public function get(SettingKey $key): mixed
    {
        $cached = $this->cache->rememberForever(
            $this->cacheKey($key),
            fn (): mixed => $this->inner->get($key) ?? self::NULL_SENTINEL,
        );

        return $cached === self::NULL_SENTINEL ? null : $cached;
    }

    public function set(SettingKey $key, mixed $value): void
    {
        $this->inner->set($key, $value);
        $this->cache->forget($this->cacheKey($key));
    }

    public function forget(SettingKey $key): void
    {
        $this->inner->forget($key);
        $this->cache->forget($this->cacheKey($key));
    }

    private function cacheKey(SettingKey $key): string
    {
        return "settings:{$key->value}";
    }
}

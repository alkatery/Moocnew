<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Application;

use App\Contexts\Platform\Infrastructure\Persistence\SiteContent;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Collection;

/**
 * Reads and writes editable site content. The public key→value map is read
 * on (nearly) every public page, so it is cached and invalidated on write —
 * the same cache-aside pattern used for platform settings.
 */
final class SiteContentRepository
{
    private const CACHE_KEY = 'site-content:public-map';

    public function __construct(
        private readonly Cache $cache,
    ) {}

    /**
     * Flat key→value map for the public site (empty values omitted so the
     * frontend falls back to its built-in defaults).
     *
     * @return array<string, string>
     */
    public function publicMap(): array
    {
        return $this->cache->rememberForever(self::CACHE_KEY, function (): array {
            return SiteContent::query()
                ->whereNotNull('value')
                ->where('value', '!=', '')
                ->pluck('value', 'key')
                ->all();
        });
    }

    /**
     * All fields with their editing metadata, ordered for the admin panel.
     *
     * @return Collection<int, SiteContent>
     */
    public function grouped(): Collection
    {
        return SiteContent::query()
            ->orderBy('group')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /**
     * Update the values of existing keys in bulk. Unknown keys are ignored,
     * so callers can't create arbitrary content rows.
     *
     * @param  array<string, string|null>  $values
     */
    public function updateValues(array $values): void
    {
        $known = SiteContent::query()
            ->whereIn('key', array_keys($values))
            ->get()
            ->keyBy('key');

        foreach ($values as $key => $value) {
            $row = $known->get($key);
            if ($row !== null) {
                $row->update(['value' => $value === '' ? null : $value]);
            }
        }

        $this->flush();
    }

    public function setValue(string $key, ?string $value): void
    {
        SiteContent::query()->where('key', $key)->update(['value' => $value]);
        $this->flush();
    }

    public function find(string $key): ?SiteContent
    {
        return SiteContent::query()->where('key', $key)->first();
    }

    public function flush(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }
}

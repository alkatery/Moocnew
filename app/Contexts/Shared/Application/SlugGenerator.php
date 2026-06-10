<?php

declare(strict_types=1);

namespace App\Contexts\Shared\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Produces a unique, URL-safe slug for a model. Falls back to a random
 * token when the source text transliterates to an empty slug (common for
 * Arabic-only titles), and appends a numeric suffix to resolve clashes.
 *
 * Lives in the shared kernel: Catalog (courses, categories) and Content
 * (news) both rely on it.
 */
final class SlugGenerator
{
    public function forTitle(string $title, string $table, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'item-'.Str::lower(Str::random(8));
        }

        $slug = $base;
        $suffix = 2;

        while ($this->exists($slug, $table, $ignoreId)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function exists(string $slug, string $table, ?int $ignoreId): bool
    {
        return DB::table($table)
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }
}

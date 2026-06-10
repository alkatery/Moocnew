<?php

declare(strict_types=1);

namespace App\Contexts\Shared\Application;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores an uploaded image on the public disk under a folder and returns its
 * public URL, pruning a previously stored asset for the same subject.
 */
final class ImageUploader
{
    public function store(UploadedFile $file, string $folder, ?string $previousUrl = null): string
    {
        $this->deleteByUrl($previousUrl, $folder);

        $path = $file->store($folder, 'public');

        return Storage::disk('public')->url($path);
    }

    public function deleteByUrl(?string $url, string $folder): void
    {
        if ($url !== null && str_contains($url, "/storage/{$folder}/")) {
            $relative = str_replace('/storage/', '', (string) parse_url($url, PHP_URL_PATH));
            Storage::disk('public')->delete($relative);
        }
    }
}

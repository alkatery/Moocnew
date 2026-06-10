<?php

declare(strict_types=1);

namespace App\Http\Requests\Content;

use App\Contexts\Identity\Domain\Permission;
use Illuminate\Foundation\Http\FormRequest;

final class UploadSiteImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ManageContent->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // SVG is allowed for logos; raster formats for everything else.
            'image' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,gif,svg,ico', 'max:4096'],
        ];
    }
}

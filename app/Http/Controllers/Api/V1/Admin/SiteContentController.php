<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Contexts\Identity\Application\ActivityLogger;
use App\Contexts\Identity\Domain\Permission;
use App\Contexts\Platform\Application\SiteContentRepository;
use App\Contexts\Platform\Domain\Content\SiteContentType;
use App\Contexts\Platform\Infrastructure\Persistence\SiteContent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\UpdateSiteContentRequest;
use App\Http\Requests\Content\UploadSiteImageRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Admin management of editable site content (PRD §4 extension): list every
 * field grouped with its editing metadata, bulk-update text/colour/url
 * values, and upload images for image fields. Gated by content.manage.
 */
final class SiteContentController extends Controller
{
    public function index(Request $request, SiteContentRepository $content): JsonResponse
    {
        abort_unless($request->user()->can(Permission::ManageContent->value), 403);

        $fields = $content->grouped()->map(fn (SiteContent $row): array => [
            'key' => $row->key,
            'group' => $row->group,
            'type' => $row->type->value,
            'label' => $row->label,
            'value' => $row->value,
        ]);

        return response()->json(['data' => $fields]);
    }

    public function update(
        UpdateSiteContentRequest $request,
        SiteContentRepository $content,
        ActivityLogger $activity,
    ): JsonResponse {
        $content->updateValues($request->validated('values'));

        $activity->log('site_content.updated', $request->user(), properties: [
            'keys' => array_keys($request->validated('values')),
        ]);

        return response()->json(['data' => $content->publicMap()]);
    }

    public function uploadImage(
        UploadSiteImageRequest $request,
        string $key,
        SiteContentRepository $content,
        ActivityLogger $activity,
    ): JsonResponse {
        $field = $content->find($key);

        abort_if($field === null, 404);
        abort_unless($field->type === SiteContentType::Image, 422, 'هذا الحقل ليس حقل صورة.');

        // Replace any previously uploaded asset for this key.
        if ($field->value !== null && str_contains($field->value, '/storage/site/')) {
            $old = (string) parse_url($field->value, PHP_URL_PATH);
            $relative = str_replace('/storage/', '', $old);
            Storage::disk('public')->delete($relative);
        }

        $path = $request->file('image')->store('site', 'public');
        $url = Storage::disk('public')->url($path);

        $content->setValue($key, $url);

        $activity->log('site_content.image_uploaded', $request->user(), properties: [
            'key' => $key,
        ]);

        return response()->json(['data' => ['key' => $key, 'value' => $url]]);
    }

    public function clearImage(
        Request $request,
        string $key,
        SiteContentRepository $content,
    ): JsonResponse {
        abort_unless($request->user()->can(Permission::ManageContent->value), 403);

        $field = $content->find($key);
        abort_if($field === null, 404);

        if ($field->value !== null && str_contains($field->value, '/storage/site/')) {
            $relative = str_replace('/storage/', '', (string) parse_url($field->value, PHP_URL_PATH));
            Storage::disk('public')->delete($relative);
        }

        $content->setValue($key, null);

        return response()->json(['data' => ['key' => $key, 'value' => null]]);
    }
}

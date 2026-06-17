<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Content;

use App\Contexts\Platform\Application\SiteContentRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Public, cached key→value map of editable site content (branding, copy,
 * colours, image URLs). Consumed by the frontend on load; only non-empty
 * values are returned so the UI keeps its built-in defaults otherwise.
 */
final class SiteContentController extends Controller
{
    public function __invoke(SiteContentRepository $content): JsonResponse
    {
        return response()->json(['data' => $content->publicMap()]);
    }
}

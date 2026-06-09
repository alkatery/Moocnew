<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Contexts\Catalog\Application\SlugGenerator;
use App\Contexts\Catalog\Infrastructure\Persistence\Category;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCategoryRequest;
use App\Http\Resources\CategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection(
            Category::query()->orderBy('position')->orderBy('name')->get(),
        );
    }

    public function store(StoreCategoryRequest $request, SlugGenerator $slugs): JsonResponse
    {
        $category = Category::query()->create([
            'name' => $request->validated('name'),
            'parent_id' => $request->validated('parent_id'),
            'position' => $request->validated('position', 0),
            'slug' => $slugs->forTitle($request->validated('name'), 'categories'),
        ]);

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }
}

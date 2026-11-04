<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(CategoryService $categories): AnonymousResourceCollection
    {
        return CategoryResource::collection($categories->listPublicCategories());
    }

    public function show(int $id, CategoryService $categories): JsonResponse
    {
        return (new CategoryResource($categories->getPublicCategory($id)))->response()->setStatusCode(200);
    }

    public function filters(int $category, CategoryService $categories): JsonResponse
    {
        return response()->json(['data' => $categories->availableFilters($category)]);
    }
}

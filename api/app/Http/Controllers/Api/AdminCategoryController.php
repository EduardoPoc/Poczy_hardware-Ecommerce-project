<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AdminCategoryController extends Controller
{
    public function index(CategoryService $categories): AnonymousResourceCollection
    {
        return CategoryResource::collection($categories->listAll());
    }

    public function show(int $id, CategoryService $categories): JsonResponse
    {
        return (new CategoryResource($categories->findById($id)))->response()->setStatusCode(200);
    }

    public function store(StoreCategoryRequest $request, CategoryService $categories): JsonResponse
    {
        $category = $categories->create($request->validated());

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, int $id, CategoryService $categories): JsonResponse
    {
        $category = $categories->update($id, $request->validated());

        return (new CategoryResource($category))->response()->setStatusCode(200);
    }

    public function destroy(int $id, CategoryService $categories): Response
    {
        $categories->delete($id);

        return response()->noContent();
    }
}

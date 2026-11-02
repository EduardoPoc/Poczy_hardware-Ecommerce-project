<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AdminCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::orderBy('name')->get();

        return response()->json($categories);
    }

    public function show(int $id): JsonResponse
    {
        $record = Category::query()->findOrFail($id);

        return response()->json($record);
    }

    public function store(StoreCategoryRequest $request, CategoryService $categories): JsonResponse
    {
        $category = $categories->create($request->validated());

        return response()->json($category, 201);
    }

    public function update(UpdateCategoryRequest $request, int $id, CategoryService $categories): JsonResponse
    {
        $category = $categories->update($id, $request->validated());

        return response()->json($category);
    }

    public function destroy(int $id, CategoryService $categories): Response
    {
        $categories->delete($id);

        return response()->noContent();
    }
}

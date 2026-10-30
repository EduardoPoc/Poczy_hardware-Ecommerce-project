<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function store(Request $request, CategoryService $categories): JsonResponse
    {
        $category = $categories->create($request->all());

        return response()->json($category, 201);
    }

    public function update(Request $request, int $id, CategoryService $categories): JsonResponse
    {
        $category = $categories->update($id, $request->all());

        return response()->json($category);
    }

    public function destroy(int $id, CategoryService $categories): Response
    {
        $categories->delete($id);

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get();

        return response()->json($categories);
    }

    public function show(int $id): JsonResponse
    {
        $category = Category::where('is_active', true)->findOrFail($id);

        return response()->json($category);
    }
}

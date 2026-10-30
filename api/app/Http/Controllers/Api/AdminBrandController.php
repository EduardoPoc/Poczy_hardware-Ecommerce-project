<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminBrandController extends Controller
{
    public function index(): JsonResponse
    {
        $brands = Brand::orderBy('name')->get();

        return response()->json($brands);
    }

    public function show(int $brand): JsonResponse
    {
        $brand = Brand::findOrFail($brand);

        return response()->json($brand);
    }

    public function store(Request $request): JsonResponse
    {
        $brand = Brand::create($request->all());

        return response()->json($brand, 201);
    }

    public function update(Request $request, int $brand): JsonResponse
    {
        $brand = Brand::findOrFail($brand);
        $brand->update($request->all());

        return response()->json($brand);
    }

    public function destroy(int $brand): Response
    {
        $brand = Brand::findOrFail($brand);
        $brand->delete();

        return response()->noContent();
    }
}

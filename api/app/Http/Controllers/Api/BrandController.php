<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
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
}

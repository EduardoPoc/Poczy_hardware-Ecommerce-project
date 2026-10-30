<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BrandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminBrandController extends Controller
{
    public function index(BrandService $brands): JsonResponse
    {
        $brands = $brands->listAll();

        return response()->json($brands);
    }

    public function show(int $brand, BrandService $brands): JsonResponse
    {
        $brand = $brands->findById($brand);

        return response()->json($brand);
    }

    public function store(Request $request, BrandService $brands): JsonResponse
    {
        $brand = $brands->create($request->all());

        return response()->json($brand, 201);
    }

    public function update(Request $request, int $brand, BrandService $brands): JsonResponse
    {
        $brand = $brands->update($brand, $request->all());

        return response()->json($brand);
    }

    public function destroy(int $brand, BrandService $brands): Response
    {
        $brands->delete($brand);

        return response()->noContent();
    }
}

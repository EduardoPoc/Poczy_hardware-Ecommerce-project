<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BrandService;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
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
}

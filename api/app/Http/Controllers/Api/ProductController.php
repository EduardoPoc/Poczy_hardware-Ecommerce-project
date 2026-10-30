<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request, ProductService $products): JsonResponse
    {
        $products = $products->listPublicProducts($request->input('per_page', 20), $request->query());

        return response()->json($products);
    }

    public function show(int $id, ProductService $products): JsonResponse
    {
        $product = $products->getPublicProduct((string) $id);

        return response()->json($product);
    }
}

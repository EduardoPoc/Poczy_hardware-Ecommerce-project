<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AdminProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $records = Product::query()->with(['category', 'brand'])->orderBy('name')->orderBy('id')
            ->paginate($request->input('per_page', 20))->withQueryString();

        return response()->json($records);
    }

    public function show(int $id): JsonResponse
    {
        $record = Product::query()->with(['category', 'brand'])->findOrFail($id);

        return response()->json($record);
    }

    public function store(Request $request, ProductService $products): JsonResponse
    {
        $product = $products->create($request->all());

        return response()->json($product, 201);
    }

    public function update(Request $request, int $id, ProductService $products): JsonResponse
    {
        $product = $products->update($id, $request->all());

        return response()->json($product);
    }

    public function destroy(int $id, ProductService $products): Response
    {
        $products->delete($id);

        return response()->noContent();
    }
}

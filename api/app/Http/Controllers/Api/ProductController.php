<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::where('is_active', true)
            ->whereHas('category', fn (Builder $query): Builder => $query->where('is_active', true))
            ->orderBy('name')->orderBy('id')
            ->paginate($request->input('per_page', 20))->withQueryString();

        return response()->json($products);
    }

    public function show(int $id): JsonResponse
    {
        $product = Product::where('is_active', true)
            ->whereHas('category', fn (Builder $query): Builder => $query->where('is_active', true))
            ->with(['category', 'brand'])->findOrFail($id);

        return response()->json($product);
    }
}

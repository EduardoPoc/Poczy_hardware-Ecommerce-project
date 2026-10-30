<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get();
        $products = Product::where('is_active', true)
            ->whereHas('category', fn (Builder $query): Builder => $query->where('is_active', true))
            ->with('brand')->orderBy('name')->orderBy('id')->get();

        return response()->json(['data' => ['categories' => $categories, 'products' => $products]]);
    }
}

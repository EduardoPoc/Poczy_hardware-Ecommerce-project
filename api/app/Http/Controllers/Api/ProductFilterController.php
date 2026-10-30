<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductFilterController extends Controller
{
    public function __invoke(Request $request, ProductService $products): JsonResponse
    {
        $filter = $request->query('filter');
        if (is_string($filter)) {
            $filter = json_decode($filter, true);
        }
        $products = $products->filterPublicProducts($filter, $request->input('per_page', 20));

        return response()->json($products);
    }
}

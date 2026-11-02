<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterPublicProductsRequest;
use App\Http\Resources\ProductResource;
use App\Services\ProductService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductFilterController extends Controller
{
    public function __invoke(FilterPublicProductsRequest $request, ProductService $products): AnonymousResourceCollection
    {
        $records = $products->filterPublicProducts(
            $request->validated('filter', []),
            (int) $request->validated('per_page', 20),
        );

        return ProductResource::collection($records);
    }
}

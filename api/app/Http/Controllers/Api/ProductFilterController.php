<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterProductsRequest;
use App\Http\Resources\ProductCardResource;
use App\Services\ProductService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductFilterController extends Controller
{
    public function __invoke(FilterProductsRequest $request, ProductService $products): AnonymousResourceCollection
    {
        return ProductCardResource::collection($products->filterPublicProducts($request->validated('filter'), $request->perPage()));
    }
}

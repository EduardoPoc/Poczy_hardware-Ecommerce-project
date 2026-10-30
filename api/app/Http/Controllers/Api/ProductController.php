<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterProductsRequest;
use App\Http\Resources\ProductResource;
use App\Services\ProductService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(FilterProductsRequest $request, ProductService $products): AnonymousResourceCollection
    {
        $products = $products->listPublicProducts((int) $request->validated('per_page', 20), $request->validated());

        return ProductResource::collection($products);
    }

    public function show(int $id, ProductService $products): ProductResource
    {
        $product = $products->getPublicProduct((string) $id);

        return new ProductResource($product);
    }
}

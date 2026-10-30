<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\AdminProductResource;
use App\Http\Resources\ProductCardResource;
use App\Http\Resources\ProductDetailResource;
use App\Services\ProductService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function __construct(private ProductService $products) {}

    public function index(PaginationRequest $request): AnonymousResourceCollection
    {
        return ProductCardResource::collection($this->products->listPublicProducts($request->perPage()));
    }

    public function show(string $id): ProductDetailResource
    {
        return new ProductDetailResource($this->products->getPublicProduct($id));
    }

    public function store(StoreProductRequest $request): AdminProductResource
    {
        return new AdminProductResource($this->products->create($request->validated()));
    }

    public function update(UpdateProductRequest $request, int $id): AdminProductResource
    {
        return new AdminProductResource($this->products->update($id, $request->validated()));
    }

    public function destroy(int $id): Response
    {
        $this->products->delete($id);

        return response()->noContent();
    }
}

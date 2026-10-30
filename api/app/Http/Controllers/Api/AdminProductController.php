<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FilterProductsRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\AdminProductResource;
use App\Services\ProductService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AdminProductController extends Controller
{
    public function index(FilterProductsRequest $request, ProductService $products): AnonymousResourceCollection
    {
        $records = $products->listAll((int) $request->validated('per_page', 20), $request->validated());

        return AdminProductResource::collection($records);
    }

    public function show(int $id, ProductService $products): AdminProductResource
    {
        $record = $products->findById($id);

        return new AdminProductResource($record);
    }

    public function store(StoreProductRequest $request, ProductService $products): AdminProductResource
    {
        $product = $products->create($request->validated());

        return new AdminProductResource($product);
    }

    public function update(UpdateProductRequest $request, int $id, ProductService $products): AdminProductResource
    {
        $product = $products->update($id, $request->validated());

        return new AdminProductResource($product);
    }

    public function destroy(int $id, ProductService $products): Response
    {
        $products->delete($id);

        return response()->noContent();
    }
}

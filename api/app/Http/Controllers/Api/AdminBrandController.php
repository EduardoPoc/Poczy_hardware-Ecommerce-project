<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Services\BrandService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AdminBrandController extends Controller
{
    public function index(BrandService $brands): AnonymousResourceCollection
    {
        $brands = $brands->listAll();

        return BrandResource::collection($brands);
    }

    public function show(int $brand, BrandService $brands): BrandResource
    {
        $brand = $brands->findById($brand);

        return new BrandResource($brand);
    }

    public function store(StoreBrandRequest $request, BrandService $brands): BrandResource
    {
        $brand = $brands->create($request->validated());

        return new BrandResource($brand);
    }

    public function update(UpdateBrandRequest $request, int $brand, BrandService $brands): BrandResource
    {
        $brand = $brands->update($brand, $request->validated());

        return new BrandResource($brand);
    }

    public function destroy(int $brand, BrandService $brands): Response
    {
        $brands->delete($brand);

        return response()->noContent();
    }
}

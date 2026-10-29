<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBrandRequest;
use App\Http\Requests\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Services\BrandService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BrandController extends Controller
{
    public function __construct(private BrandService $brands) {}

    public function index(): AnonymousResourceCollection
    {
        return BrandResource::collection($this->brands->listAll());
    }

    public function show(int $brand): BrandResource
    {
        return new BrandResource($this->brands->findById($brand));
    }

    public function store(StoreBrandRequest $request): BrandResource
    {
        return new BrandResource($this->brands->create($request->validated()));
    }

    public function update(UpdateBrandRequest $request, int $brand): BrandResource
    {
        return new BrandResource($this->brands->update($brand, $request->validated()));
    }

    public function destroy(int $brand): Response
    {
        $this->brands->delete($brand);

        return response()->noContent();
    }
}

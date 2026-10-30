<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Services\BrandService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
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
}

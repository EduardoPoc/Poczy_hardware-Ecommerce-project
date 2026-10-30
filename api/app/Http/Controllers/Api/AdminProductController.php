<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Http\Resources\AdminProductResource;
use App\Services\ProductService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminProductController extends Controller
{
    public function __construct(private ProductService $products) {}

    public function index(PaginationRequest $request): AnonymousResourceCollection
    {
        return AdminProductResource::collection($this->products->listAll($request->perPage()));
    }

    public function show(int $id): AdminProductResource
    {
        return new AdminProductResource($this->products->findById($id));
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Http\Requests\StoreStockMovementRequest;
use App\Http\Resources\StockMovementResource;
use App\Services\StockService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockMovementController extends Controller
{
    public function __construct(private StockService $stock) {}

    public function index(PaginationRequest $request, int $id): AnonymousResourceCollection
    {
        return StockMovementResource::collection($this->stock->listMovements($id, $request->perPage()));
    }

    public function store(StoreStockMovementRequest $request, int $id): StockMovementResource
    {
        return new StockMovementResource($this->stock->create($id, $request->validated()));
    }
}

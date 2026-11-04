<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Http\Requests\StoreStockMovementRequest;
use App\Http\Resources\StockMovementResource;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockMovementController extends Controller
{
    public function index(PaginationRequest $request, int $id, StockService $stock): AnonymousResourceCollection
    {
        $movements = $stock->listMovements($id, (int) $request->validated('per_page', 20));

        return StockMovementResource::collection($movements);
    }

    public function store(StoreStockMovementRequest $request, int $id, StockService $stock): JsonResponse
    {
        $movement = $stock->create($id, $request->validated());

        return (new StockMovementResource($movement))->response()->setStatusCode(201);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Http\Requests\StoreStockMovementRequest;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;

class StockMovementController extends Controller
{
    public function index(PaginationRequest $request, int $id, StockService $stock): JsonResponse
    {
        $movements = $stock->listMovements($id, (int) $request->validated('per_page', 20));

        return response()->json($movements);
    }

    public function store(StoreStockMovementRequest $request, int $id, StockService $stock): JsonResponse
    {
        $movement = $stock->create($id, $request->validated());

        return response()->json($movement, 201);
    }
}

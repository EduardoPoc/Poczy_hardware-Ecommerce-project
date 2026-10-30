<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index(Request $request, int $id, StockService $stock): JsonResponse
    {
        $movements = $stock->listMovements($id, $request->input('per_page', 20));

        return response()->json($movements);
    }

    public function store(Request $request, int $id, StockService $stock): JsonResponse
    {
        $movement = $stock->create($id, $request->all());

        return response()->json($movement, 201);
    }
}

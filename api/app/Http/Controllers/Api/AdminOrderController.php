<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function index(Request $request, OrderService $orders): JsonResponse
    {
        $orders = $orders->listAll($request->input('per_page', 20));

        return response()->json($orders);
    }

    public function show(int $id, OrderService $orders): JsonResponse
    {
        $order = $orders->findById($id);

        return response()->json($order);
    }

    public function update(Request $request, int $id, OrderService $orders): JsonResponse
    {
        $order = $orders->updateStatus($id, $request->input('status'));

        return response()->json($order);
    }
}

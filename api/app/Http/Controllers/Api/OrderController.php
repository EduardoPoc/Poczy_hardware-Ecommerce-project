<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    public function index(Request $request, OrderService $orders): JsonResponse
    {
        $orders = $orders->listUserOrders($request->user(), $request->input('per_page', 20));

        return response()->json($orders);
    }

    public function show(Request $request, int $id, OrderService $orders): JsonResponse
    {
        $order = $orders->getUserOrder($request->user(), (string) $id);

        return response()->json($order);
    }

    public function store(Request $request, OrderService $orders): JsonResponse
    {
        $order = $orders->create($request->user(), $request->all());

        return response()->json($order, 201);
    }

    public function destroy(Request $request, int $id, OrderService $orders): Response
    {
        $orders->cancel($request->user(), $id);

        return response()->noContent();
    }
}

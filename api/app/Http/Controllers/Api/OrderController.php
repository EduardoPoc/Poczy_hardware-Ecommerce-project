<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function index(PaginationRequest $request, OrderService $orders): AnonymousResourceCollection
    {
        $records = $orders->listUserOrders($request->user(), (int) $request->validated('per_page', 20));

        return OrderResource::collection($records);
    }

    public function show(Request $request, int $id, OrderService $orders): OrderResource
    {
        $order = $orders->getUserOrder($request->user(), (string) $id);

        return new OrderResource($order);
    }

    public function store(StoreOrderRequest $request, OrderService $orders): OrderResource
    {
        $order = $orders->create($request->user(), $request->validated());

        return new OrderResource($order);
    }

    public function pay(Request $request, int $id, OrderService $orders): OrderResource
    {
        if ($request->except('_token') !== []) {
            throw ValidationException::withMessages(['payment' => 'O pagamento é simulado e não recebe dados de pagamento.']);
        }

        return new OrderResource($orders->pay($request->user(), $id));
    }

    public function destroy(Request $request, int $id, OrderService $orders): Response
    {
        $orders->cancel($request->user(), $id);

        return response()->noContent();
    }
}

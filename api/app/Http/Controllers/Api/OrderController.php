<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderDetailResource;
use App\Http\Resources\OrderSummaryResource;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(PaginationRequest $request): AnonymousResourceCollection
    {
        return OrderSummaryResource::collection($this->orders->listUserOrders($request->user(), $request->perPage()));
    }

    public function show(Request $request, string $id): OrderDetailResource
    {
        return new OrderDetailResource($this->orders->getUserOrder($request->user(), $id));
    }

    public function store(StoreOrderRequest $request): OrderDetailResource
    {
        return new OrderDetailResource($this->orders->create($request->user(), $request->validated()));
    }

    public function destroy(Request $request, int $id): Response
    {
        $this->orders->cancel($request->user(), $id);

        return response()->noContent();
    }
}

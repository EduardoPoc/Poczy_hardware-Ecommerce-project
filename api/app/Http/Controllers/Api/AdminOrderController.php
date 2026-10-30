<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\AdminOrderResource;
use App\Http\Resources\OrderSummaryResource;
use App\Services\OrderService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminOrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(PaginationRequest $request): AnonymousResourceCollection
    {
        return OrderSummaryResource::collection($this->orders->listAll($request->perPage()));
    }

    public function show(int $id): AdminOrderResource
    {
        return new AdminOrderResource($this->orders->findById($id));
    }

    public function update(UpdateOrderStatusRequest $request, int $id): AdminOrderResource
    {
        return new AdminOrderResource($this->orders->updateStatus($id, $request->validated('status')));
    }
}

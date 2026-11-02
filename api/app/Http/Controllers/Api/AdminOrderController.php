<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaginationRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\AdminOrderResource;
use App\Services\OrderService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminOrderController extends Controller
{
    public function index(PaginationRequest $request, OrderService $orders): AnonymousResourceCollection
    {
        $records = $orders->listAll((int) $request->validated('per_page', 20));

        return AdminOrderResource::collection($records);
    }

    public function show(int $id, OrderService $orders): AdminOrderResource
    {
        $order = $orders->findById($id);

        return new AdminOrderResource($order);
    }

    public function update(UpdateOrderStatusRequest $request, int $id, OrderService $orders): AdminOrderResource
    {
        $order = $orders->updateStatus($id, $request->validated('status'));

        return new AdminOrderResource($order);
    }
}

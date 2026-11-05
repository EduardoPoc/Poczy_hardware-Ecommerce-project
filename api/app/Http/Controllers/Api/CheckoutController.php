<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __invoke(StoreCheckoutRequest $request, CheckoutService $checkout): JsonResponse
    {
        $order = $checkout->complete($request->user(), $request->validated());

        return (new OrderResource($order))->response()->setStatusCode(201);
    }
}

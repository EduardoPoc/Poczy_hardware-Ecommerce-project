<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(Request $request, CartService $carts): JsonResponse
    {
        return (new CartResource($carts->getForRequest($request)))->response()->setStatusCode(200);
    }

    public function storeItem(StoreCartItemRequest $request, CartService $carts): CartResource
    {
        return new CartResource($carts->addForRequest($request, (int) $request->validated('product_id'), (int) $request->validated('quantity')));
    }

    public function updateItem(UpdateCartItemRequest $request, int $product, CartService $carts): CartResource
    {
        return new CartResource($carts->setQuantityForRequest($request, $product, (int) $request->validated('quantity')));
    }

    public function destroyItem(Request $request, int $product, CartService $carts): CartResource
    {
        return new CartResource($carts->setQuantityForRequest($request, $product, 0));
    }
}

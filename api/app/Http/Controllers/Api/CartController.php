<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCartItemRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function show(Request $request, CartService $carts): JsonResponse
    {
        return $this->cartResponse($carts->getForRequest($request), $request);
    }

    public function storeItem(StoreCartItemRequest $request, CartService $carts): JsonResponse
    {
        $cart = $carts->addForRequest(
            $request,
            (int) $request->validated('product_id'),
            (int) $request->validated('quantity'),
        );

        return $this->cartResponse($cart, $request);
    }

    public function updateItem(UpdateCartItemRequest $request, int $product, CartService $carts): JsonResponse
    {
        $cart = $carts->setQuantityForRequest($request, $product, (int) $request->validated('quantity'));

        return $this->cartResponse($cart, $request);
    }

    public function destroyItem(Request $request, int $product, CartService $carts): JsonResponse
    {
        return $this->cartResponse($carts->setQuantityForRequest($request, $product, 0), $request);
    }

    /**
     * Serializa com a requisição usada pelo serviço. Os atributos do FormRequest,
     * incluindo o token recém-criado, não são copiados para a requisição original.
     */
    private function cartResponse(Cart $cart, Request $request): JsonResponse
    {
        return (new CartResource($cart))->response($request)->setStatusCode(200);
    }
}

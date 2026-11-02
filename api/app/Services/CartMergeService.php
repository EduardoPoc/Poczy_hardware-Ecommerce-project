<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CartMergeService
{
    public function __construct(private CartService $carts) {}

    public function validateGuest(Request $request): void
    {
        if (! $request->hasHeader('X-Cart-Token')) {
            return;
        }

        if ($this->carts->findGuestForRequest($request) === null) {
            throw new NotFoundHttpException('Carrinho de visitante não encontrado.');
        }
    }

    /** @return array{cart: Cart, adjustments: list<array{product_id: int, requested_quantity: int, quantity: int, reason: string, message: string}>}|null */
    public function merge(User $user, Request $request): ?array
    {
        if (! $request->hasHeader('X-Cart-Token')) {
            return null;
        }

        return DB::transaction(function () use ($user, $request): array {
            User::lockForUpdate()->findOrFail($user->id);
            $account = $this->carts->getUserCart($user);
            $account = Cart::lockForUpdate()->findOrFail($account->id);
            $guest = $this->carts->findGuestForRequest($request, true);
            if ($guest === null) {
                throw new NotFoundHttpException('Carrinho de visitante não encontrado.');
            }

            $guestItems = $guest->items()->orderBy('product_id')->lockForUpdate()->get();
            $accountItems = $account->items()->orderBy('product_id')->lockForUpdate()->get()->keyBy('product_id');
            $productIds = $guestItems->pluck('product_id');
            $categoryIds = Product::whereIn('id', $productIds)->pluck('category_id')->unique();
            $categories = Category::whereIn('id', $categoryIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $products = Product::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $adjustments = [];

            foreach ($guestItems as $guestItem) {
                $productId = (int) $guestItem->product_id;
                $product = $products->get($productId);
                $accountItem = $accountItems->get($productId);
                $requestedQuantity = $guestItem->quantity + ($accountItem?->quantity ?? 0);
                if ($product !== null && ! $categories->has($product->category_id)) {
                    throw new ConflictHttpException('A categoria do produto foi alterada. Tente novamente.');
                }
                $category = $product === null ? null : $categories->get($product->category_id);
                if ($product === null || ! $product->is_active || ! $category?->is_active) {
                    $accountItem?->delete();
                    $adjustments[] = [
                        'product_id' => $productId,
                        'requested_quantity' => $requestedQuantity,
                        'quantity' => 0,
                        'reason' => 'unavailable',
                        'message' => 'O produto foi removido do carrinho porque não está disponível.',
                    ];

                    continue;
                }

                $quantity = min($requestedQuantity, $product->stock);
                if ($quantity === 0) {
                    $accountItem?->delete();
                } elseif ($accountItem !== null) {
                    $accountItem->update(['quantity' => $quantity]);
                } else {
                    $account->items()->create(['product_id' => $productId, 'quantity' => $quantity]);
                }
                if ($quantity < $requestedQuantity) {
                    $adjustments[] = [
                        'product_id' => $productId,
                        'requested_quantity' => $requestedQuantity,
                        'quantity' => $quantity,
                        'reason' => 'stock_limit',
                        'message' => 'A quantidade foi ajustada de '.$requestedQuantity.' para '.$quantity.' conforme o estoque disponível.',
                    ];
                }
            }

            $guest->delete();
            $account = $this->carts->details($account);
            DB::afterCommit(function () use ($request): void {
                $request->session()->forget(['guest_cart_id', 'guest_cart_binding']);
                $request->attributes->remove('issued_cart_token');
            });

            return ['cart' => $account, 'adjustments' => $adjustments];
        }, 3);
    }
}

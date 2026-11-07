<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
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
            throw new NotFoundHttpException('Guest cart not found.');
        }
    }

    /**
     * Remove o vínculo do visitante somente após o commit, para preservá-lo em caso de falha.
     *
     * @return array{cart: Cart, adjustments: list<array{product_id: int, requested_quantity: int, quantity: int, reason: string, message: string}>}|null
     */
    public function merge(User $user, Request $request): ?array
    {
        if (! $request->hasHeader('X-Cart-Token') &&
            (! $request->boolean('merge_cart') || $this->carts->findGuestForRequest($request) === null)) {
            return null;
        }

        return DB::transaction(function () use ($user, $request): array {
            User::lockForUpdate()->findOrFail($user->id);
            $account = $this->carts->getUserCart($user);
            $account = Cart::lockForUpdate()->findOrFail($account->id);
            $guest = $this->carts->findGuestForRequest($request, true);
            if ($guest === null) {
                throw new NotFoundHttpException('Guest cart not found.');
            }

            $adjustments = $this->mergeItems($account, $guest);

            $guest->delete();
            $account = $this->carts->details($account);
            DB::afterCommit(function () use ($request): void {
                $request->session()->forget(['guest_cart_id', 'guest_cart_binding']);
                $request->attributes->remove('issued_cart_token');
            });

            return ['cart' => $account, 'adjustments' => $adjustments];
        }, 3);
    }

    /**
     * Os carrinhos já estão bloqueados. Categorias e produtos são bloqueados antes de
     * escrever itens, seguindo a ordem da limpeza de produtos indisponíveis do catálogo.
     *
     * @return list<array{product_id: int, requested_quantity: int, quantity: int, reason: string, message: string}>
     */
    private function mergeItems(Cart $account, Cart $guest): array
    {
        $guestItems = $guest->items()->orderBy('product_id')->get();
        $accountItems = $account->items()->orderBy('product_id')->get()->keyBy('product_id');
        $productIds = $guestItems->pluck('product_id');
        $categoryIds = Product::whereIn('id', $productIds)->pluck('category_id')->unique();
        $categories = Category::whereIn('id', $categoryIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $products = Product::whereIn('id', $productIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $adjustments = [];

        foreach ($guestItems as $guestItem) {
            $productId = (int) $guestItem->product_id;
            $product = $products->get($productId);
            $accountItem = $accountItems->get($productId);
            $category = $product === null ? null : $categories->get($product->category_id);
            if ($product !== null && $category === null) {
                throw new ConflictHttpException('The product category has changed. Try again.');
            }
            $adjustment = $this->mergeItem($account, $guestItem, $accountItem, $product, $category);
            if ($adjustment !== null) {
                $adjustments[] = $adjustment;
            }
        }

        return $adjustments;
    }

    /** @return array{product_id: int, requested_quantity: int, quantity: int, reason: string, message: string}|null */
    private function mergeItem(Cart $account, CartItem $guestItem, ?CartItem $accountItem, ?Product $product, ?Category $category): ?array
    {
        $productId = (int) $guestItem->product_id;
        $requestedQuantity = $guestItem->quantity + ($accountItem?->quantity ?? 0);
        if ($product === null || ! $product->is_active || ! $category?->is_active) {
            $accountItem?->delete();

            return [
                'product_id' => $productId,
                'requested_quantity' => $requestedQuantity,
                'quantity' => 0,
                'reason' => 'unavailable',
                'message' => 'The product was removed from the cart because it is unavailable.',
            ];
        }

        $quantity = min($requestedQuantity, $product->stock);
        $this->saveItemQuantity($account, $accountItem, $productId, $quantity);
        if ($quantity === $requestedQuantity) {
            return null;
        }

        return [
            'product_id' => $productId,
            'requested_quantity' => $requestedQuantity,
            'quantity' => $quantity,
            'reason' => 'stock_limit',
            'message' => 'The quantity was adjusted from '.$requestedQuantity.' to '.$quantity.' according to available stock.',
        ];
    }

    private function saveItemQuantity(Cart $cart, ?CartItem $item, int $productId, int $quantity): void
    {
        if ($quantity === 0) {
            $item?->delete();
        } elseif ($item !== null) {
            $item->update(['quantity' => $quantity]);
        } else {
            $cart->items()->create(['product_id' => $productId, 'quantity' => $quantity]);
        }
    }
}

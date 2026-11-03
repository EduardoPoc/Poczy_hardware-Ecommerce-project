<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class CartService
{
    public function getForRequest(Request $request): Cart
    {
        if ($request->user() !== null) {
            return $this->details($this->getUserCart($request->user()));
        }

        $cart = $this->findGuestForRequest($request);
        if ($cart !== null) {
            return $this->details($cart);
        }

        $token = bin2hex(random_bytes(32));
        $binding = bin2hex(random_bytes(32));
        $cart = Cart::create([
            'token_hash' => hash('sha256', $token),
            'session_binding_hash' => hash('sha256', $binding),
        ]);
        $request->session()->put(['guest_cart_id' => $cart->id, 'guest_cart_binding' => $binding]);
        $request->attributes->set('issued_cart_token', $token);

        return $this->details($cart);
    }

    public function getUserCart(User $user): Cart
    {
        return DB::transaction(function () use ($user): Cart {
            User::lockForUpdate()->findOrFail($user->id);

            return Cart::firstOrCreate(['user_id' => $user->id]);
        }, 3);
    }

    public function findGuestForRequest(Request $request, bool $lockForUpdate = false): ?Cart
    {
        $token = $request->header('X-Cart-Token');
        $cartId = $request->session()->get('guest_cart_id');
        $binding = $request->session()->get('guest_cart_binding');
        if ($cartId === null || ! is_string($binding)) {
            if ($token !== null) {
                throw new NotFoundHttpException('Carrinho de visitante não encontrado.');
            }

            return null;
        }

        $query = Cart::whereNull('user_id')->whereKey($cartId);
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }
        $cart = $query->first();
        if ($cart === null && $token === null) {
            $request->session()->forget(['guest_cart_id', 'guest_cart_binding']);

            return null;
        }
        if ($cart === null || ! is_string($cart->session_binding_hash) ||
            ! hash_equals($cart->session_binding_hash, hash('sha256', $binding)) ||
            ($token !== null && (! is_string($token) || ! hash_equals($cart->token_hash ?? '', hash('sha256', $token))))) {
            throw new NotFoundHttpException('Carrinho de visitante não encontrado.');
        }

        return $cart;
    }

    public function add(Cart $cart, int $productId, int $quantity): Cart
    {
        return $this->changeQuantity($cart, $productId, $quantity, true);
    }

    public function setQuantity(Cart $cart, int $productId, int $quantity): Cart
    {
        return $this->changeQuantity($cart, $productId, $quantity, false);
    }

    public function remove(Cart $cart, int $productId): Cart
    {
        return $this->changeQuantity($cart, $productId, 0, false);
    }

    public function addForRequest(Request $request, int $productId, int $quantity): Cart
    {
        return $this->changeQuantityForRequest($request, $productId, $quantity, true);
    }

    public function setQuantityForRequest(Request $request, int $productId, int $quantity): Cart
    {
        return $this->changeQuantityForRequest($request, $productId, $quantity, false);
    }

    public function details(Cart $cart): Cart
    {
        $cart->load(['items' => fn (HasMany $query): HasMany => $query->orderBy('id'), 'items.product']);
        $items = $cart->items->filter(fn (CartItem $item): bool => $item->product !== null)->values();
        $productsTotal = BigDecimal::zero()->toScale(2);
        foreach ($items as $item) {
            $unitPrice = (string) $item->product->price;
            $lineTotal = (string) BigDecimal::of($unitPrice)->multipliedBy($item->quantity)->toScale(2);
            $item->setAttribute('unit_price', $unitPrice);
            $item->setAttribute('line_total', $lineTotal);
            $productsTotal = $productsTotal->plus($lineTotal);
        }
        $cart->setRelation('items', $items);
        $cart->setAttribute('items_count', $items->count());
        $cart->setAttribute('items_quantity', $items->sum(fn (CartItem $item): int => $item->quantity));
        $cart->setAttribute('products_total', (string) $productsTotal->toScale(2));

        return $cart;
    }

    private function changeQuantityForRequest(Request $request, int $productId, int $quantity, bool $increment): Cart
    {
        $guestSession = $request->session()->only(['guest_cart_id', 'guest_cart_binding']);

        try {
            return DB::transaction(fn (): Cart => $this->changeQuantity($this->getForRequest($request), $productId, $quantity, $increment), 3);
        } catch (Throwable $exception) {
            if ($request->attributes->has('issued_cart_token')) {
                $request->session()->forget(['guest_cart_id', 'guest_cart_binding']);
                $request->session()->put($guestSession);
                $request->attributes->remove('issued_cart_token');
            }

            throw $exception;
        }
    }

    private function changeQuantity(Cart $cart, int $productId, int $quantity, bool $increment): Cart
    {
        if ($quantity < 0 || $quantity > 2147483647) {
            throw ValidationException::withMessages(['quantity' => 'Informe uma quantidade inteira entre zero e 2147483647.']);
        }

        return DB::transaction(function () use ($cart, $productId, $quantity, $increment): Cart {
            $cart = Cart::lockForUpdate()->findOrFail($cart->id);
            if ($quantity === 0) {
                $cart->items()->where('product_id', $productId)->delete();

                return $this->details($cart);
            }

            $categoryId = Product::findOrFail($productId)->category_id;
            $category = Category::lockForUpdate()->findOrFail($categoryId);
            $product = Product::lockForUpdate()->findOrFail($productId);
            if ($product->category_id !== $category->id) {
                throw new ConflictHttpException('A categoria do produto foi alterada. Tente novamente.');
            }
            if (! $product->is_active || ! $category->is_active || $product->stock === 0) {
                throw ValidationException::withMessages(['product_id' => 'O produto não está disponível.']);
            }
            $item = $cart->items()->where('product_id', $productId)->first();
            $desiredQuantity = $quantity + ($increment ? ($item?->quantity ?? 0) : 0);
            if ($desiredQuantity > $product->stock || $desiredQuantity > 2147483647) {
                throw ValidationException::withMessages(['quantity' => 'A quantidade não pode ultrapassar o estoque disponível.']);
            }
            $cart->items()->updateOrCreate(['product_id' => $productId], ['quantity' => $desiredQuantity]);

            return $this->details($cart);
        }, 3);
    }
}

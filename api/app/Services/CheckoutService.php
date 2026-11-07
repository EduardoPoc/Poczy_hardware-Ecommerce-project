<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CheckoutService
{
    public function __construct(private OrderService $orders) {}

    /** @param array{address_id: int, payment_method: string, checkout_token: string, expected_total: string} $data */
    public function complete(User $user, array $data): Order
    {
        return DB::transaction(function () use ($user, $data): Order {
            User::lockForUpdate()->findOrFail($user->id);
            $existing = $user->orders()->where('checkout_token', $data['checkout_token'])->first();
            if ($existing !== null) {
                return $this->orders->getUserOrder($user, (string) $existing->id);
            }

            $cart = Cart::where('user_id', $user->id)->lockForUpdate()->first();
            $items = $cart?->items()->orderBy('product_id')->get(['product_id', 'quantity']);
            if ($items === null || $items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'Add products to the cart before checking out.']);
            }
            if ($items->count() > 100 || $items->contains(fn (CartItem $item): bool => $item->quantity < 1 || $item->quantity > 10000)) {
                throw ValidationException::withMessages(['cart' => 'An order accepts up to 100 products and up to 10,000 units per product. Adjust your cart.']);
            }

            $order = $this->orders->create($user, [
                'address_id' => $data['address_id'],
                'payment_method' => 'FAKE_PIX',
                'items' => $items->toArray(),
            ]);
            if (! BigDecimal::of($order->total)->isEqualTo($data['expected_total'])) {
                throw new ConflictHttpException('The cart amount has changed. Review the new total before confirming Fake Pix.');
            }
            $order->forceFill(['checkout_token' => $data['checkout_token']])->save();
            $order = $this->orders->pay($user, $order->id);
            $cart->items()->delete();

            return $order;
        }, 3);
    }
}

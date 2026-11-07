<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;

class DemoOrdersSeeder extends DemoSeeder
{
    protected function seedDemoData(): void
    {
        $user = User::firstOrCreate(['email' => 'pedidos.demo@example.com'], [
            'name' => 'Cliente de demonstração de pedidos', 'password' => 'PoczyDemo!2026',
        ]);
        $address = $user->addresses()->firstOrCreate([
            'postal_code' => '01001-000', 'street' => 'Rua de Demonstração', 'number' => '100',
        ], ['city' => 'São Paulo', 'state' => 'SP']);
        $existing = $user->orders()->where('checkout_token', '00000000-0000-4000-8000-000000000001')->with('items.product')->first();
        $product = $existing?->items->first()?->product
            ?? Product::where('is_active', true)->where('stock', '>=', 5)->orderBy('id')->firstOrFail();
        $cart = Cart::firstOrCreate(['user_id' => $user->id]);
        $previousItems = $cart->items()->get(['product_id', 'quantity'])->toArray();
        $orders = app(OrderService::class);
        foreach (['PENDING', 'PAID', 'SHIPPED', 'DELIVERED', 'CANCELLED'] as $index => $status) {
            $token = '00000000-0000-4000-8000-'.str_pad((string) ($index + 1), 12, '0', STR_PAD_LEFT);
            if ($user->orders()->where('checkout_token', $token)->exists()) {
                continue;
            }
            $order = $orders->create($user, [
                'address_id' => $address->id, 'payment_method' => 'FAKE_PIX',
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ]);
            $order->forceFill(['checkout_token' => $token])->save();
            if ($status === 'CANCELLED') {
                $orders->cancel($user, $order->id);

                continue;
            }
            if ($status !== 'PENDING') {
                $orders->pay($user, $order->id);
            }
            if (in_array($status, ['SHIPPED', 'DELIVERED'], true)) {
                $orders->updateStatus($order->id, 'SHIPPED');
            }
            if ($status === 'DELIVERED') {
                $orders->updateStatus($order->id, 'DELIVERED');
            }
        }
        foreach ($previousItems as $item) {
            $cart->items()->firstOrCreate(['product_id' => $item['product_id']], ['quantity' => $item['quantity']]);
        }
        $cart->items()->firstOrCreate(['product_id' => $product->id], ['quantity' => 1]);
        $guest = Cart::firstOrCreate([
            'token_hash' => hash_hmac('sha256', 'demo-guest-cart', (string) config('app.key')),
        ], ['session_binding_hash' => hash_hmac('sha256', 'demo-guest-binding', (string) config('app.key'))]);
        $guest->items()->firstOrCreate(['product_id' => $product->id], ['quantity' => 1]);
        Product::firstOrCreate([
            'category_id' => Category::where('name', 'Periféricos')->sole()->id,
            'name' => 'Mouse de demonstração sem estoque',
        ], ['price' => '49.90', 'stock' => 0, 'is_active' => true]);
    }
}

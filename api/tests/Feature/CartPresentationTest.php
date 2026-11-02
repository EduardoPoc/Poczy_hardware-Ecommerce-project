<?php

namespace Tests\Feature;

use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Tests\TestCase;

class CartPresentationTest extends TestCase
{
    private function cart(Product $product, int $quantity): Cart
    {
        $item = new CartItem(['product_id' => 1, 'quantity' => $quantity]);
        $item->setRelation('product', $product);
        $cart = new Cart(['token_hash' => 'private-hash', 'session_binding_hash' => 'private-binding']);
        $cart->setRelation('items', new Collection([$item]));

        return $cart;
    }

    public function test_prices_are_read_from_the_product_and_totals_keep_decimal_precision(): void
    {
        $product = new Product(['name' => 'Peça', 'price' => '0.10', 'cost' => '0.05']);
        $cart = $this->cart($product, 3);
        $request = Request::create('/api/cart');
        $data = (new CartResource($cart))->response($request)->getData(true)['data'];
        $this->assertSame('0.30', $data['products_total']);
        $this->assertSame('0.10', $data['items'][0]['unit_price']);
        $this->assertSame('0.30', $data['items'][0]['line_total']);
        $this->assertArrayNotHasKey('cost', $data['items'][0]['product']);

        $product->price = '0.20';
        $data = (new CartResource($cart))->response($request)->getData(true)['data'];
        $this->assertSame('0.60', $data['products_total']);
        $this->assertSame('0.20', $data['items'][0]['unit_price']);
        $this->assertSame('0.60', $data['items'][0]['line_total']);
    }

    public function test_guest_token_is_only_returned_when_issued_and_secrets_are_never_serialized(): void
    {
        $cart = new Cart(['token_hash' => 'private-hash', 'session_binding_hash' => 'private-binding']);
        $cart->setRelation('items', new Collection);
        $request = Request::create('/api/cart');
        $data = (new CartResource($cart))->response($request)->getData(true)['data'];
        $this->assertSame('0.00', $data['products_total']);
        $this->assertArrayNotHasKey('cart_token', $data);
        $this->assertArrayNotHasKey('token_hash', $data);
        $this->assertArrayNotHasKey('session_binding_hash', $data);

        $request->attributes->set('issued_cart_token', 'newly-issued-token');
        $data = (new CartResource($cart))->response($request)->getData(true)['data'];
        $this->assertSame('newly-issued-token', $data['cart_token']);
    }

    public function test_invalid_cart_inputs_return_validation_errors_before_accessing_storage(): void
    {
        $this->postJson('/api/cart/items', [])->assertUnprocessable()->assertJsonValidationErrors(['product_id', 'quantity']);
        foreach ([-1, 1.5, null, 'invalid', 2147483648] as $quantity) {
            $this->patchJson('/api/cart/items/1', ['quantity' => $quantity])
                ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        }
        $this->patchJson('/api/cart/items/invalid', ['quantity' => 1])->assertNotFound();
    }

    public function test_a_guest_token_without_its_visitor_session_is_rejected(): void
    {
        $this->getJson('/api/cart', ['X-Cart-Token' => str_repeat('a', 64)])
            ->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_first_cart_read_keeps_http_200_even_for_a_new_model(): void
    {
        $cart = new Cart;
        $cart->wasRecentlyCreated = true;
        $cart->setRelation('items', new Collection);
        $this->mock(CartService::class)->shouldReceive('getForRequest')->once()->andReturn($cart);
        $this->getJson('/api/cart')->assertOk()->assertJsonPath('data.products_total', '0.00');
    }

    public function test_frontend_can_send_cart_and_csrf_headers_with_session_cookies(): void
    {
        config(['cors.allowed_origins' => ['http://localhost:5173']]);
        foreach (['/api/cart/items', '/sanctum/csrf-cookie'] as $path) {
            $response = $this->options($path, [], [
                'Origin' => 'http://localhost:5173',
                'Access-Control-Request-Method' => $path === '/api/cart/items' ? 'POST' : 'GET',
                'Access-Control-Request-Headers' => 'X-Cart-Token,X-XSRF-TOKEN',
            ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
                ->assertHeader('Access-Control-Allow-Credentials', 'true');
            $headers = strtolower($response->headers->get('Access-Control-Allow-Headers'));
            $this->assertStringContainsString('x-cart-token', $headers);
            $this->assertStringContainsString('x-xsrf-token', $headers);
        }
    }
}

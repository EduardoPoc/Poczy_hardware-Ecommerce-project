<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartMergeTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private Product $guestOnlyProduct;

    private Product $accountOnlyProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $category = Category::create(['name' => 'Peças']);
        $this->product = Product::create(['category_id' => $category->id, 'name' => 'Compartilhado', 'price' => '0.10', 'stock' => 10])->refresh();
        $this->guestOnlyProduct = Product::create(['category_id' => $category->id, 'name' => 'Visitante', 'price' => '0.20', 'stock' => 10])->refresh();
        $this->accountOnlyProduct = Product::create(['category_id' => $category->id, 'name' => 'Conta', 'price' => '0.30', 'stock' => 10])->refresh();
    }

    private function customer(): User
    {
        return User::create(['name' => 'Cliente', 'email' => 'merge@example.com', 'password' => 'SenhaCliente!2026'])->refresh();
    }

    private function accountCart(User $user): Cart
    {
        return Cart::factory()->create(['user_id' => $user->id, 'token_hash' => null, 'session_binding_hash' => null]);
    }

    /** @param array<int, int> $quantities
     * @return array{0: int, 1: string, 2: string}
     */
    private function guestCart(array $quantities): array
    {
        $response = $this->getJson('/api/cart')->assertOk();
        $id = $response->json('data.id');
        $token = $response->json('data.cart_token');
        foreach ($quantities as $productId => $quantity) {
            $this->postJson('/api/cart/items', ['product_id' => $productId, 'quantity' => $quantity], ['X-Cart-Token' => $token])->assertOk();
        }

        return [$id, $token, session('guest_cart_binding')];
    }

    /** @return array{name: string, email: string, password: string, password_confirmation: string} */
    private function registrationData(): array
    {
        return [
            'name' => 'Nova Conta', 'email' => 'newmerge@example.com',
            'password' => 'NovaSenha!2026', 'password_confirmation' => 'NovaSenha!2026',
        ];
    }

    public function test_login_merges_overlapping_items_copies_guest_items_and_preserves_account_only_items(): void
    {
        $user = $this->customer();
        $account = $this->accountCart($user);
        CartItem::factory()->create(['cart_id' => $account->id, 'product_id' => $this->product->id, 'quantity' => 2]);
        CartItem::factory()->create(['cart_id' => $account->id, 'product_id' => $this->accountOnlyProduct->id, 'quantity' => 1]);
        [$guestId, $token] = $this->guestCart([$this->product->id => 3, $this->guestOnlyProduct->id => 4]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'SenhaCliente!2026'], ['X-Cart-Token' => $token])
            ->assertOk()->assertJsonPath('id', $user->id)->assertJsonPath('cart_merge.cart.id', $account->id)
            ->assertJsonPath('cart_merge.cart.user_id', $user->id)->assertJsonCount(3, 'cart_merge.cart.items')
            ->assertJsonPath('cart_merge.cart.products_total', '1.60')->assertJsonPath('cart_merge.adjustments', [])
            ->assertJsonMissingPath('password')->assertJsonMissingPath('cart_merge.cart.cart_token');
        $this->assertDatabaseHas('cart_items', ['cart_id' => $account->id, 'product_id' => $this->product->id, 'quantity' => 5]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $account->id, 'product_id' => $this->guestOnlyProduct->id, 'quantity' => 4]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $account->id, 'product_id' => $this->accountOnlyProduct->id, 'quantity' => 1]);
        $this->assertDatabaseMissing('carts', ['id' => $guestId]);
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $guestId]);
        $this->assertDatabaseCount('carts', 1);
        $this->assertFalse(session()->has('guest_cart_id'));
        $this->assertFalse(session()->has('guest_cart_binding'));
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_login_creates_an_account_cart_when_only_the_guest_cart_exists(): void
    {
        $user = $this->customer();
        [$guestId, $token] = $this->guestCart([$this->product->id => 2]);
        $cartId = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'SenhaCliente!2026'], ['X-Cart-Token' => $token])
            ->assertOk()->assertJsonPath('cart_merge.cart.user_id', $user->id)
            ->assertJsonPath('cart_merge.cart.items.0.quantity', 2)->json('cart_merge.cart.id');
        $this->assertNotSame($guestId, $cartId);
        $this->assertDatabaseHas('carts', ['id' => $cartId, 'user_id' => $user->id]);
        $this->assertDatabaseMissing('carts', ['id' => $guestId]);
        $this->assertDatabaseCount('carts', 1);
    }

    public function test_merge_caps_the_sum_at_stock_and_returns_a_user_visible_adjustment(): void
    {
        $user = $this->customer();
        $account = $this->accountCart($user);
        $this->product->update(['stock' => 5]);
        CartItem::factory()->create(['cart_id' => $account->id, 'product_id' => $this->product->id, 'quantity' => 3]);
        [, $token] = $this->guestCart([$this->product->id => 4]);
        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'SenhaCliente!2026'], ['X-Cart-Token' => $token])
            ->assertOk()->assertJsonPath('cart_merge.cart.items.0.quantity', 5)
            ->assertJsonPath('cart_merge.adjustments.0.product_id', $this->product->id)
            ->assertJsonPath('cart_merge.adjustments.0.requested_quantity', 7)
            ->assertJsonPath('cart_merge.adjustments.0.quantity', 5)
            ->assertJsonPath('cart_merge.adjustments.0.reason', 'stock_limit');
        $this->assertIsString($response->json('cart_merge.adjustments.0.message'));
        $this->assertNotSame('', $response->json('cart_merge.adjustments.0.message'));
        $this->assertDatabaseHas('cart_items', ['cart_id' => $account->id, 'product_id' => $this->product->id, 'quantity' => 5]);
    }

    public function test_merge_removes_an_item_when_available_stock_becomes_zero_and_reports_the_adjustment(): void
    {
        $user = $this->customer();
        [$guestId, $token] = $this->guestCart([$this->product->id => 3]);
        $this->product->update(['stock' => 0]);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'SenhaCliente!2026'], ['X-Cart-Token' => $token])
            ->assertOk()->assertJsonCount(0, 'cart_merge.cart.items')->assertJsonPath('cart_merge.cart.products_total', '0.00')
            ->assertJsonPath('cart_merge.adjustments.0.requested_quantity', 3)
            ->assertJsonPath('cart_merge.adjustments.0.quantity', 0)->assertJsonPath('cart_merge.adjustments.0.reason', 'stock_limit');
        $this->assertDatabaseMissing('carts', ['id' => $guestId]);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_login_skips_an_inactive_guest_only_product_and_reports_its_removal(): void
    {
        $user = $this->customer();
        $account = $this->accountCart($user);
        CartItem::factory()->create(['cart_id' => $account->id, 'product_id' => $this->accountOnlyProduct->id, 'quantity' => 1]);
        [$guestId, $token] = $this->guestCart([$this->guestOnlyProduct->id => 3]);
        $this->guestOnlyProduct->update(['is_active' => false]);

        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'SenhaCliente!2026'], ['X-Cart-Token' => $token])
            ->assertOk()->assertJsonPath('id', $user->id)
            ->assertJsonCount(1, 'cart_merge.cart.items')->assertJsonPath('cart_merge.cart.products_total', '0.30')
            ->assertJsonCount(1, 'cart_merge.adjustments')
            ->assertJsonPath('cart_merge.adjustments.0.product_id', $this->guestOnlyProduct->id)
            ->assertJsonPath('cart_merge.adjustments.0.requested_quantity', 3)
            ->assertJsonPath('cart_merge.adjustments.0.quantity', 0)
            ->assertJsonPath('cart_merge.adjustments.0.reason', 'unavailable');

        $this->assertIsString($response->json('cart_merge.adjustments.0.message'));
        $this->assertNotSame('', $response->json('cart_merge.adjustments.0.message'));
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $account->id, 'product_id' => $this->guestOnlyProduct->id]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $account->id, 'product_id' => $this->accountOnlyProduct->id, 'quantity' => 1]);
        $this->assertDatabaseMissing('carts', ['id' => $guestId]);
        $this->assertDatabaseMissing('carts', ['token_hash' => hash('sha256', $token)]);
        $this->assertSame(10, $this->guestOnlyProduct->fresh()->stock);
    }

    public function test_login_removes_an_inactive_shared_product_from_the_account_and_preserves_active_items(): void
    {
        $user = $this->customer();
        $account = $this->accountCart($user);
        CartItem::factory()->create(['cart_id' => $account->id, 'product_id' => $this->product->id, 'quantity' => 2]);
        CartItem::factory()->create(['cart_id' => $account->id, 'product_id' => $this->accountOnlyProduct->id, 'quantity' => 1]);
        [$guestId, $token] = $this->guestCart([$this->product->id => 3, $this->guestOnlyProduct->id => 2]);
        $this->product->update(['is_active' => false]);

        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'SenhaCliente!2026'], ['X-Cart-Token' => $token])
            ->assertOk()->assertJsonPath('id', $user->id)
            ->assertJsonCount(2, 'cart_merge.cart.items')->assertJsonPath('cart_merge.cart.products_total', '0.70')
            ->assertJsonCount(1, 'cart_merge.adjustments')
            ->assertJsonPath('cart_merge.adjustments.0.product_id', $this->product->id)
            ->assertJsonPath('cart_merge.adjustments.0.requested_quantity', 5)
            ->assertJsonPath('cart_merge.adjustments.0.quantity', 0)
            ->assertJsonPath('cart_merge.adjustments.0.reason', 'unavailable');

        $this->assertIsString($response->json('cart_merge.adjustments.0.message'));
        $this->assertNotSame('', $response->json('cart_merge.adjustments.0.message'));
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $account->id, 'product_id' => $this->product->id]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $account->id, 'product_id' => $this->accountOnlyProduct->id, 'quantity' => 1]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $account->id, 'product_id' => $this->guestOnlyProduct->id, 'quantity' => 2]);
        $this->assertDatabaseMissing('carts', ['id' => $guestId]);
        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_login_skips_a_guest_product_whose_category_became_inactive_and_reports_its_removal(): void
    {
        $user = $this->customer();
        $account = $this->accountCart($user);
        $category = Category::create(['name' => 'Categoria temporária']);
        $this->guestOnlyProduct->update(['category_id' => $category->id]);
        CartItem::factory()->create(['cart_id' => $account->id, 'product_id' => $this->accountOnlyProduct->id, 'quantity' => 1]);
        [$guestId, $token] = $this->guestCart([$this->guestOnlyProduct->id => 4]);
        $category->update(['is_active' => false]);

        $response = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'SenhaCliente!2026'], ['X-Cart-Token' => $token])
            ->assertOk()->assertJsonPath('id', $user->id)
            ->assertJsonCount(1, 'cart_merge.cart.items')->assertJsonPath('cart_merge.cart.products_total', '0.30')
            ->assertJsonCount(1, 'cart_merge.adjustments')
            ->assertJsonPath('cart_merge.adjustments.0.product_id', $this->guestOnlyProduct->id)
            ->assertJsonPath('cart_merge.adjustments.0.requested_quantity', 4)
            ->assertJsonPath('cart_merge.adjustments.0.quantity', 0)
            ->assertJsonPath('cart_merge.adjustments.0.reason', 'unavailable');

        $this->assertIsString($response->json('cart_merge.adjustments.0.message'));
        $this->assertNotSame('', $response->json('cart_merge.adjustments.0.message'));
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $account->id, 'product_id' => $this->guestOnlyProduct->id]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $account->id, 'product_id' => $this->accountOnlyProduct->id, 'quantity' => 1]);
        $this->assertDatabaseMissing('carts', ['id' => $guestId]);
        $this->assertTrue($this->guestOnlyProduct->fresh()->is_active);
    }

    public function test_registration_skips_an_inactive_guest_product_without_failing_or_logging_in(): void
    {
        [$guestId, $token] = $this->guestCart([$this->product->id => 2, $this->guestOnlyProduct->id => 3]);
        $this->guestOnlyProduct->update(['is_active' => false]);

        $response = $this->postJson('/api/user', $this->registrationData(), ['X-Cart-Token' => $token])
            ->assertCreated()->assertJsonPath('email', 'newmerge@example.com')
            ->assertJsonCount(1, 'cart_merge.cart.items')->assertJsonPath('cart_merge.cart.products_total', '0.20')
            ->assertJsonPath('cart_merge.cart.items.0.product_id', $this->product->id)
            ->assertJsonPath('cart_merge.cart.items.0.quantity', 2)
            ->assertJsonCount(1, 'cart_merge.adjustments')
            ->assertJsonPath('cart_merge.adjustments.0.product_id', $this->guestOnlyProduct->id)
            ->assertJsonPath('cart_merge.adjustments.0.requested_quantity', 3)
            ->assertJsonPath('cart_merge.adjustments.0.quantity', 0)
            ->assertJsonPath('cart_merge.adjustments.0.reason', 'unavailable');

        $this->assertIsString($response->json('cart_merge.adjustments.0.message'));
        $this->assertNotSame('', $response->json('cart_merge.adjustments.0.message'));
        $userId = $response->json('id');
        $cartId = $response->json('cart_merge.cart.id');
        $this->assertSame($userId, $response->json('cart_merge.cart.user_id'));
        $this->assertDatabaseHas('users', ['id' => $userId, 'email' => 'newmerge@example.com']);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $cartId, 'product_id' => $this->product->id, 'quantity' => 2]);
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $cartId, 'product_id' => $this->guestOnlyProduct->id]);
        $this->assertDatabaseMissing('carts', ['id' => $guestId]);
        $this->assertDatabaseMissing('carts', ['token_hash' => hash('sha256', $token)]);
        $this->assertGuest('web');
    }

    public function test_the_guest_token_is_invalidated_and_cannot_merge_the_items_twice(): void
    {
        $user = $this->customer();
        [, $token] = $this->guestCart([$this->product->id => 2]);
        $credentials = ['email' => $user->email, 'password' => 'SenhaCliente!2026'];
        $this->postJson('/api/login', $credentials, ['X-Cart-Token' => $token])->assertOk();
        $this->postJson('/api/login', $credentials, ['X-Cart-Token' => $token])->assertNotFound();
        $this->assertDatabaseCount('carts', 1);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', ['product_id' => $this->product->id, 'quantity' => 2]);
        $this->assertDatabaseMissing('carts', ['token_hash' => hash('sha256', $token)]);
    }

    public function test_registration_merges_the_guest_cart_into_the_new_account_without_automatic_login(): void
    {
        [$guestId, $token] = $this->guestCart([$this->product->id => 2]);
        $response = $this->postJson('/api/user', $this->registrationData(), ['X-Cart-Token' => $token])
            ->assertCreated()->assertJsonPath('email', 'newmerge@example.com')->assertJsonMissingPath('password')
            ->assertJsonPath('cart_merge.cart.items.0.quantity', 2)->assertJsonPath('cart_merge.cart.products_total', '0.20');
        $userId = $response->json('id');
        $this->assertSame($userId, $response->json('cart_merge.cart.user_id'));
        $this->assertDatabaseHas('carts', ['id' => $response->json('cart_merge.cart.id'), 'user_id' => $userId]);
        $this->assertDatabaseMissing('carts', ['id' => $guestId]);
        $this->assertFalse(session()->has('guest_cart_id'));
        $this->assertFalse(session()->has('guest_cart_binding'));
        $this->assertGuest('web');
    }

    public function test_login_without_an_explicit_token_preserves_both_carts_and_does_not_merge(): void
    {
        $user = $this->customer();
        $account = $this->accountCart($user);
        CartItem::factory()->create(['cart_id' => $account->id, 'product_id' => $this->product->id, 'quantity' => 1]);
        [$guestId, $token, $binding] = $this->guestCart([$this->product->id => 2]);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'SenhaCliente!2026'])->assertOk();
        $this->assertDatabaseHas('cart_items', ['cart_id' => $account->id, 'product_id' => $this->product->id, 'quantity' => 1]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $guestId, 'product_id' => $this->product->id, 'quantity' => 2]);
        $this->assertDatabaseHas('carts', ['id' => $guestId, 'token_hash' => hash('sha256', $token)]);
        $this->assertSame($guestId, session('guest_cart_id'));
        $this->assertSame($binding, session('guest_cart_binding'));
    }

    public function test_a_guest_token_from_another_session_is_rejected_without_changing_either_cart(): void
    {
        $user = $this->customer();
        $account = $this->accountCart($user);
        [$guestId, $token] = $this->guestCart([$this->product->id => 2]);
        $this->withSession(['guest_cart_id' => null, 'guest_cart_binding' => null])
            ->postJson('/api/login', ['email' => $user->email, 'password' => 'SenhaCliente!2026'], ['X-Cart-Token' => $token])->assertNotFound();
        $this->assertDatabaseHas('carts', ['id' => $guestId]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $guestId, 'product_id' => $this->product->id, 'quantity' => 2]);
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $account->id]);
        $this->assertDatabaseCount('carts', 2);
    }

    public function test_an_invalid_token_is_rejected_without_consuming_the_valid_guest_cart(): void
    {
        $user = $this->customer();
        [$guestId] = $this->guestCart([$this->product->id => 2]);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'SenhaCliente!2026'], ['X-Cart-Token' => str_repeat('a', 64)])
            ->assertNotFound();
        $this->assertDatabaseHas('carts', ['id' => $guestId]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $guestId, 'quantity' => 2]);
        $this->assertDatabaseCount('carts', 1);
    }

    public function test_invalid_credentials_preserve_the_guest_cart_account_cart_and_session_binding(): void
    {
        $user = $this->customer();
        $account = $this->accountCart($user);
        CartItem::factory()->create(['cart_id' => $account->id, 'product_id' => $this->product->id, 'quantity' => 1]);
        [$guestId, $token, $binding] = $this->guestCart([$this->product->id => 2]);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'], ['X-Cart-Token' => $token])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseHas('cart_items', ['cart_id' => $account->id, 'quantity' => 1]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $guestId, 'quantity' => 2]);
        $this->assertSame($guestId, session('guest_cart_id'));
        $this->assertSame($binding, session('guest_cart_binding'));
        $this->assertGuest('web');
    }

    public function test_a_late_merge_failure_rolls_back_previous_item_updates_and_preserves_guest_credentials(): void
    {
        $user = $this->customer();
        $account = $this->accountCart($user);
        CartItem::factory()->create(['cart_id' => $account->id, 'product_id' => $this->product->id, 'quantity' => 2]);
        [$guestId, $token, $binding] = $this->guestCart([$this->product->id => 3, $this->guestOnlyProduct->id => 4]);
        $lastProductId = $this->guestOnlyProduct->id;
        $failureReached = false;
        CartItem::saving(function (CartItem $item) use ($account, $lastProductId, &$failureReached): void {
            if ($item->cart_id === $account->id && $item->product_id === $lastProductId) {
                $failureReached = true;
                throw new \RuntimeException('Merge write unavailable');
            }
        });
        try {
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'SenhaCliente!2026'], ['X-Cart-Token' => $token])
                ->assertStatus(500);
        } finally {
            CartItem::flushEventListeners();
        }
        $this->assertTrue($failureReached);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $account->id, 'product_id' => $this->product->id, 'quantity' => 2]);
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $account->id, 'product_id' => $lastProductId]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $guestId, 'product_id' => $this->product->id, 'quantity' => 3]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $guestId, 'product_id' => $lastProductId, 'quantity' => 4]);
        $this->assertSame($guestId, session('guest_cart_id'));
        $this->assertSame($binding, session('guest_cart_binding'));
        $this->assertGuest('web');
    }

    public function test_failed_registration_merge_rolls_back_the_new_user_and_preserves_the_guest_cart(): void
    {
        [$guestId, $token, $binding] = $this->guestCart([$this->product->id => 2]);
        $failureReached = false;
        CartItem::saving(function (CartItem $item) use ($guestId, &$failureReached): void {
            if ($item->cart_id !== $guestId) {
                $failureReached = true;
                throw new \RuntimeException('Merge write unavailable');
            }
        });
        try {
            $this->postJson('/api/user', $this->registrationData(), ['X-Cart-Token' => $token])->assertStatus(500);
        } finally {
            CartItem::flushEventListeners();
        }
        $this->assertTrue($failureReached);
        $this->assertDatabaseMissing('users', ['email' => 'newmerge@example.com']);
        $this->assertDatabaseCount('carts', 1);
        $this->assertDatabaseHas('carts', ['id' => $guestId, 'token_hash' => hash('sha256', $token)]);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $guestId, 'product_id' => $this->product->id, 'quantity' => 2]);
        $this->assertSame($guestId, session('guest_cart_id'));
        $this->assertSame($binding, session('guest_cart_binding'));
        $this->assertGuest('web');
    }
}

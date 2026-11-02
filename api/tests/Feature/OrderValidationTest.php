<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class OrderValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $user = new User(['name' => 'Cliente', 'email' => 'client@example.com']);
        $user->id = 1;
        $user->role = 'CUSTOMER';
        $this->actingAs($user, 'web');
    }

    public function test_checkout_rejects_missing_fields_and_malformed_quantities_without_writing(): void
    {
        $this->postJson('/api/orders', [])->assertUnprocessable()->assertJsonValidationErrors(['address_id', 'payment_method', 'items']);
        foreach ([0, -1, 1.5, null, 'invalid'] as $quantity) {
            $this->postJson('/api/orders', [
                'address_id' => 1, 'payment_method' => 'PIX',
                'items' => [['product_id' => 1, 'quantity' => $quantity]],
            ])->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
        }
    }

    public function test_checkout_rejects_client_prices_totals_and_duplicate_products(): void
    {
        $data = ['address_id' => 1, 'payment_method' => 'PIX', 'items' => [['product_id' => 1, 'quantity' => 1]]];
        $this->postJson('/api/orders', $data + ['total' => '0.01'])->assertUnprocessable()->assertJsonValidationErrors('total');
        $data['items'][0]['unit_price'] = '0.01';
        $this->postJson('/api/orders', $data)->assertUnprocessable()->assertJsonValidationErrors('items.0');
        $data['items'] = [['product_id' => 1, 'quantity' => 1], ['product_id' => 1, 'quantity' => 2]];
        $this->postJson('/api/orders', $data)->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');
    }

    public function test_simulated_payment_rejects_card_data_before_accessing_the_order(): void
    {
        $this->postJson('/api/orders/1/pay', ['card_number' => '4111111111111111'])
            ->assertUnprocessable()->assertJsonValidationErrors('payment');
    }

    public function test_missing_or_malformed_administrative_status_returns_422(): void
    {
        $user = new User(['name' => 'Admin']);
        $user->id = 2;
        $user->role = 'ADMIN';
        $this->actingAs($user, 'web');
        $this->patchJson('/api/admin/orders/1', [])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->patchJson('/api/admin/orders/1', ['status' => ['PAID']])->assertUnprocessable()->assertJsonValidationErrors('status');
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::create(['name' => 'Cliente', 'email' => 'addressowner@example.com', 'password' => 'SenhaCliente!2026'])->refresh();
        $this->other = User::create(['name' => 'Outro', 'email' => 'otheraddress@example.com', 'password' => 'SenhaCliente!2026'])->refresh();
        $this->actingAs($this->customer, 'web');
    }

    /** @return array{postal_code: string, street: string, number: string, city: string, state: string} */
    private function addressData(): array
    {
        return ['postal_code' => '01001-000', 'street' => 'Rua Original', 'number' => '10', 'city' => 'São Paulo', 'state' => 'SP'];
    }

    public function test_owner_can_create_read_update_and_delete_an_address_with_resource_fields(): void
    {
        $response = $this->postJson('/api/addresses', $this->addressData())->assertCreated()
            ->assertJsonPath('data.user_id', $this->customer->id)->assertJsonPath('data.complement', null)
            ->assertJsonStructure(['data' => ['id', 'created_at', 'updated_at']]);
        $id = $response->json('data.id');
        $this->assertNotNull($response->json('data.created_at'));
        $this->assertNotNull($response->json('data.updated_at'));
        $this->getJson('/api/addresses')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/addresses/'.$id)->assertOk()->assertJsonPath('data.street', 'Rua Original');
        $this->patchJson('/api/addresses/'.$id, ['street' => 'Rua Nova', 'complement' => 'Casa'])
            ->assertOk()->assertJsonPath('data.street', 'Rua Nova')->assertJsonPath('data.city', 'São Paulo');
        $this->putJson('/api/addresses/'.$id, ['complement' => null])->assertOk()->assertJsonPath('data.complement', null);
        $this->deleteJson('/api/addresses/'.$id)->assertNoContent();
        $this->assertDatabaseMissing('addresses', ['id' => $id]);
    }

    public function test_another_owners_address_is_not_listed_and_returns_404_for_read_update_and_delete(): void
    {
        $foreign = $this->other->addresses()->create($this->addressData());
        $owned = $this->customer->addresses()->create($this->addressData());
        $this->getJson('/api/addresses')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $owned->id);
        $this->getJson('/api/addresses/'.$foreign->id)->assertNotFound();
        $this->patchJson('/api/addresses/'.$foreign->id, ['street' => 'Rua alterada'])->assertNotFound();
        $this->deleteJson('/api/addresses/'.$foreign->id)->assertNotFound();
        $this->assertDatabaseHas('addresses', ['id' => $foreign->id, 'street' => 'Rua Original']);
    }

    public function test_invalid_creation_and_partial_updates_preserve_the_existing_address(): void
    {
        $this->postJson('/api/addresses', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['postal_code', 'street', 'number', 'city', 'state']);
        $this->postJson('/api/addresses', $this->addressData() + ['user_id' => $this->other->id])
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $id = $this->postJson('/api/addresses', $this->addressData())->assertCreated()->json('data.id');
        foreach ([['street' => null], ['state' => 'XX'], ['postal_code' => 'invalid'], ['user_id' => $this->other->id]] as $invalid) {
            $this->patchJson('/api/addresses/'.$id, $invalid)->assertUnprocessable()->assertJsonValidationErrors(array_keys($invalid));
        }
        $this->assertDatabaseHas('addresses', ['id' => $id, 'user_id' => $this->customer->id, 'street' => 'Rua Original']);
        $this->assertDatabaseCount('addresses', 1);
    }
}

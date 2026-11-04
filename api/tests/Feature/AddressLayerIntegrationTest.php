<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use App\Services\AddressService;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class AddressLayerIntegrationTest extends TestCase
{
    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = new User(['name' => 'Cliente', 'email' => 'address@example.com']);
        $this->customer->forceFill(['id' => 7, 'role' => 'CUSTOMER']);
        $this->actingAs($this->customer, 'web');
    }

    /** @return array{postal_code: string, street: string, number: string, city: string, state: string} */
    private function addressData(): array
    {
        return ['postal_code' => '01001-000', 'street' => 'Rua Original', 'number' => '10', 'city' => 'São Paulo', 'state' => 'SP'];
    }

    /** @param array<string, mixed> $overrides */
    private function address(array $overrides = []): Address
    {
        return (new Address($this->addressData()))->forceFill(array_replace([
            'id' => 5, 'user_id' => $this->customer->id, 'complement' => null,
            'created_at' => '2026-11-02 12:00:00', 'updated_at' => '2026-11-02 12:00:00',
        ], $overrides));
    }

    public function test_index_delegates_the_owner_and_serializes_the_address_collection(): void
    {
        $this->mock(AddressService::class)->shouldReceive('listUserAddresses')->once()->with($this->customer)
            ->andReturn(new Collection([$this->address()]));
        $this->getJson('/api/addresses')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 5)->assertJsonPath('data.0.user_id', 7)
            ->assertJsonPath('data.0.complement', null)->assertJsonPath('data.0.created_at', '2026-11-02T12:00:00.000000Z');
    }

    public function test_show_delegates_the_owner_and_id_and_keeps_status_200_for_a_recently_created_model(): void
    {
        $address = $this->address();
        $address->wasRecentlyCreated = true;
        $this->mock(AddressService::class)->shouldReceive('getUserAddress')->once()->with($this->customer, 5)->andReturn($address);
        $this->getJson('/api/addresses/5')->assertOk()->assertJsonPath('data.id', 5)->assertJsonPath('data.street', 'Rua Original');
    }

    public function test_store_sends_only_validated_fields_and_forces_created_status(): void
    {
        $data = $this->addressData();
        $this->mock(AddressService::class)->shouldReceive('create')->once()->with($this->customer, $data)->andReturn($this->address());
        $this->postJson('/api/addresses', $data + ['id' => 999, 'created_at' => '2000-01-01'])
            ->assertCreated()->assertJsonPath('data.id', 5)->assertJsonPath('data.complement', null)
            ->assertJsonPath('data.created_at', '2026-11-02T12:00:00.000000Z');
    }

    public function test_partial_updates_delegate_only_present_validated_fields_and_keep_status_200(): void
    {
        $address = $this->address(['street' => 'Rua Nova']);
        $address->wasRecentlyCreated = true;
        $this->mock(AddressService::class)->shouldReceive('update')->once()
            ->with($this->customer, 5, ['street' => 'Rua Nova'])->andReturn($address);
        $this->patchJson('/api/addresses/5', ['street' => 'Rua Nova', 'id' => 999])
            ->assertOk()->assertJsonPath('data.street', 'Rua Nova')->assertJsonPath('data.city', 'São Paulo');
    }

    public function test_complement_can_be_explicitly_cleared_in_a_partial_update(): void
    {
        $this->mock(AddressService::class)->shouldReceive('update')->once()
            ->with($this->customer, 5, ['complement' => null])->andReturn($this->address());
        $this->putJson('/api/addresses/5', ['complement' => null])->assertOk()->assertJsonPath('data.complement', null);
    }

    public function test_destroy_delegates_the_owner_and_id_and_returns_no_body(): void
    {
        $this->mock(AddressService::class)->shouldReceive('delete')->once()->with($this->customer, 5)->andReturnNull();
        $this->deleteJson('/api/addresses/5')->assertNoContent();
    }

    public function test_store_rejects_missing_fields_before_the_service_runs(): void
    {
        $this->mock(AddressService::class)->shouldNotReceive('create');
        $this->postJson('/api/addresses', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['postal_code', 'street', 'number', 'city', 'state']);
    }

    public function test_store_rejects_invalid_types_lengths_postal_code_and_state_before_the_service_runs(): void
    {
        $this->mock(AddressService::class)->shouldNotReceive('create');
        $invalid = [
            'postal_code' => 'invalid', 'street' => str_repeat('a', 151), 'number' => [],
            'complement' => str_repeat('a', 101), 'city' => null, 'state' => 'XX',
        ];
        $this->postJson('/api/addresses', $invalid)->assertUnprocessable()->assertJsonValidationErrors(array_keys($invalid));
    }

    public function test_store_and_update_reject_an_explicit_owner_id_before_the_service_runs(): void
    {
        $service = $this->mock(AddressService::class);
        $service->shouldNotReceive('create');
        $service->shouldNotReceive('update');
        $this->postJson('/api/addresses', $this->addressData() + ['user_id' => 999])
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->patchJson('/api/addresses/5', ['user_id' => 999])
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
    }

    public function test_partial_update_rejects_invalid_present_fields_before_the_service_runs(): void
    {
        $this->mock(AddressService::class)->shouldNotReceive('update');
        foreach ([['street' => null], ['state' => 'XX'], ['postal_code' => 'invalid'], ['complement' => []]] as $invalid) {
            $this->patchJson('/api/addresses/5', $invalid)->assertUnprocessable()->assertJsonValidationErrors(array_keys($invalid));
        }
    }
}

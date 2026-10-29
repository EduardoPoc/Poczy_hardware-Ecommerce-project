<?php

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class AddressService
{
    /** @return Collection<int, Address> */
    public function listUserAddresses(User $user): Collection
    {
        return $user->addresses()->orderBy('id')->get();
    }

    public function getUserAddress(User $user, int $id): Address
    {
        return $user->addresses()->findOrFail($id);
    }

    /** @param array{postal_code: string, street: string, number: string, complement?: ?string, city: string, state: string} $data */
    public function create(User $user, array $data): Address
    {
        return $user->addresses()->create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, int $id, array $data): Address
    {
        $address = $this->getUserAddress($user, $id);
        $address->update($data);

        return $address;
    }

    public function delete(User $user, int $id): void
    {
        $this->getUserAddress($user, $id)->delete();
    }
}

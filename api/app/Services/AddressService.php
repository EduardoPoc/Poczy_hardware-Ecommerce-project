<?php

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class AddressService
{
    public function listUserAddresses(User $user): Collection
    {
        return $user->addresses()->orderBy('id')->get();
    }

    public function getUserAddress(User $user, int $id): Address
    {
        return $user->addresses()->findOrFail($id);
    }

    public function create(User $user, array $data): Address
    {
        return $user->addresses()->create($data);
    }

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

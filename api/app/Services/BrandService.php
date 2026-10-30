<?php

namespace App\Services;

use App\Models\Brand;
use Illuminate\Database\Eloquent\Collection;

class BrandService
{
    public function listAll(): Collection
    {
        return Brand::orderBy('name')->orderBy('id')->get();
    }

    public function findById(int $id): Brand
    {
        return Brand::findOrFail($id);
    }

    public function create(array $data): Brand
    {
        return Brand::create($data);
    }

    public function update(int $id, array $data): Brand
    {
        $brand = $this->findById($id);
        $brand->update($data);

        return $brand;
    }

    public function delete(int $id): void
    {
        $this->findById($id)->delete();
    }
}

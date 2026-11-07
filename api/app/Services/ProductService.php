<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class ProductService
{
    public function __construct(private SpecificationService $specifications, private StockService $stock) {}

    private function publicProducts(): Builder
    {
        return Product::where('is_active', true)
            ->where('stock', '>=', 1)
            ->whereHas('category', fn (Builder $query): Builder => $query->where('is_active', true));
    }

    public function listPublicProducts(int $perPage = 20, array $filter = []): LengthAwarePaginator
    {
        return $this->filterPublicProducts($filter, $perPage);
    }

    public function getPublicProduct(string $id): Product
    {
        return $this->publicProducts()->with(['category', 'brand'])->findOrFail($id);
    }

    public function listAllPublicProducts(): Collection
    {
        return $this->publicProducts()->with('brand')->orderBy('name')->orderBy('id')->get();
    }

    public function filterPublicProducts(array $filter = [], int $perPage = 20): LengthAwarePaginator
    {
        return $this->applyFilters($this->publicProducts(), $filter)
            ->with(['category', 'brand'])->orderBy('name')->orderBy('id')
            ->paginate($perPage)->withQueryString();
    }

    public function listAll(int $perPage = 20, array $filter = []): LengthAwarePaginator
    {
        $query = $this->applyFilters(Product::query(), $filter);
        if (array_key_exists('is_active', $filter) && $filter['is_active'] !== null) {
            $query->where('is_active', $filter['is_active']);
        }

        return $query->with(['category', 'brand'])->orderBy('name')->orderBy('id')
            ->paginate($perPage)->withQueryString();
    }

    private function applyFilters(Builder $query, array $filter): Builder
    {
        if (isset($filter['q']) && $filter['q'] !== '') {
            $query->whereLike('name', '%'.$filter['q'].'%');
        }
        if (isset($filter['category_id'])) {
            $query->where('category_id', $filter['category_id']);
        }
        if (isset($filter['min_price'])) {
            $query->where('price', '>=', $filter['min_price']);
        }
        if (isset($filter['max_price'])) {
            $query->where('price', '<=', $filter['max_price']);
        }
        if (! empty($filter['brand_ids'])) {
            $query->whereIn('brand_id', $filter['brand_ids']);
        }
        foreach ($filter['specifications'] ?? [] as $key => $values) {
            if ($values === []) {
                continue;
            }
            $query->where(function (Builder $alternatives) use ($key, $values): void {
                foreach ($values as $value) {
                    $alternatives->orWhereJsonContains('specifications->'.$key, $value);
                }
            });
        }

        return $query;
    }

    public function findById(int $id): Product
    {
        return Product::with(['category', 'brand'])->findOrFail($id);
    }

    public function create(array $data): Product
    {
        $imagePath = $this->storeImage($data);
        try {
            return DB::transaction(function () use ($data): Product {
                $category = Category::lockForUpdate()->findOrFail($data['category_id']);
                if (isset($data['brand_id'])) {
                    Brand::lockForUpdate()->findOrFail($data['brand_id']);
                }
                $this->specifications->validateValues($data['specifications'] ?? [], $category->getAttribute('attributes') ?? []);
                $initialStock = (int) ($data['stock'] ?? 0);
                $this->validateActivation((bool) ($data['is_active'] ?? true), $initialStock);
                $product = Product::create(array_merge($data, ['stock' => 0]))->refresh();
                if ($initialStock > 0) {
                    $this->stock->move($product, 'INBOUND', $initialStock, 'Estoque inicial');
                }

                return $product->load(['category', 'brand']);
            }, 3);
        } catch (Throwable $exception) {
            if ($imagePath !== null) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $exception;
        }
    }

    public function update(int $id, array $data): Product
    {
        $imagePath = $this->storeImage($data);
        try {
            return DB::transaction(function () use ($id, $data, $imagePath): Product {
                $categoryId = $data['category_id'] ?? Product::findOrFail($id)->category_id;
                $category = Category::lockForUpdate()->findOrFail($categoryId);
                $product = Product::lockForUpdate()->findOrFail($id);
                if (isset($data['brand_id'])) {
                    Brand::lockForUpdate()->findOrFail($data['brand_id']);
                }
                if (! isset($data['category_id']) && $product->category_id !== $category->id) {
                    throw new ConflictHttpException('The category was changed by another operation. Try again.');
                }
                $values = array_key_exists('specifications', $data) ? $data['specifications'] : $product->specifications;
                $this->specifications->validateValues($values ?? [], $category->getAttribute('attributes') ?? []);
                unset($data['stock']);
                $this->validateActivation((bool) ($data['is_active'] ?? $product->is_active), $product->stock);
                $previousImage = $product->image_path;
                $product->update($data);
                if (! $product->is_active || ! $category->is_active) {
                    $product->cartItems()->delete();
                }
                if ($imagePath !== null) {
                    $this->deleteImageAfterCommit($previousImage);
                }

                return $product->load(['category', 'brand']);
            }, 3);
        } catch (Throwable $exception) {
            if ($imagePath !== null) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $exception;
        }
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $product = Product::lockForUpdate()->findOrFail($id);
            if ($product->orderItems()->exists()) {
                throw new ConflictHttpException('The product belongs to orders. Use is_active to deactivate it and preserve history.');
            }
            if ($product->stockMovements()->exists()) {
                throw new ConflictHttpException('The product has stock movements. Use is_active to deactivate it.');
            }
            $product->delete();
            $this->deleteImageAfterCommit($product->image_path);
        }, 3);
    }

    private function validateActivation(bool $isActive, int $stock): void
    {
        if ($isActive && $stock < 1) {
            throw ValidationException::withMessages([
                'is_active' => 'The product must have at least 1 unit in stock to be active.',
            ]);
        }
    }

    private function storeImage(array &$data): ?string
    {
        $image = $data['image'] ?? null;
        unset($data['image']);
        if ($image === null) {
            return null;
        }

        $path = $image->store('products', 'public');
        if ($path === false) {
            throw new RuntimeException('The product image could not be saved.');
        }
        $data['image_path'] = $path;

        return $path;
    }

    private function deleteImageAfterCommit(?string $path): void
    {
        if ($path !== null && str_starts_with($path, 'products/')) {
            DB::afterCommit(function () use ($path): void {
                Storage::disk('public')->delete($path);
            });
        }
    }
}

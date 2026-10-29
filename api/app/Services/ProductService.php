<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ProductService
{
    public function __construct(private SpecificationService $specifications, private StockService $stock) {}

    /** @return Builder<Product> */
    private function publicProducts(): Builder
    {
        return Product::where('is_active', true)
            ->whereHas('category', fn (Builder $query): Builder => $query->where('is_active', true));
    }

    public function listPublicProducts(int $perPage): LengthAwarePaginator
    {
        return $this->publicProducts()->orderBy('name')->orderBy('id')
            ->paginate($perPage, ['id', 'name', 'price', 'image_url'])->withQueryString();
    }

    public function getPublicProduct(string $id): Product
    {
        return $this->publicProducts()->with(['category', 'brand'])->findOrFail($id);
    }

    /** @return Collection<int, Product> */
    public function listAllPublicProducts(): Collection
    {
        return $this->publicProducts()->with('brand')->orderBy('name')->orderBy('id')->get();
    }

    /** @param array{category_id: int, min_price?: numeric-string|int|float, max_price?: numeric-string|int|float, brand_ids?: list<int>, specifications?: array<string, list<bool|int|float|string>>} $filter */
    public function filterPublicProducts(array $filter, int $perPage): LengthAwarePaginator
    {
        $query = $this->publicProducts()->where('category_id', $filter['category_id']);
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
                    if (is_float($value) && $alternatives->getConnection()->getDriverName() === 'sqlite') {
                        // PDO binds floats as text; decode JSON to preserve numeric comparison in SQLite.
                        $alternatives->orWhereJsonContains('specifications->'.$key, new Expression("json_extract(?, '$')"));
                        $alternatives->getQuery()->addBinding(json_encode($value, JSON_THROW_ON_ERROR), 'where');
                    } else {
                        $alternatives->orWhereJsonContains('specifications->'.$key, $value);
                    }
                }
            });
        }

        return $query->orderBy('name')->orderBy('id')
            ->paginate($perPage, ['id', 'name', 'price', 'image_url'])->withQueryString();
    }

    public function listAll(int $perPage): LengthAwarePaginator
    {
        return Product::with(['category', 'brand'])->orderBy('name')->orderBy('id')->paginate($perPage)->withQueryString();
    }

    public function findById(int $id): Product
    {
        return Product::with(['category', 'brand'])->findOrFail($id);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $category = Category::lockForUpdate()->findOrFail($data['category_id']);
            if (isset($data['brand_id'])) {
                Brand::lockForUpdate()->findOrFail($data['brand_id']);
            }
            $this->specifications->validateValues($data['specifications'] ?? [], $category->getAttribute('attributes') ?? []);
            $initialStock = (int) ($data['stock'] ?? 0);
            $product = Product::create(array_merge($data, ['stock' => 0]))->refresh();
            if ($initialStock > 0) {
                $this->stock->move($product, 'INBOUND', $initialStock, 'Estoque inicial');
            }

            return $product->load(['category', 'brand']);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): Product
    {
        return DB::transaction(function () use ($id, $data): Product {
            $categoryId = $data['category_id'] ?? Product::findOrFail($id)->category_id;
            $category = Category::lockForUpdate()->findOrFail($categoryId);
            $product = Product::lockForUpdate()->findOrFail($id);
            if (isset($data['brand_id'])) {
                Brand::lockForUpdate()->findOrFail($data['brand_id']);
            }
            if (! isset($data['category_id']) && $product->category_id !== $category->id) {
                throw new ConflictHttpException('A categoria foi alterada por outra operação. Tente novamente.');
            }
            $values = array_key_exists('specifications', $data) ? $data['specifications'] : $product->specifications;
            $this->specifications->validateValues($values ?? [], $category->getAttribute('attributes') ?? []);
            unset($data['stock']);
            $product->update($data);

            return $product->load(['category', 'brand']);
        }, 3);
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $product = Product::lockForUpdate()->findOrFail($id);
            if ($product->stockMovements()->exists()) {
                throw new ConflictHttpException('O produto possui movimentações de estoque. Use is_active para desativá-lo.');
            }
            $product->delete();
        }, 3);
    }
}

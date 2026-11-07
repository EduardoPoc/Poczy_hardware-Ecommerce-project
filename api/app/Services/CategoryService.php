<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CategoryService
{
    public function __construct(private SpecificationService $specifications) {}

    public function listPublicCategories(): Collection
    {
        return Category::where('is_active', true)->orderBy('name')->orderBy('id')->get();
    }

    public function getPublicCategory(int $id): Category
    {
        return Category::where('is_active', true)->findOrFail($id);
    }

    /** @return Collection<int, Category> */
    public function listAll(): Collection
    {
        return Category::orderBy('name')->orderBy('id')->get();
    }

    public function findById(int $id): Category
    {
        return Category::findOrFail($id);
    }

    /** @return array{brands: array, attributes: array, min_price: ?string, max_price: ?string} */
    public function availableFilters(int $categoryId): array
    {
        $category = $this->getPublicCategory($categoryId);
        $products = $category->products()->where('is_active', true)->with('brand')
            ->get(['id', 'brand_id', 'price', 'specifications']);
        $attributes = [];
        foreach ($category->getAttribute('attributes') ?? [] as $key => $definition) {
            if ($key === 'brand' || ! ($definition['is_filterable'] ?? true)) {
                continue;
            }
            $values = $products->map(fn (Product $product): mixed => $product->specifications[$key] ?? null)
                ->filter(fn (mixed $value): bool => $value !== null && $value !== '')
                ->uniqueStrict()->sort()->values()->all();
            if ($values !== []) {
                $attributes[$key] = array_merge($definition, ['values' => $values]);
            }
        }

        return [
            'brands' => $products->pluck('brand')->filter()->unique('id')->sortBy('name')
                ->map(fn (Brand $brand): array => $brand->only(['id', 'name']))->values()->all(),
            'attributes' => $attributes,
            'min_price' => $products->min('price'),
            'max_price' => $products->max('price'),
        ];
    }

    /** @param array{name: string, description?: ?string, attributes?: ?array, is_active?: bool} $data */
    public function create(array $data): Category
    {
        $this->specifications->validateDefinitions($data['attributes'] ?? []);

        return Category::create($data)->refresh();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): Category
    {
        return DB::transaction(function () use ($id, $data): Category {
            $category = Category::lockForUpdate()->findOrFail($id);
            if (array_key_exists('attributes', $data)) {
                $definitions = $data['attributes'] ?? [];
                $this->specifications->validateDefinitions($definitions);
                foreach ($category->products()->cursor() as $product) {
                    $this->specifications->validateValues($product->specifications ?? [], $definitions, 'attributes.products.'.$product->id);
                }
            }
            $category->update($data);

            return $category;
        }, 3);
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $category = Category::lockForUpdate()->findOrFail($id);
            if ($category->products()->exists()) {
                throw new ConflictHttpException('The category has products. Deactivate it or remove the associations before deleting it.');
            }
            $category->delete();
        }, 3);
    }
}

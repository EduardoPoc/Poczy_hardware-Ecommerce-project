<?php

namespace App\Services;

class CatalogService
{
    public function __construct(private CategoryService $categories, private ProductService $products) {}

    public function getCatalog(): array
    {
        return [
            'categories' => $this->categories->listPublicCategories(),
            'products' => $this->products->listAllPublicProducts(),
        ];
    }
}

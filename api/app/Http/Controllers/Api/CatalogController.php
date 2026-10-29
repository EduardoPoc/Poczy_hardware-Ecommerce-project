<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CatalogResource;
use App\Services\CategoryService;
use App\Services\ProductService;

class CatalogController extends Controller
{
    public function __invoke(CategoryService $categories, ProductService $products): CatalogResource
    {
        return new CatalogResource([
            'categories' => $categories->listPublicCategories(),
            'products' => $products->listAllPublicProducts(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryDetailResource;
use App\Http\Resources\CategoryResource;
use App\Services\CategoryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function __construct(private CategoryService $categories) {}

    public function index(): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->categories->listPublicCategories());
    }

    public function show(int $id): CategoryDetailResource
    {
        return new CategoryDetailResource($this->categories->getPublicCategory($id));
    }

    public function store(StoreCategoryRequest $request): CategoryDetailResource
    {
        return new CategoryDetailResource($this->categories->create($request->validated()));
    }

    public function update(UpdateCategoryRequest $request, int $id): CategoryDetailResource
    {
        return new CategoryDetailResource($this->categories->update($id, $request->validated()));
    }

    public function destroy(int $id): Response
    {
        $this->categories->delete($id);

        return response()->noContent();
    }
}

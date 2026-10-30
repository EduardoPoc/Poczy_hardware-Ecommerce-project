<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryDetailResource;
use App\Services\CategoryService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminCategoryController extends Controller
{
    public function __construct(private CategoryService $categories) {}

    public function index(): AnonymousResourceCollection
    {
        return CategoryDetailResource::collection($this->categories->listAll());
    }

    public function show(int $id): CategoryDetailResource
    {
        return new CategoryDetailResource($this->categories->findById($id));
    }
}

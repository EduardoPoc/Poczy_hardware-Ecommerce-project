<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CatalogResource;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;

class CatalogController extends Controller
{
    public function __invoke(CatalogService $catalog): JsonResponse
    {
        return (new CatalogResource($catalog->getCatalog()))->response()->setStatusCode(200);
    }
}

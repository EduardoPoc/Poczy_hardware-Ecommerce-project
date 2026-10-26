<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/health', function (): JsonResponse {
        return response()->json([
            'status' => 'ok',
            'name' => config('app.name'),
        ]);
    })->name('health');

    Route::get('/user', function (Request $request): JsonResponse {
        return response()->json(['data' => $request->user()]);
    })->middleware('auth:sanctum')->name('user');
});

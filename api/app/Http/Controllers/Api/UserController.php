<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\CartMergeResource;
use App\Services\AuthService;
use App\Services\CartMergeService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json($user);
    }

    public function store(StoreUserRequest $request, UserService $users, CartMergeService $carts): JsonResponse
    {
        $carts->validateGuest($request);
        $payload = DB::transaction(function () use ($request, $users, $carts): array {
            $user = $users->register($request->validated());
            $merge = $carts->merge($user, $request);
            $payload = $user->toArray();
            if ($merge !== null) {
                $payload['cart_merge'] = new CartMergeResource($merge);
            }

            return $payload;
        }, 3);

        return response()->json($payload, 201);
    }

    public function update(Request $request, UserService $users): JsonResponse
    {
        $user = $users->update($request->user(), $request->all());

        return response()->json($user);
    }

    public function destroy(Request $request, UserService $users, AuthService $auth): Response
    {
        $users->delete($request->user(), $request->input('current_password'));
        $auth->logout($request->session());

        return response()->noContent();
    }
}

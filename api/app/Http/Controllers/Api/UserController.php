<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\CartMergeResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\CartMergeService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function show(Request $request, UserService $users): JsonResponse
    {
        $user = $users->getProfile($request->user());

        return (new UserResource($user))->response()->setStatusCode(200);
    }

    public function store(StoreUserRequest $request, UserService $users, CartMergeService $carts): JsonResponse
    {
        $carts->validateGuest($request);
        $payload = DB::transaction(function () use ($request, $users, $carts): array {
            $user = $users->register($request->validated());
            $merge = $carts->merge($user, $request);
            $payload = (new UserResource($user))->resolve($request);
            if ($merge !== null) {
                $payload['cart_merge'] = new CartMergeResource($merge);
            }

            return $payload;
        }, 3);

        return response()->json($payload, 201);
    }

    public function update(UpdateUserRequest $request, UserService $users): JsonResponse
    {
        $user = $users->update($request->user(), $request->validated());

        return (new UserResource($user))->response()->setStatusCode(200);
    }

    public function destroy(DeleteUserRequest $request, UserService $users, AuthService $auth): Response
    {
        $users->delete($request->user(), $request->validated('current_password'));
        $auth->logout($request->hasSession() ? $request->session() : null);

        return response()->noContent();
    }
}

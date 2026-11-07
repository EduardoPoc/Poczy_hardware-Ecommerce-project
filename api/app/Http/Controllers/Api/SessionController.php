<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSessionRequest;
use App\Http\Resources\CartMergeResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\CartMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class SessionController extends Controller
{
    public function store(StoreSessionRequest $request, AuthService $auth, CartMergeService $carts): JsonResponse
    {
        $carts->validateGuest($request);
        $guard = Auth::guard('web');
        $previousUser = $guard->user();
        $session = $request->hasSession() ? $request->session() : null;
        $previousSession = $session?->all() ?? [];
        $login = function () use ($request, $auth, $carts, $session): array {
            $user = $auth->login($request->validated(), $session);
            $merge = $carts->merge($user, $request);
            $payload = (new UserResource($user))->resolve($request);
            if ($merge !== null) {
                $payload['cart_merge'] = new CartMergeResource($merge);
            }
            if ($request->route('token_auth')) {
                $payload['access_token'] = $auth->issueToken($user);
                $payload['token_type'] = 'Bearer';
                $payload['expires_in'] = 28800;
            }

            return $payload;
        };
        try {
            $payload = $request->route('token_auth') ? DB::transaction($login, 3) : $login();
        } catch (Throwable $exception) {
            $session?->flush();
            $session?->put($previousSession);
            $guard->forgetUser();
            if ($previousUser !== null) {
                $guard->setUser($previousUser);
            }

            throw $exception;
        }

        return response()->json($payload);
    }

    public function destroy(Request $request, AuthService $auth): Response
    {
        $auth->logout($request->hasSession() ? $request->session() : null);

        return response()->noContent();
    }
}

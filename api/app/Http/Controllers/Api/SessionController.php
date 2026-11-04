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
use Throwable;

class SessionController extends Controller
{
    public function store(StoreSessionRequest $request, AuthService $auth, CartMergeService $carts): JsonResponse
    {
        $carts->validateGuest($request);
        $guard = Auth::guard('web');
        $previousUser = $guard->user();
        $previousSession = $request->session()->all();
        try {
            $user = $auth->login($request->validated(), $request->session());
            $merge = $carts->merge($user, $request);
        } catch (Throwable $exception) {
            $request->session()->flush();
            $request->session()->put($previousSession);
            $guard->forgetUser();
            if ($previousUser !== null) {
                $guard->setUser($previousUser);
            }

            throw $exception;
        }
        $payload = (new UserResource($user))->resolve($request);
        if ($merge !== null) {
            $payload['cart_merge'] = new CartMergeResource($merge);
        }

        return response()->json($payload);
    }

    public function destroy(Request $request, AuthService $auth): Response
    {
        $auth->logout($request->session());

        return response()->noContent();
    }
}

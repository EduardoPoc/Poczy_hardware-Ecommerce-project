<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json($user);
    }

    public function store(Request $request, UserService $users): JsonResponse
    {
        $user = $users->register($request->all());

        return response()->json($user, 201);
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SessionController extends Controller
{
    public function store(Request $request, AuthService $auth): JsonResponse
    {
        $user = $auth->login($request->all(), $request->session());

        return response()->json($user);
    }

    public function destroy(Request $request, AuthService $auth): Response
    {
        $auth->logout($request->session());

        return response()->noContent();
    }
}

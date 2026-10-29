<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SessionController extends Controller
{
    public function __construct(private AuthService $auth) {}

    public function store(LoginRequest $request): UserResource
    {
        return new UserResource($this->auth->login($request->validated(), $request->session()));
    }

    public function destroy(Request $request): Response
    {
        $this->auth->logout($request->session());

        return response()->noContent();
    }
}

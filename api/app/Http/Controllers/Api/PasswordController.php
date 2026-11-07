<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Services\AuthService;
use App\Services\UserService;
use Illuminate\Http\Response;

class PasswordController extends Controller
{
    public function update(UpdatePasswordRequest $request, UserService $users, AuthService $auth): Response
    {
        $users->changePassword($request->user(), $request->validated());
        $auth->logout($request->hasSession() ? $request->session() : null);

        return response()->noContent();
    }
}

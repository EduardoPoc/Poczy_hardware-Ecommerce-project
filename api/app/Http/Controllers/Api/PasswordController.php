<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Services\AuthService;
use App\Services\UserService;
use Illuminate\Http\Response;

class PasswordController extends Controller
{
    public function update(ChangePasswordRequest $request, UserService $users, AuthService $auth): Response
    {
        $users->changePassword($request->user(), $request->validated());
        $auth->logout($request->session());

        return response()->noContent();
    }
}

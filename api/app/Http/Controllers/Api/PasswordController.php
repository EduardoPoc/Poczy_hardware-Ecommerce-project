<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PasswordController extends Controller
{
    public function update(Request $request, UserService $users, AuthService $auth): Response
    {
        $users->changePassword($request->user(), $request->all());
        $auth->logout($request->session());

        return response()->noContent();
    }
}

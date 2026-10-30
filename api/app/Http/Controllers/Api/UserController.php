<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeleteUserRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function __construct(private UserService $service) {}

    public function show(Request $request): UserResource
    {
        return new UserResource($this->service->getProfile($request->user()));
    }

    public function store(RegisterUserRequest $request): UserResource
    {
        return new UserResource($this->service->register($request->validated()));
    }

    public function update(UpdateUserRequest $request): UserResource
    {
        return new UserResource($this->service->update($request->user(), $request->validated()));
    }

    public function destroy(DeleteUserRequest $request, AuthService $auth): Response
    {
        $this->service->delete($request->user(), $request->validated('current_password'));
        $auth->logout($request->session());

        return response()->noContent();
    }
}

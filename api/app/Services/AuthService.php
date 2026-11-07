<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /** @param array{email: string, password: string} $credentials */
    public function login(array $credentials, ?Session $session = null): User
    {
        $guard = Auth::guard('web');
        $credentials = array_intersect_key($credentials, array_flip(['email', 'password']));
        $authenticated = $session === null ? $guard->once($credentials) : $guard->attempt($credentials);
        if (! $authenticated) {
            throw ValidationException::withMessages(['email' => 'The provided credentials are invalid.']);
        }
        $session?->regenerate();

        return Auth::guard('web')->user();
    }

    public function issueToken(User $user): string
    {
        return $user->createToken('api', ['*'], now()->addHours(8))->plainTextToken;
    }

    public function logout(?Session $session = null): void
    {
        $user = Auth::guard('sanctum')->user();
        $user?->tokens()->delete();
        if ($session !== null) {
            Auth::guard('web')->logout();
            $session->invalidate();
            $session->regenerateToken();
        }
        Auth::guard('sanctum')->forgetUser();
    }
}

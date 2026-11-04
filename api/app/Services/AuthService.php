<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /** @param array{email: string, password: string} $credentials */
    public function login(array $credentials, Session $session): User
    {
        if (! Auth::guard('web')->attempt(array_intersect_key($credentials, array_flip(['email', 'password'])))) {
            throw ValidationException::withMessages(['email' => 'As credenciais informadas são inválidas.']);
        }
        $session->regenerate();

        return Auth::guard('web')->user();
    }

    public function logout(Session $session): void
    {
        Auth::guard('web')->logout();
        $session->invalidate();
        $session->regenerateToken();
    }
}

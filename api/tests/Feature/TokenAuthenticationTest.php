<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TokenAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'https://external-client.example');
        $this->customer = User::create(['name' => 'Token client', 'email' => 'token@example.com', 'password' => 'SenhaCliente!2026'])->refresh();
    }

    private function loginToken(): string
    {
        $token = $this->postJson('/api/auth/login', ['email' => 'TOKEN@example.com', 'password' => 'SenhaCliente!2026'])
            ->assertOk()->assertJsonPath('token_type', 'Bearer')->assertJsonPath('expires_in', 28800)
            ->assertJsonMissingPath('password')->json('access_token');
        Auth::forgetGuards();

        return $token;
    }

    public function test_token_authenticates_without_session_cookies_and_is_stored_as_a_hash(): void
    {
        $token = $this->loginToken();
        $this->assertNotSame($token, $this->customer->tokens()->sole()->token);
        $this->withToken($token)->getJson('/api/user')->assertOk()->assertJsonPath('data.id', $this->customer->id);
        $this->getJson('/api/admin/products')->assertForbidden();
    }

    public function test_logout_revokes_tokens_and_expired_or_unknown_tokens_cannot_authenticate(): void
    {
        $token = $this->loginToken();
        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        Auth::forgetGuards();
        $this->getJson('/api/user')->assertUnauthorized();
        Auth::forgetGuards();
        $expired = $this->customer->createToken('expired', ['*'], now()->subMinute())->plainTextToken;
        $this->withToken($expired)->getJson('/api/user')->assertUnauthorized();
        Auth::forgetGuards();
        $this->withToken('999999|unknown')->getJson('/api/user')->assertUnauthorized();
    }

    public function test_password_change_revokes_all_tokens_and_still_requires_the_current_password(): void
    {
        $token = $this->loginToken();
        $this->withToken($token)->putJson('/api/user/password', [
            'current_password' => 'incorrect', 'password' => 'NovaSenha!2026', 'password_confirmation' => 'NovaSenha!2026',
        ])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->putJson('/api/user/password', [
            'current_password' => 'SenhaCliente!2026', 'password' => 'NovaSenha!2026', 'password_confirmation' => 'NovaSenha!2026',
        ])->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertTrue(Hash::check('NovaSenha!2026', $this->customer->fresh()->password));
    }

    public function test_stateless_registration_cannot_choose_an_administrative_role(): void
    {
        $data = ['name' => 'New client', 'email' => 'new-token@example.com', 'password' => 'SenhaCliente!2026', 'password_confirmation' => 'SenhaCliente!2026'];
        $this->postJson('/api/auth/register', $data + ['role' => 'ADMIN'])->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->postJson('/api/auth/register', $data)->assertCreated()->assertJsonPath('role', 'CUSTOMER');
    }
}

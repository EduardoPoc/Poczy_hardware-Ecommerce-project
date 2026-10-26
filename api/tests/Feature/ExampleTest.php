<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_health_returns_json_without_a_database_connection(): void
    {
        $this->get('/api/v1/health')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
                'name' => config('app.name'),
            ]);
    }

    public function test_user_requires_authentication_even_without_an_accept_header(): void
    {
        $this->get('/api/v1/user')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_authenticated_user_is_returned_without_sensitive_fields(): void
    {
        $user = User::factory()->make();
        Sanctum::actingAs($user);

        $this->get('/api/v1/user')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.nome', $user->nome)
            ->assertJsonMissingPath('data.senha_hash')
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');
    }

    public function test_unknown_routes_return_json(): void
    {
        $this->get('/api/v1/missing')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_allowed_frontend_origin_can_preflight_authenticated_requests(): void
    {
        config(['cors.allowed_origins' => ['http://localhost:5173']]);

        $this->options('/api/v1/user', [], [
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'Authorization',
        ])
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function test_unknown_origins_do_not_receive_cors_permission(): void
    {
        config(['cors.allowed_origins' => ['http://localhost:5173', 'http://127.0.0.1:5173']]);

        $this->get('/api/v1/health', ['Origin' => 'https://example.com'])
            ->assertOk()
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_health_works_without_querying_the_database(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_administrative_routes_require_session_authentication(): void
    {
        $this->getJson('/api/admin/products')->assertUnauthorized();
    }

    public function test_profile_requires_session_authentication(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_api_validation_messages_are_english_even_when_portuguese_is_requested(): void
    {
        $this->withHeader('Accept-Language', 'pt-BR')->postJson('/api/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'The email field is required.')
            ->assertJsonPath('errors.password.0', 'The password field is required.');

        $password = str_repeat('é', 37).'Aa1!';
        $response = $this->postJson('/api/user', [
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertUnprocessable();
        $this->assertContains(
            'The password must not exceed 72 bytes for the configured hashing algorithm.',
            $response->json('errors.password'),
        );
    }

    public function test_customers_cannot_access_administrative_routes(): void
    {
        $user = new User(['name' => 'Cliente', 'email' => 'cliente@example.com']);
        $user->role = 'CUSTOMER';
        $this->actingAs($user, 'web');
        $this->postJson('/api/admin/brands', ['name' => 'Marca'])->assertForbidden();
        $this->getJson('/api/admin/products')->assertForbidden();
    }

    public function test_public_resources_do_not_accept_writes(): void
    {
        $this->postJson('/api/products', [])->assertStatus(405);
        $this->postJson('/api/brands', [])->assertStatus(405);
    }

    public function test_unregistered_routes_return_json(): void
    {
        foreach (['/api/missing', '/api/order', '/api/categoria', '/api/v1/products'] as $path) {
            $this->getJson($path)->assertNotFound()->assertHeader('Content-Type', 'application/json');
        }
    }

    public function test_allowed_frontend_can_preflight_session_requests(): void
    {
        config(['cors.allowed_origins' => ['http://localhost:5173']]);
        $this->options('/api/admin/products', [], [
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    public function test_unknown_origins_do_not_receive_cors_permission(): void
    {
        config(['cors.allowed_origins' => ['http://localhost:5173', 'http://127.0.0.1:5173']]);
        $this->getJson('/api/missing', ['Origin' => 'https://example.com'])
            ->assertNotFound()->assertHeaderMissing('Access-Control-Allow-Origin');
    }
}

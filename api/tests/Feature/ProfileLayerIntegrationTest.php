<?php

namespace Tests\Feature;

use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use App\Services\AuthService;
use App\Services\UserService;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Validator;
use Mockery;
use Tests\TestCase;

class ProfileLayerIntegrationTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = new User(['name' => 'Cliente', 'email' => 'client@example.com']);
        $this->user->id = 1;
        $this->user->role = 'CUSTOMER';
        $this->user->setRawAttributes(array_merge($this->user->getAttributes(), [
            'password' => 'private-hash', 'remember_token' => 'private-token', 'internal_note' => 'private-note',
        ]));
        $this->actingAs($this->user, 'web');
    }

    public function test_profile_delegates_to_the_service_and_uses_the_resource_with_http_200(): void
    {
        $this->user->wasRecentlyCreated = true;
        $this->mock(UserService::class)->shouldReceive('getProfile')->once()->with($this->user)->andReturn($this->user);
        $this->getJson('/api/user')->assertOk()->assertJsonPath('data.id', 1)
            ->assertJsonPath('data.email', 'client@example.com')->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token')->assertJsonMissingPath('data.internal_note');
    }

    public function test_profile_updates_forward_only_validated_fields_and_serialize_the_result(): void
    {
        $updated = clone $this->user;
        $updated->name = 'Nome atualizado';
        $updated->wasRecentlyCreated = true;
        $this->mock(UserService::class)->shouldReceive('update')->once()
            ->with($this->user, ['name' => 'Nome atualizado'])->andReturn($updated);
        $this->patchJson('/api/user', ['name' => 'Nome atualizado', 'id' => 999, 'created_at' => '2000-01-01'])
            ->assertOk()->assertJsonPath('data.id', 1)->assertJsonPath('data.name', 'Nome atualizado')
            ->assertJsonMissingPath('data.internal_note');
    }

    public function test_malformed_profile_updates_and_role_changes_are_rejected_before_the_service(): void
    {
        $this->mock(UserService::class)->shouldNotReceive('update');
        foreach ([
            ['name' => []], ['name' => null], ['name' => str_repeat('a', 101)], ['role' => 'ADMIN'],
            ['email' => []], ['email' => 'invalid-email'], ['current_password' => []],
        ] as $input) {
            $this->patchJson('/api/user', $input)->assertUnprocessable()->assertJsonValidationErrors(array_keys($input));
        }
    }

    public function test_deletion_validates_the_password_before_calling_services(): void
    {
        $this->mock(UserService::class)->shouldNotReceive('delete');
        $this->mock(AuthService::class)->shouldNotReceive('logout');
        foreach ([[], ['current_password' => []], ['current_password' => null]] as $input) {
            $this->deleteJson('/api/user', $input)->assertUnprocessable()->assertJsonValidationErrors('current_password');
        }
    }

    public function test_valid_deletion_calls_the_user_service_and_logs_out_with_no_content(): void
    {
        $this->mock(UserService::class)->shouldReceive('delete')->once()->with($this->user, 'current-password');
        $this->mock(AuthService::class)->shouldReceive('logout')->once()->with(Mockery::type(Session::class));
        $this->deleteJson('/api/user', ['current_password' => 'current-password', 'user_id' => 999])->assertNoContent();
    }

    public function test_invalid_password_changes_do_not_call_services_or_logout(): void
    {
        $this->mock(UserService::class)->shouldNotReceive('changePassword');
        $this->mock(AuthService::class)->shouldNotReceive('logout');
        $this->putJson('/api/user/password', [])->assertUnprocessable()->assertJsonValidationErrors(['current_password', 'password']);
        foreach ([
            ['password' => []], ['password' => 'weak'], ['password_confirmation' => 'different'], ['current_password' => []],
        ] as $invalid) {
            $input = array_replace([
                'current_password' => 'current-password', 'password' => 'NovaSenha!2026', 'password_confirmation' => 'NovaSenha!2026',
            ], $invalid);
            $this->putJson('/api/user/password', $input)->assertUnprocessable()->assertJsonStructure(['message', 'errors']);
        }
    }

    public function test_valid_password_change_forwards_only_validated_fields_and_logs_out(): void
    {
        $this->mock(UserService::class)->shouldReceive('changePassword')->once()->with($this->user, [
            'current_password' => 'current-password', 'password' => 'NovaSenha!2026',
        ]);
        $this->mock(AuthService::class)->shouldReceive('logout')->once()->with(Mockery::type(Session::class));
        $this->putJson('/api/user/password', [
            'current_password' => 'current-password', 'password' => 'NovaSenha!2026',
            'password_confirmation' => 'NovaSenha!2026', 'id' => 999,
        ])->assertNoContent();
    }

    public function test_unsupported_bcrypt_passwords_are_rejected_before_hashing(): void
    {
        $this->mock(UserService::class)->shouldNotReceive('changePassword');
        $this->mock(AuthService::class)->shouldNotReceive('logout');
        foreach (["SenhaSegura\0!2026", 'Aa1!'.str_repeat('é', 35)] as $password) {
            $this->putJson('/api/user/password', [
                'current_password' => 'current-password', 'password' => $password, 'password_confirmation' => $password,
            ])->assertUnprocessable()->assertJsonValidationErrors('password');
        }
    }

    public function test_registration_password_rules_reject_unsupported_bytes_and_accept_the_bcrypt_boundary(): void
    {
        $request = new StoreUserRequest;
        $rules = ['password' => $request->rules()['password']];
        foreach (["SenhaSegura\0!2026", 'Aa1!'.str_repeat('é', 35)] as $password) {
            $validator = Validator::make(['password' => $password, 'password_confirmation' => $password], $rules);
            $this->assertTrue($validator->fails());
            $this->assertTrue($validator->errors()->has('password'));
        }
        $password = 'Aa1!'.str_repeat('é', 34);
        $this->assertSame(72, strlen($password));
        $this->assertTrue(Validator::make(['password' => $password, 'password_confirmation' => $password], $rules)->passes());
    }
}

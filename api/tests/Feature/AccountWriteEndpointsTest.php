<?php

namespace Tests\Feature;

use App\Models\PasswordChangeHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountWriteEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function customer(string $email = 'customer@example.com'): User
    {
        return User::create(['name' => 'Cliente', 'email' => $email, 'password' => 'SenhaAtual!2026'])->refresh();
    }

    public function test_registration_hashes_the_password_returns_defaults_and_prevents_privilege_escalation(): void
    {
        $data = ['name' => 'Novo Cliente', 'email' => 'novo@example.com', 'password' => 'SenhaSegura!2026', 'password_confirmation' => 'SenhaSegura!2026'];
        $this->postJson('/api/user', $data + ['role' => 'ADMIN'])->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->postJson('/api/user', $data)->assertCreated()->assertJsonPath('data.role', 'CUSTOMER')->assertJsonMissingPath('data.password');
        $user = User::where('email', $data['email'])->sole();
        $this->assertTrue(Hash::check($data['password'], $user->password));
        $this->postJson('/api/user', $data)->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_session_login_and_logout_and_invalid_credentials(): void
    {
        $user = $this->customer();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'SenhaAtual!2026'])->assertOk()->assertJsonPath('data.id', $user->id);
        $this->assertAuthenticatedAs($user, 'web');
        $this->getJson('/api/user')->assertOk();
        $this->postJson('/api/logout')->assertNoContent();
        $this->assertGuest('web');
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', ['email' => 'rate@example.com', 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/api/login', ['email' => 'rate@example.com', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_malformed_credentials_return_validation_errors(): void
    {
        $this->postJson('/api/login', ['email' => ['invalid'], 'password' => ['invalid']])
            ->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_session_writes_require_csrf_outside_the_test_bypass(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        $this->postJson('/api/login', ['email' => 'csrf@example.com', 'password' => 'wrong'])->assertStatus(419);
        $this->get('/sanctum/csrf-cookie')->assertNoContent()->assertCookie('XSRF-TOKEN');
    }

    public function test_profile_update_requires_password_for_email_and_never_changes_role(): void
    {
        $user = $this->customer();
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($user, 'web');
        $this->patchJson('/api/user', ['name' => 'Nome atualizado'])->assertOk()->assertJsonPath('data.name', 'Nome atualizado');
        $this->patchJson('/api/user', ['role' => 'ADMIN'])->assertUnprocessable();
        $this->patchJson('/api/user', ['email' => 'new@example.com'])->assertUnprocessable();
        $this->patchJson('/api/user', ['email' => 'new@example.com', 'current_password' => 'SenhaAtual!2026'])->assertOk();
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame('CUSTOMER', $user->fresh()->role);
    }

    public function test_password_change_records_history_and_logs_the_session_out(): void
    {
        $user = $this->customer();
        $this->actingAs($user, 'web');
        $this->putJson('/api/user/password', ['current_password' => 'wrong', 'password' => 'NovaSenha!2026', 'password_confirmation' => 'NovaSenha!2026'])->assertUnprocessable();
        $this->assertDatabaseCount('password_change_history', 0);
        $this->putJson('/api/user/password', ['current_password' => 'SenhaAtual!2026', 'password' => 'NovaSenha!2026', 'password_confirmation' => 'NovaSenha!2026'])->assertNoContent();
        $this->assertTrue(Hash::check('NovaSenha!2026', $user->fresh()->password));
        $this->assertDatabaseHas('password_change_history', ['id_user' => $user->id]);
        $this->assertGuest('web');
    }

    public function test_password_and_audit_are_rolled_back_together_on_failure(): void
    {
        $user = $this->customer();
        $hash = $user->password;
        $this->actingAs($user, 'web');
        PasswordChangeHistory::creating(function (): void {
            throw new \RuntimeException('Audit storage unavailable');
        });
        try {
            $this->putJson('/api/user/password', ['current_password' => 'SenhaAtual!2026', 'password' => 'NovaSenha!2026', 'password_confirmation' => 'NovaSenha!2026'])->assertStatus(500);
        } finally {
            PasswordChangeHistory::flushEventListeners();
        }
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertDatabaseCount('password_change_history', 0);
    }

    public function test_address_crud_is_scoped_to_the_session_and_validates_partial_updates(): void
    {
        $user = $this->customer();
        $other = $this->customer('other@example.com');
        $data = ['postal_code' => '01001-000', 'street' => 'Rua Original', 'number' => '10', 'city' => 'São Paulo', 'state' => 'SP'];
        $foreign = $other->addresses()->create($data);
        $this->actingAs($user, 'web');
        $this->postJson('/api/address', $data + ['user_id' => $other->id])->assertUnprocessable();
        $response = $this->postJson('/api/address', $data)->assertCreated();
        $id = $response->json('data.id');
        $this->getJson('/api/address/'.$id)->assertOk();
        $this->patchJson('/api/address/'.$id, ['street' => 'Rua Nova', 'complement' => null])->assertOk()->assertJsonPath('data.street', 'Rua Nova');
        $this->patchJson('/api/address/'.$id, ['street' => null])->assertUnprocessable();
        $this->patchJson('/api/address/'.$id, ['state' => 'XX'])->assertUnprocessable();
        $this->getJson('/api/address/'.$foreign->id)->assertNotFound();
        $this->patchJson('/api/address/'.$foreign->id, ['city' => 'Outra'])->assertNotFound();
        $this->deleteJson('/api/address/'.$foreign->id)->assertNotFound();
        $this->deleteJson('/api/address/'.$id)->assertNoContent();
        $this->assertDatabaseMissing('addresses', ['id' => $id]);
    }

    public function test_account_deletion_preserves_required_audit_and_requires_password(): void
    {
        $user = $this->customer();
        $this->actingAs($user, 'web');
        $this->deleteJson('/api/user', ['current_password' => 'wrong'])->assertUnprocessable();
        $user->passwordChanges()->create();
        $this->deleteJson('/api/user', ['current_password' => 'SenhaAtual!2026'])->assertConflict();
        $clean = $this->customer('clean@example.com');
        $this->actingAs($clean, 'web')->deleteJson('/api/user', ['current_password' => 'SenhaAtual!2026'])->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $clean->id]);
        $this->assertGuest('web');
    }

    public function test_password_change_revokes_database_sessions_without_touching_other_accounts(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->customer();
        $other = $this->customer('other@example.com');
        foreach (['computer' => $user->id, 'phone' => $user->id, 'other' => $other->id] as $id => $userId) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'payload' => base64_encode('{}'), 'last_activity' => time()]);
        }
        $this->actingAs($user, 'web')->putJson('/api/user/password', [
            'current_password' => 'SenhaAtual!2026', 'password' => 'NovaSenha!2026', 'password_confirmation' => 'NovaSenha!2026',
        ])->assertNoContent();
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());
    }

    public function test_email_normalization_prevents_case_variant_registration(): void
    {
        $data = ['name' => 'Cliente', 'email' => 'UPPER@example.com', 'password' => 'SenhaSegura!2026', 'password_confirmation' => 'SenhaSegura!2026'];
        $this->postJson('/api/user', $data)->assertCreated()->assertJsonPath('data.email', 'upper@example.com');
        $data['email'] = 'upper@example.com';
        $this->postJson('/api/user', $data)->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/login', ['email' => 'UPPER@example.com', 'password' => $data['password']])->assertOk();
    }
}

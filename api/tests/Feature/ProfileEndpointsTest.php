<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create(['name' => 'Cliente', 'email' => 'profile@example.com', 'password' => 'SenhaAtual!2026'])->refresh();
        $this->actingAs($this->user, 'web');
    }

    public function test_profile_reads_and_partial_updates_use_resources_and_protect_account_fields(): void
    {
        $this->getJson('/api/user')->assertOk()->assertJsonPath('data.email', 'profile@example.com')
            ->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token');
        $this->patchJson('/api/user', ['name' => 'Nome novo', 'id' => 999])->assertOk()->assertJsonPath('data.name', 'Nome novo');
        $this->patchJson('/api/user', ['role' => 'ADMIN'])->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertDatabaseHas('users', ['id' => $this->user->id, 'name' => 'Nome novo', 'role' => 'CUSTOMER']);
    }

    public function test_email_updates_require_the_password_normalize_email_and_preserve_uniqueness(): void
    {
        $this->user->forceFill(['email_verified_at' => now()])->save();
        User::create(['name' => 'Outro', 'email' => 'other@example.com', 'password' => 'SenhaAtual!2026']);
        $this->patchJson('/api/user', ['email' => 'new@example.com'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->patchJson('/api/user', ['email' => 'new@example.com', 'current_password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->patchJson('/api/user', ['email' => 'other@example.com', 'current_password' => 'SenhaAtual!2026'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->patchJson('/api/user', ['email' => 'NEW@example.com', 'current_password' => 'SenhaAtual!2026'])
            ->assertOk()->assertJsonPath('data.email', 'new@example.com')->assertJsonPath('data.email_verified_at', null);
        $this->assertSame('new@example.com', $this->user->fresh()->email);
        $this->actingAs($this->user->fresh(), 'web')->patchJson('/api/user', ['email' => 'NEW@example.com'])
            ->assertOk()->assertJsonPath('data.email', 'new@example.com');
    }

    public function test_password_change_validates_confirms_audits_and_logs_out(): void
    {
        $input = ['current_password' => 'SenhaAtual!2026', 'password' => 'NovaSenha!2026', 'password_confirmation' => 'different'];
        $this->putJson('/api/user/password', $input)->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertDatabaseCount('password_change_histories', 0);
        $input['password_confirmation'] = $input['password'];
        $this->putJson('/api/user/password', $input)->assertNoContent();
        $this->assertTrue(Hash::check('NovaSenha!2026', $this->user->fresh()->password));
        $this->assertDatabaseHas('password_change_histories', ['id_user' => $this->user->id]);
        $this->assertGuest('web');
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_deletion_preserves_accounts_with_history_and_removes_clean_accounts(): void
    {
        $this->deleteJson('/api/user', [])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->deleteJson('/api/user', ['current_password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->user->passwordChanges()->create();
        $this->deleteJson('/api/user', ['current_password' => 'SenhaAtual!2026'])->assertConflict();
        $this->assertDatabaseHas('users', ['id' => $this->user->id]);
        $clean = User::create(['name' => 'Conta limpa', 'email' => 'clean@example.com', 'password' => 'SenhaAtual!2026']);
        $this->actingAs($clean, 'web')->deleteJson('/api/user', ['current_password' => 'SenhaAtual!2026'])->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $clean->id]);
        $this->assertGuest('web');
    }
}

<?php

namespace Tests\Feature;

use App\Models\PasswordChangeHistory;
use App\Models\User;
use App\Services\AddressService;
use App\Services\AuthService;
use App\Services\UserService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class AccountWriteEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function customer(string $email = 'customer@example.com'): User
    {
        return app(UserService::class)->register(['name' => 'Cliente', 'email' => $email, 'password' => 'SenhaAtual!2026']);
    }

    public function test_registration_service_hashes_password_and_preserves_customer_role(): void
    {
        $user = app(UserService::class)->register(['name' => 'Novo Cliente', 'email' => 'novo@example.com', 'password' => 'SenhaSegura!2026', 'role' => 'ADMIN']);
        $this->assertSame('CUSTOMER', $user->role);
        $this->assertTrue(Hash::check('SenhaSegura!2026', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
    }

    public function test_auth_service_ignores_extra_request_fields_and_manages_the_session(): void
    {
        $user = $this->customer();
        $auth = app(AuthService::class);
        $session = app('session.store');
        $authenticated = $auth->login(['email' => $user->email, 'password' => 'SenhaAtual!2026', '_token' => 'csrf'], $session);
        $this->assertSame($user->id, $authenticated->id);
        $this->assertAuthenticatedAs($user, 'web');
        $auth->logout($session);
        $this->assertGuest('web');
    }

    public function test_auth_service_rejects_invalid_credentials(): void
    {
        $this->customer();
        $this->expectException(ValidationException::class);
        app(AuthService::class)->login(['email' => 'customer@example.com', 'password' => 'wrong'], app('session.store'));
    }

    public function test_email_changes_require_current_password_and_clear_verification(): void
    {
        $user = $this->customer();
        $user->forceFill(['email_verified_at' => now()])->save();
        $users = app(UserService::class);
        $users->update($user, ['name' => 'Nome atualizado', 'role' => 'ADMIN']);
        $this->assertSame('CUSTOMER', $user->fresh()->role);
        try {
            $users->update($user, ['email' => 'novo@example.com']);
            $this->fail('A troca de e-mail exige senha atual.');
        } catch (ValidationException) {
            $this->assertSame('customer@example.com', $user->fresh()->email);
        }
        $updated = $users->update($user, ['email' => 'novo@example.com', 'current_password' => 'SenhaAtual!2026']);
        $this->assertNull($updated->email_verified_at);
    }

    public function test_password_change_and_audit_roll_back_together(): void
    {
        $user = $this->customer();
        $hash = $user->password;
        PasswordChangeHistory::creating(function (): void {
            throw new \RuntimeException('Audit unavailable');
        });
        try {
            app(UserService::class)->changePassword($user, ['current_password' => 'SenhaAtual!2026', 'password' => 'NovaSenha!2026']);
            $this->fail('A gravação do histórico deveria falhar.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        } finally {
            PasswordChangeHistory::flushEventListeners();
        }
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertDatabaseCount('password_change_histories', 0);
    }

    public function test_password_change_records_history_and_revokes_only_own_database_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = $this->customer();
        $other = $this->customer('other@example.com');
        foreach (['computer' => $user->id, 'phone' => $user->id, 'other' => $other->id] as $id => $userId) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'payload' => base64_encode('{}'), 'last_activity' => time()]);
        }
        app(UserService::class)->changePassword($user, ['current_password' => 'SenhaAtual!2026', 'password' => 'NovaSenha!2026']);
        $this->assertTrue(Hash::check('NovaSenha!2026', $user->fresh()->password));
        $this->assertDatabaseHas('password_change_histories', ['id_user' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'computer']);
        $this->assertDatabaseMissing('sessions', ['id' => 'phone']);
        $this->assertDatabaseHas('sessions', ['id' => 'other']);
    }

    public function test_address_service_crud_is_scoped_to_its_owner(): void
    {
        $user = $this->customer();
        $other = $this->customer('other@example.com');
        $addresses = app(AddressService::class);
        $data = ['postal_code' => '01001-000', 'street' => 'Rua Original', 'number' => '10', 'city' => 'São Paulo', 'state' => 'SP'];
        $address = $addresses->create($user, $data);
        $addresses->update($user, $address->id, ['street' => 'Rua Nova']);
        $this->assertSame('Rua Nova', $addresses->getUserAddress($user, $address->id)->street);
        try {
            $addresses->getUserAddress($other, $address->id);
            $this->fail('Outro usuário não deve consultar o endereço.');
        } catch (ModelNotFoundException) {
            $this->assertCount(0, $addresses->listUserAddresses($other));
        }
        $addresses->delete($user, $address->id);
        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    }

    public function test_accounts_with_audit_are_preserved_and_clean_accounts_can_be_deleted(): void
    {
        $user = $this->customer();
        $user->passwordChanges()->create();
        try {
            app(UserService::class)->delete($user, 'SenhaAtual!2026');
            $this->fail('A conta com histórico deveria ser preservada.');
        } catch (ConflictHttpException) {
            $this->assertDatabaseHas('users', ['id' => $user->id]);
        }
        $clean = $this->customer('clean@example.com');
        app(UserService::class)->delete($clean, 'SenhaAtual!2026');
        $this->assertDatabaseMissing('users', ['id' => $clean->id]);
    }
}

<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UserService
{
    public function getProfile(User $user): User
    {
        return $user;
    }

    /** @param array{name: string, email: string, password: string} $data */
    public function register(array $data): User
    {
        return User::create(collect($data)->only(['name', 'email', 'password'])->all())->refresh();
    }

    /** @param array{name?: string, email?: string, current_password?: string} $data */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $user = User::lockForUpdate()->findOrFail($user->id);
            if (isset($data['email']) && $data['email'] !== $user->email) {
                $this->checkPassword($user, $data['current_password'] ?? '');
                $user->email_verified_at = null;
            }
            $user->fill(collect($data)->only(['name', 'email'])->all())->save();

            return $user;
        }, 3);
    }

    /** @param array{current_password: string, password: string} $data */
    public function changePassword(User $user, array $data): void
    {
        DB::transaction(function () use ($user, $data): void {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $this->checkPassword($user, $data['current_password']);
            $user->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
            $user->passwordChanges()->create();
            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table'))
                    ->where('user_id', $user->id)->delete();
            }
        }, 3);
    }

    private function checkPassword(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'A senha atual está incorreta.']);
        }
    }

    public function delete(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password): void {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $this->checkPassword($user, $password);
            if ($user->orders()->exists() || $user->passwordChanges()->exists()) {
                throw new ConflictHttpException('A conta possui pedidos ou histórico de senha que devem ser preservados.');
            }
            $user->delete();
        }, 3);
    }
}

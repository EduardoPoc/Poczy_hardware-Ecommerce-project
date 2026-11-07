<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Rules\MaxBytes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdministrator extends Command
{
    protected $signature = 'app:create-admin {--name=} {--email=}';

    protected $description = 'Create an administrator with a password entered privately in the terminal';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Run this command interactively to enter and confirm the administrator password.');

            return self::FAILURE;
        }
        $data = [
            'name' => trim((string) ($this->option('name') ?: $this->ask('Nome'))),
            'email' => mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('E-mail')))),
            'password' => $this->secret('Senha (mínimo 12 caracteres, maiúsculas, minúsculas, número e símbolo)'),
            'password_confirmation' => $this->secret('Confirme a senha'),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', 'not_regex:/\x00/', new MaxBytes(72), Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }
        $user = new User(collect($validator->validated())->only(['name', 'email', 'password'])->all());
        $user->role = 'ADMIN';
        $user->save();
        $this->info('Administrator created successfully.');

        return self::SUCCESS;
    }
}

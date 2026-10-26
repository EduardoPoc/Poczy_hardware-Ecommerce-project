<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['nome', 'email', 'senha_hash'];

    protected $hidden = ['senha_hash', 'remember_token'];

    protected $table = 'usuarios';

    protected $authPasswordName = 'senha_hash';


    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'senha_hash' => 'hashed',
            'criado_em' => 'datetime',
        ];
    }
}

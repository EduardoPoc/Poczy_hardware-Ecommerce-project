<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordChangeHistory extends Model
{
    protected $table = 'password_change_history';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user');
    }
}

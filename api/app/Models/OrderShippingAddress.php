<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderShippingAddress extends Model
{
    protected $primaryKey = 'order_id';

    public $incrementing = false;

    protected $fillable = [
        'postal_code',
        'street',
        'number',
        'complement',
        'city',
        'state',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

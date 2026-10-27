<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Motherboard extends Model
{
    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $fillable = [
        'form_factor',
        'ddr',
        'socket',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PcCase extends Model
{
    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $fillable = [
        'motherboard_form_factor',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

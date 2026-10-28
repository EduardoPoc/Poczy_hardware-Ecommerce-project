<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notebook extends Model
{
    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $fillable = [
        'storage_capacity_gb',
        'has_dedicated_gpu',
        'ram_capacity_gb',
        'screen_size_inches',
        'screen_type',
    ];

    protected function casts(): array
    {
        return [
            'storage_capacity_gb' => 'integer',
            'has_dedicated_gpu' => 'boolean',
            'ram_capacity_gb' => 'integer',
            'screen_size_inches' => 'decimal:1',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

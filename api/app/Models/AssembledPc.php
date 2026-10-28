<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssembledPc extends Model
{
    use HasFactory;

    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $fillable = [
        'storage_capacity_gb',
        'has_dedicated_gpu',
        'ram_capacity_gb',
    ];

    protected function casts(): array
    {
        return [
            'storage_capacity_gb' => 'integer',
            'has_dedicated_gpu' => 'boolean',
            'ram_capacity_gb' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

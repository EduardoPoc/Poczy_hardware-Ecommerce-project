<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorageDevice extends Model
{
    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $fillable = [
        'type',
        'read_speed_mbps',
        'write_speed_mbps',
        'capacity_gb',
    ];

    protected function casts(): array
    {
        return [
            'read_speed_mbps' => 'integer',
            'write_speed_mbps' => 'integer',
            'capacity_gb' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

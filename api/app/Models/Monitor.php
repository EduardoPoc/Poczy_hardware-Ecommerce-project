<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Monitor extends Model
{
    use HasFactory;

    protected $primaryKey = 'product_id';

    public $incrementing = false;

    protected $fillable = [
        'screen_size_inches',
        'screen_type',
    ];

    protected function casts(): array
    {
        return [
            'screen_size_inches' => 'decimal:1',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

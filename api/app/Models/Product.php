<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'description',
        'price',
        'cost',
        'stock',
        'image_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function assembledPc(): HasOne
    {
        return $this->hasOne(AssembledPc::class, 'product_id');
    }

    public function monitor(): HasOne
    {
        return $this->hasOne(Monitor::class, 'product_id');
    }

    public function notebook(): HasOne
    {
        return $this->hasOne(Notebook::class, 'product_id');
    }

    public function peripheral(): HasOne
    {
        return $this->hasOne(Peripheral::class, 'product_id');
    }

    public function processor(): HasOne
    {
        return $this->hasOne(Processor::class, 'product_id');
    }

    public function ramModule(): HasOne
    {
        return $this->hasOne(RamModule::class, 'product_id');
    }

    public function graphicsCard(): HasOne
    {
        return $this->hasOne(GraphicsCard::class, 'product_id');
    }

    public function motherboard(): HasOne
    {
        return $this->hasOne(Motherboard::class, 'product_id');
    }

    public function storageDevice(): HasOne
    {
        return $this->hasOne(StorageDevice::class, 'product_id');
    }

    public function powerSupply(): HasOne
    {
        return $this->hasOne(PowerSupply::class, 'product_id');
    }

    public function pcCase(): HasOne
    {
        return $this->hasOne(PcCase::class, 'product_id');
    }

    public function coolingDevice(): HasOne
    {
        return $this->hasOne(CoolingDevice::class, 'product_id');
    }
}

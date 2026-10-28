<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'price', 'tax_percent', 'stock'];

    protected $casts = [
        'price'       => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'stock'       => 'integer',
    ];

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Usage: Product::lowStock(10)->get() */
    public function scopeLowStock(Builder $query, int $threshold): Builder
    {
        return $query->where('stock', '<', $threshold)->orderBy('stock');
    }
}

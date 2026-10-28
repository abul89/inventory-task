<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['order_number', 'customer_id', 'subtotal', 'tax_total', 'grand_total'];

    protected $casts = [
        'subtotal'    => 'decimal:2',
        'tax_total'   => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    /** An order belongs to one customer. */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** An order has one or more product lines. */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}

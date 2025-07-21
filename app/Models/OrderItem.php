<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_sku',
        'quantity',
        'unit_price',
        'total_price'
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2'
    ];

    /**
     * Buyurtma
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Mahsulot
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * API uchun ma'lumot qaytarish
     */
    public function toApiArray($locale = 'uz')
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_sku' => $this->product_sku,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_price' => $this->total_price,
            'product' => $this->product ? $this->product->toApiArray($locale) : null
        ];
    }
}

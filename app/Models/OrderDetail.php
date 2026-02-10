<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'store_id',
        'quantity',
        'price_at_time',
        'subtotal',
    ];

    protected $casts = [
        'price_at_time' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    // العلاقات
    
    /**
     * الطلب الذي ينتمي إليه التفصيل
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * المنتج في التفصيل
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * المحل الذي يبيع المنتج
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
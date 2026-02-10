<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
    ];

    // العلاقات
    
    /**
     * المستخدم الذي أضاف المنتج للسلة
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * المنتج في السلة
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * حساب السعر الإجمالي للعنصر
     */
    public function getTotalPriceAttribute()
    {
        return $this->product->discounted_price * $this->quantity;
    }
}
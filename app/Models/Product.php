<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'store_id',
        'category_id',
        'name',
        'description',
        'price',
        'icon',
        'color_code',
        'is_available',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
    ];

    protected $dates = ['deleted_at'];

    // العلاقات
    
    /**
     * المحل الذي يبيع المنتج
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * التصنيف الذي ينتمي إليه المنتج
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * عناصر السلة التي تحتوي على هذا المنتج
     */
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * تفاصيل الطلبات التي تحتوي على هذا المنتج
     */
    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }

    /**
     * المستخدمون الذين أضافوا المنتج للمفضلة
     */
    public function favoritedBy()
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * العروض والتخفيضات على المنتج
     */
    public function offers()
    {
        return $this->hasMany(Offer::class);
    }

    /**
     * الحصول على السعر بعد الخصم (إذا كان هناك عرض)
     */
    public function getDiscountedPriceAttribute()
    {
        $activeOffer = $this->offers()
            ->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();

        if ($activeOffer) {
            if ($activeOffer->discount_type === 'نسبة_مئوية') {
                return $this->price * (1 - ($activeOffer->discount_value / 100));
            } else {
                return max(0, $this->price - $activeOffer->discount_value);
            }
        }

        return $this->price;
    }
}
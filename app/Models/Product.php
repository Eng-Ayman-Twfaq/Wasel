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
        'name',
        'description',
        'price',
        // 'image_url',
        'is_available',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
    ];

    // ========== العلاقات ==========

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function favoritedBy()
    {
        return $this->hasMany(Favorite::class);
    }

    public function offers()
    {
        return $this->hasMany(Offer::class);
    }

    // ========== طرق المساعدة ==========

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
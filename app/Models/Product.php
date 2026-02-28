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
        'quantity',
        'low_stock_threshold',
        'unit_type',
        'pieces_per_unit',
        'allow_partial_unit',
        'min_order_quantity',
        'is_available',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'pieces_per_unit' => 'integer',
        'allow_partial_unit' => 'boolean',
        'is_available' => 'boolean',
        'min_order_quantity' => 'decimal:2',
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

    /**
     * حساب الكمية المتوفرة بالوحدات (مثلاً: عدد الكراتين المتوفرة)
     */
    public function getAvailableUnitsAttribute()
    {
        if ($this->pieces_per_unit == 0) return 0;
        return floor($this->quantity / $this->pieces_per_unit);
    }

    /**
     * التحقق من توفر كمية محددة
     */
    public function isQuantityAvailable($requestedQuantity)
    {
        $requestedPieces = $requestedQuantity * $this->pieces_per_unit;
        return $this->quantity >= $requestedPieces;
    }

    /**
     * خصم الكمية من المخزون
     */
    public function deductStock($quantity)
    {
        $piecesToDeduct = $quantity * $this->pieces_per_unit;
        $this->decrement('quantity', $piecesToDeduct);
        
        // تحديث حالة التوفر إذا نفذ المخزون
        if ($this->quantity <= 0) {
            $this->update(['is_available' => false]);
        }
    }

    /**
     * التحقق مما إذا كان المخزون منخفضًا
     */
    public function getIsLowStockAttribute()
    {
        return $this->quantity <= $this->low_stock_threshold;
    }

    /**
     * الحصول على السعر بعد الخصم
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

    /**
     * حساب سعر كمية محددة مع مراعاة الخصومات
     */
    public function calculateTotalPrice($quantity)
    {
        $unitPrice = $this->getDiscountedPriceAttribute();
        return $quantity * $unitPrice;
    }

    // ========== Scopes ==========

    /**
     * المنتجات المتوفرة فقط
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)
                     ->where('quantity', '>', 0);
    }

    /**
     * المنتجات منخفضة المخزون
     */
    public function scopeLowStock($query)
    {
        return $query->whereColumn('quantity', '<=', 'low_stock_threshold')
                     ->where('quantity', '>', 0);
    }

    /**
     * المنتجات النافدة
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('quantity', '<=', 0);
    }

    /**
     * المنتجات حسب نوع الوحدة
     */
    public function scopeOfUnitType($query, $unitType)
    {
        return $query->where('unit_type', $unitType);
    }

    /**
     * المنتجات التي تسمح ببيع أجزاء من الوحدة
     */
    public function scopeAllowPartial($query)
    {
        return $query->where('allow_partial_unit', true);
    }
}
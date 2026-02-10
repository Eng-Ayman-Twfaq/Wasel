<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'store_id',
        'customer_store_id',
        'total_amount',
        'invoice_status',
        'sent_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'sent_at' => 'datetime',
    ];

    // العلاقات
    
    /**
     * الطلب المرتبط بالفاتورة
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * المحل البائع (التاجر)
     */
    public function merchant()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * المحل المشتري (البقالة)
     */
    public function customer()
    {
        return $this->belongsTo(Store::class, 'customer_store_id');
    }

    /**
     * المعاملات المالية للفاتورة
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * الحصول على المبلغ المدفوع
     */
    public function getPaidAmountAttribute()
    {
        return $this->transactions()
            ->where('status', 'ناجحة')
            ->sum('amount');
    }

    /**
     * الحصول على المبلغ المتبقي
     */
    public function getRemainingAmountAttribute()
    {
        return $this->total_amount - $this->paid_amount;
    }

    /**
     * التحقق إذا كانت الفاتورة مدفوعة بالكامل
     */
    public function getIsPaidAttribute()
    {
        return $this->remaining_amount <= 0;
    }
}
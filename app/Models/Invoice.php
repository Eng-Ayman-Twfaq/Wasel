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
        'invoice_type',
        'total_amount',
        'invoice_status',
        'sent_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'sent_at' => 'datetime',
    ];

    // ========== العلاقات ==========

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function merchant()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function customer()
    {
        return $this->belongsTo(Store::class, 'customer_store_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // ========== طرق المساعدة ==========

    public function getPaidAmountAttribute()
    {
        return $this->transactions()
            ->where('status', 'ناجحة')
            ->sum('amount');
    }

    public function getRemainingAmountAttribute()
    {
        return $this->total_amount - $this->paid_amount;
    }

    public function getIsPaidAttribute()
    {
        return $this->remaining_amount <= 0;
    }

    public static function createMasterInvoice(Order $order)
    {
        $totalProducts = $order->orderDetails()->sum('subtotal');
        $grandTotal = $totalProducts + $order->delivery_fee;

        return self::create([
            'order_id' => $order->id,
            'store_id' => null,
            'customer_store_id' => $order->store_id,
            'invoice_type' => 'master',
            'total_amount' => $grandTotal,
            'invoice_status' => 'بانتظار',
        ]);
    }
}
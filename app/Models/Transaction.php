<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'amount',
        'payment_method_id',
        'transaction_date',
        'reference',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    // العلاقات
    
    /**
     * الفاتورة المرتبطة بالمعاملة
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * طريقة الدفع المستخدمة
     */
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
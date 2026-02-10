<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'reviewer_id',
        'reviewee_id',
        'rating',
        'comment',
    ];

    // العلاقات
    
    /**
     * الطلب المرتبط بالتقييم
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * المستخدم الذي قدم التقييم
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * المستخدم الذي تلقى التقييم
     */
    public function reviewee()
    {
        return $this->belongsTo(User::class, 'reviewee_id');
    }
}
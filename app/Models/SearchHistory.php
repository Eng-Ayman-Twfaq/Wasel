<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SearchHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'query',
        'results_count',
    ];

    // العلاقات
    
    /**
     * المستخدم الذي أجرى البحث
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
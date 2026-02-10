<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'role',
        'owner_type',
        'identification_number',
        'area_id',
        'device_id',
        'registration_status',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $dates = ['deleted_at'];

    // العلاقات
    
    /**
     * المنطقة التابع لها المستخدم
     */
    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * المحلات التي يمتلكها المستخدم
     */
    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    /**
     * الوثائق المرفوعة من قبل المستخدم
     */
    public function uploadedDocuments()
    {
        return $this->hasMany(UserUploadedDocument::class);
    }

    /**
     * إذا كان المستخدم ضمن فريق الدعم
     */
    public function supportTeam()
    {
        return $this->hasOne(SupportTeam::class);
    }

    /**
     * عناصر السلة الخاصة بالمستخدم
     */
    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * الطلبات التي أنشأها المستخدم (إذا كان صاحب محل بقالة)
     */
    public function orders()
    {
        return $this->hasManyThrough(Order::class, Store::class);
    }

    /**
     * التقييمات التي قدمها المستخدم
     */
    public function reviewsGiven()
    {
        return $this->hasMany(Review::class, 'reviewer_id');
    }

    /**
     * التقييمات التي تلقاها المستخدم
     */
    public function reviewsReceived()
    {
        return $this->hasMany(Review::class, 'reviewee_id');
    }

    /**
     * المنتجات المفضلة للمستخدم
     */
    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    /**
     * سجل البحث للمستخدم
     */
    public function searchHistories()
    {
        return $this->hasMany(SearchHistory::class);
    }

    /**
     * الإشعارات الخاصة بالمستخدم
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * عمليات التوصيل المكلف بها المستخدم (إذا كان مندوب توصيل)
     */
    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'assigned_to');
    }

    /**
     * تحديد إذا كان المستخدم مديراً
     */
    public function isAdmin()
    {
        return $this->role === 'مدير';
    }

    /**
     * تحديد إذا كان المستخدم ضمن فريق الدعم
     */
    public function isSupport()
    {
        return $this->role === 'دعم';
    }

    /**
     * تحديد إذا كان المستخدم مالك محل
     */
    public function isStoreOwner()
    {
        return $this->role === 'مالك_محل';
    }

    /**
     * تحديد إذا كان الحساب موافقاً عليه
     */
    public function isApproved()
    {
        return $this->registration_status === 'موافق';
    }

    // في نهاية نموذج User، أضف هذه الطريقة:

/**
 * الحصول على حالة وثائق المستخدم
 */
public function getDocumentsStatusAttribute()
{
    $requiredDocs = RequiredDocument::getRequiredForRole($this->role);
    $uploadedDocs = $this->uploadedDocuments;
    
    $status = [
        'total_required' => $requiredDocs->count(),
        'uploaded' => 0,
        'approved' => 0,
        'pending' => 0,
        'rejected' => 0,
        'is_complete' => false,
        'missing_docs' => [],
    ];
    
    // حساب الوثائق المرفوعة
    foreach ($requiredDocs as $requiredDoc) {
        $uploadedDoc = $uploadedDocs->where('document_type', $requiredDoc->document_type)->first();
        
        if ($uploadedDoc) {
            $status['uploaded']++;
            
            if ($uploadedDoc->is_approved) {
                $status['approved']++;
            } elseif ($uploadedDoc->is_pending) {
                $status['pending']++;
            } elseif ($uploadedDoc->is_rejected) {
                $status['rejected']++;
            }
        } else {
            $status['missing_docs'][] = $requiredDoc->document_name;
        }
    }
    
    $status['is_complete'] = $status['approved'] === $status['total_required'];
    
    return $status;
}
}
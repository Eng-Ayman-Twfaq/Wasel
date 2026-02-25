<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PhoneVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone',
        'code',
        'attempts',
        'max_attempts',
        'status',
        'ip_address',
        'device_info',
        'expires_at',
        'verified_at',
    ];

    protected $casts = [
        'device_info' => 'array',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
    ];

    /**
     * التحقق مما إذا كان الرمز منتهي الصلاحية
     */
    public function isExpired(): bool
    {
        return Carbon::now()->gt($this->expires_at);
    }

    /**
     * التحقق مما إذا كان يمكن إعادة المحاولة
     */
    public function canAttempt(): bool
    {
        return $this->attempts < $this->max_attempts && 
               $this->status === 'pending' && 
               !$this->isExpired();
    }

    /**
     * زيادة عدد المحاولات
     */
    public function incrementAttempts(): void
    {
        $this->increment('attempts');
        
        if ($this->attempts >= $this->max_attempts) {
            $this->status = 'blocked';
            $this->save();
        }
    }

    /**
     * تأكيد التحقق
     */
    public function markAsVerified(): void
    {
        $this->status = 'verified';
        $this->verified_at = Carbon::now();
        $this->save();
    }

    /**
     * إنشاء رمز تحقق جديد
     */
    public static function generateCode(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * إنشاء طلب تحقق جديد
     */
    public static function createVerification(string $phone, string $ipAddress = null, array $deviceInfo = null): self
    {
        // حساب عدد محاولات إعادة الإرسال (كل المحاولات السابقة)
        $resendCount = self::where('phone', $phone)->count();
        
        // إذا وصل إلى 3 محاولات سابقة، نمنع إنشاء رمز جديد
        if ($resendCount >= 3) {
            throw new \Exception('لقد استنفذت عدد محاولات إعادة الإرسال (3 مرات). يرجى التواصل مع فريق الدعم');
        }

        // إلغاء أي محاولات سابقة معلقة
        self::where('phone', $phone)
            ->where('status', 'pending')
            ->update(['status' => 'expired']);
        
        return self::create([
            'phone' => $phone,
            'code' => self::generateCode(),
            'attempts' => 0,
            'max_attempts' => 3, // ✅ 3 محاولات فقط
            'status' => 'pending',
            'ip_address' => $ipAddress,
            'device_info' => $deviceInfo,
            'expires_at' => Carbon::now()->addMinutes(2), // ✅ دقيقتان صلاحية
        ]);
    }

    /**
     * التحقق من الرمز
     */
    public static function verifyCode(string $phone, string $code): array
    {
        $verification = self::where('phone', $phone)
            ->where('status', 'pending')
            ->latest()
            ->first();
        
        if (!$verification) {
            return [
                'success' => false,
                'message' => 'لم يتم طلب رمز تحقق لهذا الرقم'
            ];
        }

        // التحقق من صلاحية الكود
        if ($verification->isExpired()) {
            $verification->update(['status' => 'expired']);
            return [
                'success' => false,
                'message' => 'انتهت صلاحية رمز التحقق (دقيقتان). يرجى طلب رمز جديد'
            ];
        }

        // التحقق من صحة الكود
        if ($verification->code !== $code) {
            $verification->incrementAttempts();
            
            // إذا وصل إلى 3 محاولات خاطئة، نمنع المزيد
            if ($verification->attempts >= 3) {
                $verification->update(['status' => 'blocked']);
                
                return [
                    'success' => false,
                    'message' => 'لقد تجاوزت عدد المحاولات المسموحة (3 مرات). يرجى طلب رمز جديد',
                    'blocked' => true
                ];
            }

            $attemptsLeft = 3 - $verification->attempts;
            return [
                'success' => false,
                'message' => 'رمز التحقق غير صحيح',
                'attempts_remaining' => $attemptsLeft,
                'expires_in' => Carbon::now()->diffInSeconds($verification->expires_at)
            ];
        }

        // كود صحيح
        $verification->markAsVerified();
        
        return [
            'success' => true,
            'message' => 'تم التحقق بنجاح'
        ];
    }

    /**
     * نطاق للطلبات المنتهية
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', Carbon::now())
            ->where('status', 'pending');
    }

    /**
     * نطاق للطلبات المعلقة
     */
    public function scopePending($query, string $phone)
    {
        return $query->where('phone', $phone)
            ->where('status', 'pending')
            ->where('expires_at', '>', Carbon::now());
    }
}
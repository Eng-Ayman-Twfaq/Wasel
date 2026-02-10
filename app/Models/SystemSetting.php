<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
        'is_editable',
    ];

    protected $casts = [
        'is_editable' => 'boolean',
    ];

    /**
     * الحصول على قيمة الإعداد مع التحويل للنوع المناسب
     */
    public function getValueAttribute($value)
    {
        switch ($this->type) {
            case 'رقم':
                return (float) $value;
            case 'قيمة_منطقية':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'جسون':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    /**
     * حفظ قيمة الإعداد بناءً على نوعه
     */
    public function setValueAttribute($value)
    {
        switch ($this->type) {
            case 'جسون':
                $this->attributes['value'] = json_encode($value);
                break;
            default:
                $this->attributes['value'] = (string) $value;
                break;
        }
    }

    /**
     * طريقة مساعدة للحصول على إعداد بنوع معين
     */
    public static function getValue($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        
        if (!$setting) {
            return $default;
        }
        
        return $setting->value;
    }
}
<?php

namespace App\Traits;

use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

trait PasswordVerification
{
    public function verifyPassword(string $password): JsonResponse|bool
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'message' => 'غير مصرح'
            ], 401);
        }

        if (!Hash::check($password, $user->password)) {
            return response()->json([
                'message' => 'كلمة المرور غير صحيحة'
            ], 403);
        }

        return true;
    }

    // طريقة الاستخدام 
    // public function store(Request $request)
    // {
    //     $check = $this->verifyPassword($request->password);

    //     if ($check !== true) {
    //         return $check; // يرجع رسالة الخطأ تلقائياً
    //     }

    //     // إذا كلمة المرور صحيحة يكمل التنفيذ
    //     $product = Product::create([
    //         'name' => $request->name,
    //         'price' => $request->price,
    //         'user_id' => auth()->id(),
    //     ]);

    //     return response()->json([
    //         'message' => 'تم حفظ المنتج بنجاح',
    //         'product' => $product
    //     ]);
    // }
}
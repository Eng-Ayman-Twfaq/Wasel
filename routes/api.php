<?php

use App\Http\Controllers\Api\Auth\DeviceController;
use App\Http\Controllers\Api\Auth\RegistrationController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\GroceryOrderController;
use App\Http\Controllers\Api\Marketplace\MarketplaceController;
use App\Http\Controllers\Api\MerchantIdentityController;
use App\Http\Controllers\Api\MerchantOrderController;
use App\Http\Controllers\Api\MerchantProfileController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// Routes للتسجيل
Route::prefix('auth')->group(function () {
    // الحصول على قائمة المناطق
    Route::get('areas', [RegistrationController::class, 'getAreas']);
    
    // مراحل التسجيل
    Route::post('register/step1', [RegistrationController::class, 'registerStep1']);
    Route::post('register/verify-phone', [RegistrationController::class, 'verifyPhone']);
    Route::post('register/step2', [RegistrationController::class, 'registerStep2']);
    Route::post('register/step3', [RegistrationController::class, 'registerStep3']);
    Route::post('register/step4', [RegistrationController::class, 'registerStep4']);
    Route::post('register/resend-code', [RegistrationController::class, 'resendVerificationCode']);

     // تسجيل الجهاز بعد إنشاء الحساب
    Route::post('register-device/{userId}', [DeviceController::class, 'registerDevice']);
    
    // التحقق من الجهاز عند تسجيل الدخول
    Route::post('verify-device/{userId}', [DeviceController::class, 'verifyDevice']);
    
    // الأجهزة الخاصة بالمستخدم (محمية بـ auth)
    // Route::middleware('auth:sanctum')->group(function () {
        Route::get('user-devices/{userId}', [DeviceController::class, 'getUserDevices']);
        Route::post('approve-device/{deviceId}', [DeviceController::class, 'approveDevice']);
        Route::delete('revoke-device/{deviceId}', [DeviceController::class, 'revokeDevice']);
    // });


    Route::post('login', [AuthController::class, 'login']);
    Route::post('verify-device', [AuthController::class, 'verifyDevice']);
    Route::post('resend-code', [AuthController::class, 'resendVerificationCode']);
    
    // المسارات المحمية
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
    });


   
// تحكم التاجر
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('products/stats', [ProductController::class, 'stats']);
        Route::apiResource('products', ProductController::class);


         // stats قبل {id} لتجنب التعارض
        Route::get('orders/stats',           [MerchantOrderController::class, 'stats']);
        Route::get('orders',                 [MerchantOrderController::class, 'index']);
        Route::get('orders/{id}',            [MerchantOrderController::class, 'show']);
        Route::post('orders/{id}/approve',   [MerchantOrderController::class, 'approve']);
        Route::post('orders/{id}/reject',    [MerchantOrderController::class, 'reject']);

        // ── الملف الشخصي ──
        Route::get('profile',  [MerchantProfileController::class, 'show']);
        Route::put('profile',  [MerchantProfileController::class, 'update']);
        Route::put('profile/{password}',  [MerchantProfileController::class, 'changePassword']);
// تعديل بيانات الهوية
        Route::get('profile/identity',  [MerchantIdentityController::class, 'show']);
        Route::put('profile/identity',  [MerchantIdentityController::class, 'update']);

        // الاشعارات 

        Route::get('/notifications', [NotificationController::class, 'index']);

    Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    Route::delete('/notifications/delete-all', [NotificationController::class, 'destroyAll']);
    });


    Route::middleware('auth:sanctum')->prefix('grocery')->group(function () {

        Route::get('orders/stats',           [GroceryOrderController::class, 'stats']);
        Route::get('orders',                 [GroceryOrderController::class, 'index']);
        Route::get('orders/{id}',            [GroceryOrderController::class, 'show']);
        Route::post('orders',                [GroceryOrderController::class, 'store']);
        Route::post('orders/{id}/cancel',    [GroceryOrderController::class, 'cancel']);

    });
    Route::middleware('auth:sanctum')->prefix('market')->group(function () {
    Route::get('/products', [MarketplaceController::class, 'products']);
    Route::get('/products/nearby', [MarketplaceController::class, 'nearbyProducts']);
    Route::get('/traders', [MarketplaceController::class, 'traders']);
    Route::get('/traders/nearby', [MarketplaceController::class, 'nearbyTraders']);
});
});
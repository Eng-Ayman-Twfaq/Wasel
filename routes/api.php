<?php

use App\Http\Controllers\Api\Auth\RegistrationController;
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
});
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {

    // 🔴 غير مصرح (توكن خطأ أو غير موجود)
    $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
        return response()->json([
            'success' => false,
            'message' => 'غير مصرح لك بالوصول. يرجى تسجيل الدخول'
        ], 401);
    });

    // 🔴 ممنوع (صلاحيات)
    $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException $e) {
        return response()->json([
            'success' => false,
            'message' => 'ليس لديك صلاحية لتنفيذ هذا الإجراء'
        ], 403);
    });

    // 🔴 غير موجود
    $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
        return response()->json([
            'success' => false,
            'message' => 'المورد المطلوب غير موجود'
        ], 404);
    });

    // 🔴 أخطاء التحقق Validation
    $exceptions->render(function (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'خطأ في البيانات المدخلة',
            'errors' => $e->errors()
        ], 422);
    });

    // 🔴 أي خطأ غير متوقع (500)
    $exceptions->render(function (\Throwable $e) {

        return response()->json([
            'success' => false,
            'message' => 'حدث خطأ غير متوقع، يرجى المحاولة لاحقاً'
        ], 500);
    });

})
    ->create();
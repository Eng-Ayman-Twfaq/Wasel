<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class NotificationController extends Controller
{
    // =========================================================
    // GET /api/auth/notifications
    // جلب اشعارات المستخدم مع pagination
    // =========================================================
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['status' => false, 'message' => 'غير مصرح لك'], 403);
            }

            $perPage = min($request->get('per_page', 20), 50);

            $notifications = $user->notifications()
                ->latest()
                ->paginate($perPage);

            // عدد الغير مقروءة
            $unreadCount = $user->notifications()
                ->where('is_read', false)
                ->count();

            return response()->json([
                'status'       => true,
                'message'      => 'تم جلب الإشعارات بنجاح',
                'unread_count' => $unreadCount,
                'data'         => NotificationResource::collection($notifications),
                'pagination'   => [
                    'current_page' => $notifications->currentPage(),
                    'last_page'    => $notifications->lastPage(),
                    'per_page'     => $notifications->perPage(),
                    'total'        => $notifications->total(),
                    'has_more'     => $notifications->hasMorePages(),
                ],
            ]);

        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ في الخادم'], 500);
        }
    }

    // =========================================================
    // PUT /api/auth/notifications/{id}/read
    // تعليم اشعار واحد كمقروء
    // =========================================================
    public function markAsRead(int $id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['status' => false, 'message' => 'غير مصرح لك'], 403);
            }

            $notification = $user->notifications()->find($id);

            if (!$notification) {
                return response()->json(['status' => false, 'message' => 'الإشعار غير موجود'], 404);
            }

            $notification->markAsRead();

            return response()->json([
                'status'  => true,
                'message' => 'تم تعليم الإشعار كمقروء',
            ]);

        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ في الخادم'], 500);
        }
    }

    // =========================================================
    // PUT /api/auth/notifications/read-all
    // تعليم جميع الاشعارات كمقروءة
    // =========================================================
    public function markAllAsRead()
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['status' => false, 'message' => 'غير مصرح لك'], 403);
            }

            $count = $user->notifications()
                ->where('is_read', false)
                ->update(['is_read' => true]);

            return response()->json([
                'status'  => true,
                'message' => "تم تعليم {$count} إشعار كمقروء",
                'count'   => $count,
            ]);

        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ في الخادم'], 500);
        }
    }

    // =========================================================
    // DELETE /api/auth/notifications/{id}
    // حذف اشعار واحد
    // =========================================================
    public function destroy(int $id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['status' => false, 'message' => 'غير مصرح لك'], 403);
            }

            $notification = $user->notifications()->find($id);

            if (!$notification) {
                return response()->json(['status' => false, 'message' => 'الإشعار غير موجود'], 404);
            }

            $notification->delete();

            return response()->json([
                'status'  => true,
                'message' => 'تم حذف الإشعار بنجاح',
            ]);

        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ في الخادم'], 500);
        }
    }

    // =========================================================
    // DELETE /api/auth/notifications/delete-all
    // حذف جميع الاشعارات
    // =========================================================
    public function destroyAll()
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['status' => false, 'message' => 'غير مصرح لك'], 403);
            }

            $count = $user->notifications()->delete();

            return response()->json([
                'status'  => true,
                'message' => "تم حذف {$count} إشعار",
                'count'   => $count,
            ]);

        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ في الخادم'], 500);
        }
    }
}
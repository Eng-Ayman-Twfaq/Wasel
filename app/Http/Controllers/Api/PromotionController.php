<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PromotionResource;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PromotionController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    // GET /api/auth/grocery/promotions
    // جلب العروض النشطة — للبقالة فقط
    // ═══════════════════════════════════════════════════════════
    public function index()
    {
        try {
            $user = Auth::user();

            if (!$user || !$user->store || !$user->store->isGrocery()) {
                return response()->json(['status' => false, 'message' => 'غير مصرح لك'], 403);
            }

            $promotions = Promotion::active()
                ->orderBy('position')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'data'   => PromotionResource::collection($promotions),
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'حدث خطأ في الخادم'], 500);
        }
    }
}
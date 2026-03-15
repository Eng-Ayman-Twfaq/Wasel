<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\SearchResource;
use App\Http\Resources\TraderResource;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    private function ensureGrocery()
    {
        $store = Auth::user()?->store;
        if (!$store || !$store->isGrocery()) abort(403);
        return $store;
    }

    // ═══════════════════════════════════════════════════════════
    // GET /api/auth/grocery/marketplace/search
    //
    // Query params:
    //   q    = نص البحث (required, min:1)
    //   type = 'products' | 'traders' | 'all' (default: all)
    //   page = رقم الصفحة (default: 1)
    //
    // Response:
    //   {
    //     status: true,
    //     query: "...",
    //     type: "all",
    //     products: { data: [...], pagination: {...} },  // إذا type=products|all
    //     traders:  { data: [...], pagination: {...} },  // إذا type=traders|all
    //   }
    // ═══════════════════════════════════════════════════════════
    public function search(Request $request)
    {
        $request->validate([
            'q'    => ['required', 'string', 'min:1', 'max:100'],
            'type' => ['nullable', 'in:products,traders,all'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $grocery = $this->ensureGrocery();
        $query   = trim($request->q);
        $type    = $request->type ?? 'all';
        $page    = (int) ($request->page ?? 1);

        $response = [
            'status' => true,
            'query'  => $query,
            'type'   => $type,
        ];

        // ── بحث المنتجات ──────────────────────────────────────
        if ($type === 'products' || $type === 'all') {
            $productsQuery = Product::with('store')
                ->available()
                ->whereHas('store', fn($q) => $q
                    ->where('store_type', 'تاجر')
                    ->where('is_approved', true)
                    ->where('id', '!=', $grocery->id)
                )
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                      ->orWhere('description', 'like', "%{$query}%");
                })
                ->orderByRaw("
                    CASE
                        WHEN name LIKE ? THEN 1
                        WHEN name LIKE ? THEN 2
                        ELSE 3
                    END, name ASC
                ", ["{$query}%", "%{$query}%"])
                ->paginate(15, ['*'], 'page', $page);

            $response['products'] = [
                'data'       => ProductResource::collection($productsQuery)->resolve(),
                'pagination' => [
                    'current_page' => $productsQuery->currentPage(),
                    'last_page'    => $productsQuery->lastPage(),
                    'total'        => $productsQuery->total(),
                    'per_page'     => $productsQuery->perPage(),
                ],
            ];
        }

        // ── بحث التجار ────────────────────────────────────────
        if ($type === 'traders' || $type === 'all') {
            $tradersQuery = Store::where('store_type', 'تاجر')
                ->where('is_approved', true)
                ->where('id', '!=', $grocery->id)
                ->where('store_name', 'like', "%{$query}%")
                ->orderByRaw("
                    CASE
                        WHEN store_name LIKE ? THEN 1
                        WHEN store_name LIKE ? THEN 2
                        ELSE 3
                    END, store_name ASC
                ", ["{$query}%", "%{$query}%"])
                ->paginate(10, ['*'], 'page', $page);

            $response['traders'] = [
                'data'       => SearchResource::collection($tradersQuery)->resolve(),
                'pagination' => [
                    'current_page' => $tradersQuery->currentPage(),
                    'last_page'    => $tradersQuery->lastPage(),
                    'total'        => $tradersQuery->total(),
                    'per_page'     => $tradersQuery->perPage(),
                ],
            ];
        }

        return response()->json($response);
    }

    // ═══════════════════════════════════════════════════════════
    // GET /api/auth/grocery/marketplace/search/suggestions
    //
    // اقتراحات سريعة أثناء الكتابة (max 8 نتائج)
    // Query params:
    //   q    = نص البحث (required, min:1)
    //   type = 'products' | 'traders' | 'all'
    // ═══════════════════════════════════════════════════════════
    public function suggestions(Request $request)
    {
        $request->validate([
            'q'    => ['required', 'string', 'min:1', 'max:50'],
            'type' => ['nullable', 'in:products,traders,all'],
        ]);

        $grocery = $this->ensureGrocery();
        $query   = trim($request->q);
        $type    = $request->type ?? 'all';

        $suggestions = collect();

        // اقتراحات منتجات
        if ($type === 'products' || $type === 'all') {
            $products = Product::available()
                ->whereHas('store', fn($q) => $q
                    ->where('store_type', 'تاجر')
                    ->where('is_approved', true)
                    ->where('id', '!=', $grocery->id)
                )
                ->where('name', 'like', "%{$query}%")
                ->orderByRaw("CASE WHEN name LIKE ? THEN 0 ELSE 1 END", ["{$query}%"])
                ->limit($type === 'all' ? 4 : 8)
                ->pluck('name', 'id');

            foreach ($products as $id => $name) {
                $suggestions->push([
                    'id'    => $id,
                    'text'  => $name,
                    'type'  => 'product',
                    'icon'  => 'inventory_2',
                ]);
            }
        }

        // اقتراحات تجار
        if ($type === 'traders' || $type === 'all') {
            $traders = Store::where('store_type', 'تاجر')
                ->where('is_approved', true)
                ->where('id', '!=', $grocery->id)
                ->where('store_name', 'like', "%{$query}%")
                ->orderByRaw("CASE WHEN store_name LIKE ? THEN 0 ELSE 1 END", ["{$query}%"])
                ->limit($type === 'all' ? 4 : 8)
                ->pluck('store_name', 'id');

            foreach ($traders as $id => $name) {
                $suggestions->push([
                    'id'    => $id,
                    'text'  => $name,
                    'type'  => 'trader',
                    'icon'  => 'store',
                ]);
            }
        }

        return response()->json([
            'status'      => true,
            'query'       => $query,
            'suggestions' => $suggestions->take(8)->values(),
        ]);
    }
}
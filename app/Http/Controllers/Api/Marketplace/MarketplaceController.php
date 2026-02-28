<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Http\Resources\MarketplaceProductResource;
use App\Http\Resources\TraderResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MarketplaceController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth:sanctum');
    // }

    private function ensureGrocery()
    {
        $store = Auth::user()->store;

        if (!$store || !$store->isGrocery()) {
            abort(403);
        }

        return $store;
    }

    // عرض جميع منتجات التجار
    public function products()
    {
        $grocery = $this->ensureGrocery();

        $products = Product::with('store')
            ->available()
            ->whereHas('store', function ($q) use ($grocery) {
                $q->where('store_type', 'تاجر')
                  ->where('is_approved', true)
                  ->where('id', '!=', $grocery->id);
            })
            ->paginate(20);

        return MarketplaceProductResource::collection($products);
    }

    // منتجات التجار القريبين
    public function nearbyProducts(Request $request)
    {
        $grocery = $this->ensureGrocery();

        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'radius' => 'nullable|numeric'
        ]);

        $radius = $request->radius ?? 10;

        $stores = Store::where('store_type', 'تاجر')
            ->where('is_approved', true)
            ->where('id', '!=', $grocery->id)
            ->nearby($request->lat, $request->lng, $radius)
            ->pluck('id');

        $products = Product::with('store')
            ->available()
            ->whereIn('store_id', $stores)
            ->paginate(20);

        return MarketplaceProductResource::collection($products);
    }

    // عرض جميع التجار
    public function traders()
    {
        $grocery = $this->ensureGrocery();

        $traders = Store::where('store_type', 'تاجر')
            ->where('is_approved', true)
            ->where('id', '!=', $grocery->id)
            ->paginate(20);

        return TraderResource::collection($traders);
    }

    // عرض التجار القريبين
    public function nearbyTraders(Request $request)
    {
        $grocery = $this->ensureGrocery();

        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'radius' => 'nullable|numeric'
        ]);

        $radius = $request->radius ?? 10;

        $traders = Store::where('store_type', 'تاجر')
            ->where('is_approved', true)
            ->where('id', '!=', $grocery->id)
            ->nearby($request->lat, $request->lng, $radius)
            ->paginate(20);

        return TraderResource::collection($traders);
    }
}
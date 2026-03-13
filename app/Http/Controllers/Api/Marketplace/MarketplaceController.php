<?php

namespace App\Http\Controllers\Api\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\TraderResource;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MarketplaceController extends Controller
{
    private function ensureGrocery()
    {
        $store = Auth::user()?->store;
        if (!$store || !$store->isGrocery()) abort(403);
        return $store;
    }

    private function merchantProductsQuery($grocery)
    {
        return Product::with('store')
            ->available()
            ->whereHas('store', fn($q) => $q
                ->where('store_type', 'تاجر')
                ->where('is_approved', true)
                ->where('id', '!=', $grocery->id)
            );
    }

    // GET /marketplace/products
    public function products()
    {
        $grocery  = $this->ensureGrocery();
        $products = $this->merchantProductsQuery($grocery)->paginate(20);
        return ProductResource::collection($products);
    }

    // GET /marketplace/products/nearby
    public function nearbyProducts(Request $request)
    {
        $grocery = $this->ensureGrocery();
        $request->validate([
            'lat'    => 'required|numeric',
            'lng'    => 'required|numeric',
            'radius' => 'nullable|numeric|min:1|max:200',
        ]);

        $storeIds = Store::where('store_type', 'تاجر')
            ->where('is_approved', true)
            ->where('id', '!=', $grocery->id)
            ->nearby($request->lat, $request->lng, $request->radius ?? 10)
            ->pluck('id');

        $products = Product::with('store')
            ->available()
            ->whereIn('store_id', $storeIds)
            ->paginate(20);

        return ProductResource::collection($products);
    }

    // GET /marketplace/products/{id}
    public function productDetail(int $id)
    {
        $grocery = $this->ensureGrocery();
        $product = $this->merchantProductsQuery($grocery)->findOrFail($id);
        return response()->json([
            'status' => true,
            'data'   => new ProductResource($product),
        ]);
    }

    // GET /marketplace/traders
    public function traders()
    {
        $grocery = $this->ensureGrocery();
        $traders = Store::where('store_type', 'تاجر')
            ->where('is_approved', true)
            ->where('id', '!=', $grocery->id)
            ->paginate(20);
        return TraderResource::collection($traders);
    }

    // GET /marketplace/traders/nearby
    public function nearbyTraders(Request $request)
    {
        $grocery = $this->ensureGrocery();
        $request->validate([
            'lat'    => 'required|numeric',
            'lng'    => 'required|numeric',
            'radius' => 'nullable|numeric|min:1|max:200',
        ]);

        $traders = Store::where('store_type', 'تاجر')
            ->where('is_approved', true)
            ->where('id', '!=', $grocery->id)
            ->nearby($request->lat, $request->lng, $request->radius ?? 10)
            ->paginate(20);

        return TraderResource::collection($traders);
    }

    // GET /marketplace/traders/{id}/products
    public function traderProducts(int $id)
    {
        $grocery = $this->ensureGrocery();

        $store = Store::where('store_type', 'تاجر')
            ->where('is_approved', true)
            ->where('id', '!=', $grocery->id)
            ->findOrFail($id);

        $products = Product::with('store')
            ->where('store_id', $store->id)
            ->available()
            ->latest()
            ->paginate(20);

        return ProductResource::collection($products);
    }
}
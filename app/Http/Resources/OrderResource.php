<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // موافقة التاجر الحالي — محمّلة في MerchantOrderController
        $myApproval = $this->whenLoaded('merchantApprovals',
            fn() => $this->merchantApprovals->first()
        );

        return [
            'id'               => $this->id,
            'status'           => $this->status,
            'payment_status'   => $this->payment_status,
            'approval_flow'    => $this->approval_flow,
            'customer_visible' => $this->customer_visible,
            'delivery_address' => $this->delivery_address,
            'notes'            => $this->notes,
            'total_amount'     => (float) $this->total_amount,
            'delivery_fee'     => (float) $this->delivery_fee,
            'grand_total'      => (float) $this->grand_total,
            'created_at'       => $this->created_at?->format('Y-m-d H:i'),
            'updated_at'       => $this->updated_at?->format('Y-m-d H:i'),

            // ── صاحب الطلب (البقالة) ──
            'grocery' => $this->whenLoaded('grocery', fn() => [
                'id'      => $this->grocery->id,
                'name'    => $this->grocery->store_name,
                'address' => $this->grocery->address,
            ]),

            // ── طريقة الدفع ──
            'payment_method' => $this->whenLoaded('paymentMethod', fn() => [
                'id'   => $this->paymentMethod->id,
                'name' => $this->paymentMethod->name,
            ]),

            // ── المنتجات ──
            'items' => $this->whenLoaded('orderDetails', fn() =>
                $this->orderDetails->map(fn($d) => [
                    'id'            => $d->id,
                    'quantity'      => $d->quantity,
                    'price_at_time' => (float) $d->price_at_time,
                    'subtotal'      => (float) $d->subtotal,
                    'store_id'      => $d->store_id,
                    'product'       => $d->relationLoaded('product') ? [
                        'id'        => $d->product->id,
                        'name'      => $d->product->name,
                        'unit_type' => $d->product->unit_type ?? null,
                        'image_url' => $d->product->image_url ?? null,
                    ] : null,
                ])
            ),

            // ── موافقة التاجر الحالي (للتاجر فقط) ──
            'my_approval' => $myApproval ? [
                'status'      => $myApproval->status,
                'approved_at' => $myApproval->approved_at?->format('Y-m-d H:i'),
            ] : null,

            // ✅ هل يستطيع التاجر الموافقة/الرفض؟
            // true فقط إذا كان الدفع دين (approval_flow=merchant) والحالة بانتظار
            'can_approve' => $this->approval_flow === 'merchant'
                && $myApproval
                && $myApproval->status === 'بانتظار',

            // ── موافقات جميع التجار (للبقالة فقط) ──
            'merchant_approvals' => $this->whenLoaded('merchantApprovals',
                fn() => $this->merchantApprovals->map(fn($a) => [
                    'merchant_store_id' => $a->merchant_store_id,
                    'merchant_name'     => $a->relationLoaded('merchantStore')
                        ? $a->merchantStore->store_name
                        : null,
                    'status'            => $a->status,
                    'approved_at'       => $a->approved_at?->format('Y-m-d H:i'),
                ])
            ),
        ];
    }
}
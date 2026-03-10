<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $order = $this->whenLoaded('order');

        return [
            'id'             => $this->id,
            'invoice_type'   => $this->invoice_type,
            'invoice_status' => $this->invoice_status,
            'total_amount'   => (float) $this->total_amount,
            'paid_amount'    => (float) $this->paid_amount,
            'remaining_amount'=> (float) $this->remaining_amount,
            'is_paid'        => $this->is_paid,
            'sent_at'        => $this->sent_at?->format('Y-m-d H:i'),
            'created_at'     => $this->created_at?->format('Y-m-d H:i'),

            // ── الطلب ──
            'order' => $order ? [
                'id'             => $order->id,
                'status'         => $order->status,
                'payment_status' => $order->payment_status,
                'created_at'     => $order->created_at?->format('Y-m-d H:i'),
                'payment_method' => $order->relationLoaded('paymentMethod')
                    ? $order->paymentMethod?->name : null,
            ] : null,

            // ── المتجر البائع (التاجر) ──
            'merchant' => $this->whenLoaded('merchant', fn() => [
                'id'      => $this->merchant->id,
                'name'    => $this->merchant->store_name,
                'address' => $this->merchant->address,
            ]),

            // ── المتجر المشتري (البقالة) ──
            'customer' => $this->whenLoaded('customer', fn() => [
                'id'      => $this->customer->id,
                'name'    => $this->customer->store_name,
                'address' => $this->customer->address,
            ]),

            // ── المنتجات (من تفاصيل الطلب) ──
            'items' => $this->whenLoaded('order', function () {
                if (!$this->order->relationLoaded('orderDetails')) return [];
                return $this->order->orderDetails->map(fn($d) => [
                    'id'            => $d->id,
                    'product_name'  => $d->product?->name ?? '—',
                    'unit_type'     => $d->product?->unit_type,
                    'quantity'      => $d->quantity,
                    'price_at_time' => (float) $d->price_at_time,
                    'subtotal'      => (float) $d->subtotal,
                ]);
            }),

            // ── المعاملات المالية ──
            'transactions' => $this->whenLoaded('transactions', fn() =>
                $this->transactions->map(fn($t) => [
                    'id'               => $t->id,
                    'amount'           => (float) $t->amount,
                    'status'           => $t->status,
                    'reference'        => $t->reference,
                    'transaction_date' => $t->transaction_date?->format('Y-m-d H:i'),
                ])
            ),
        ];
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Exception;

class InvoiceController extends Controller
{
    // ─────────────────────────────────────────
    private function getMerchantStore()
    {
        $user = Auth::user();
        if (!$user || !$user->store || !$user->store->isActive()) {
            return null;
        }
        return $user->store;
    }

    // ── جلب الفاتورة مع بيانات المستخدم (العميل) ──
    private function getInvoiceWithRelations(int $invoiceId, int $storeId)
    {
        return Invoice::where('id', $invoiceId)
            ->where('store_id', $storeId)
            ->with([
                'order',
                'order.paymentMethod:id,name',
                'order.orderDetails' => fn($q) => $q->where('store_id', $storeId)
                    ->with('product:id,name,unit_type,price'),
                // التاجر (البائع) + صاحب المتجر
                'merchant:id,store_name,address,commercial_info,user_id',
                'merchant.owner:id,first_name,last_name,phone',
                // العميل (البقالة) + صاحب المتجر
                'customer:id,store_name,address,user_id',
                'customer.owner:id,first_name,last_name,phone',
                'transactions',
            ])
            ->first();
    }

    // =========================================================
    // GET /api/auth/merchant/invoices
    // =========================================================
    public function index(Request $request)
    {
        try {
            $store = $this->getMerchantStore();
            if (!$store) return response()->json(['status' => false, 'message' => 'غير مصرح لك'], 403);

            $query = Invoice::where('store_id', $store->id)
                ->with([
                    'order:id,status,payment_status,created_at,payment_method_id',
                    'order.paymentMethod:id,name',
                    'customer:id,store_name,address,user_id',
                    'customer.owner:id,first_name,last_name,phone',
                    'transactions',
                ])
                ->latest();

            if ($request->filled('type'))   $query->where('invoice_type',   $request->type);
            if ($request->filled('status')) $query->where('invoice_status', $request->status);

            $invoices = $query->paginate(15);

            $base    = fn() => Invoice::where('store_id', $store->id);
            $summary = [
                'total_amount'   => $base()->sum('total_amount'),
                'pending_amount' => $base()->where('invoice_status', 'بانتظار')->sum('total_amount'),
                'sent_amount'    => $base()->where('invoice_status', 'مرسلة')->sum('total_amount'),
                'paid_amount'    => $base()->where('invoice_status', 'مدفوعة')->sum('total_amount'),
                'total_count'    => $base()->count(),
            ];

            return response()->json([
                'status'     => true,
                'message'    => 'تم جلب الفواتير بنجاح',
                'summary'    => $summary,
                'data'       => InvoiceResource::collection($invoices),
                'pagination' => [
                    'current_page' => $invoices->currentPage(),
                    'last_page'    => $invoices->lastPage(),
                    'per_page'     => $invoices->perPage(),
                    'total'        => $invoices->total(),
                    'has_more'     => $invoices->hasMorePages(),
                ],
            ]);
        } catch (Exception $e) {
            return $this->serverError();
        }
    }

    // =========================================================
    // GET /api/auth/merchant/invoices/{id}
    // =========================================================
    public function show(int $id)
    {
        try {
            $store = $this->getMerchantStore();
            if (!$store) return response()->json(['status' => false, 'message' => 'غير مصرح لك'], 403);

            $invoice = $this->getInvoiceWithRelations($id, $store->id);
            if (!$invoice) return response()->json(['status' => false, 'message' => 'الفاتورة غير موجودة'], 404);

            return response()->json(['status' => true, 'data' => new InvoiceResource($invoice)]);
        } catch (Exception $e) {
            return $this->serverError();
        }
    }

    // =========================================================
    // GET /api/auth/merchant/invoices/{id}/pdf
    // ✅ ملف واحد فقط — يُعاد استخدامه إذا وُجد
    // =========================================================
    public function generatePdf(Request $request, int $id)
    {
        try {
            $store = $this->getMerchantStore();
            if (!$store) return response()->json(['status' => false, 'message' => 'غير مصرح لك'], 403);

            $invoice = $this->getInvoiceWithRelations($id, $store->id);
            if (!$invoice) return response()->json(['status' => false, 'message' => 'الفاتورة غير موجودة'], 404);

            $filename = 'invoice-' . $invoice->id . '-store-' . $store->id . '.pdf';
            $path     = 'invoices/' . $filename;
            $appUrl   = rtrim(config('app.url'), '/');

            // ✅ إذا الملف موجود والمستخدم لم يطلب إعادة التوليد — أرجعه مباشرة
            $forceRegen = $request->boolean('force', false);
            if (!$forceRegen && Storage::disk('public')->exists($path)) {
                return response()->json([
                    'status'   => true,
                    'message'  => 'تم جلب الفاتورة',
                    'pdf_url'  => $appUrl . '/storage/' . $path,
                    'filename' => $filename,
                    'cached'   => true,
                ]);
            }

            // ✅ لا يوجد — أنشئه مرة واحدة واحفظه
            Storage::disk('public')->put($path, $this->buildPdf($invoice));

            return response()->json([
                'status'   => true,
                'message'  => 'تم إنشاء الفاتورة بنجاح',
                'pdf_url'  => $appUrl . '/storage/' . $path,
                'filename' => $filename,
                'cached'   => false,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'خطأ أثناء إنشاء الفاتورة: ' . $e->getMessage(),
            ], 500);
        }
    }

    // =========================================================
    // buildPdf — الطريقة الصحيحة لـ mPDF header/footer
    // يجب كتابة <htmlpageheader> داخل WriteHTML نفسه
    // ثم تفعيله بـ <sethtmlpageheader> في بداية الصفحة
    // =========================================================
    private function buildPdf(Invoice $invoice): string
    {
        $defaultConfig     = (new ConfigVariables())->getDefaults();
        $defaultFontConfig = (new FontVariables())->getDefaults();

        $mpdf = new Mpdf([
            'mode'             => 'utf-8',
            'format'           => 'A4',
            'margin_top'       => 65,   // فراغ للـ header
            'margin_bottom'    => 50,   // فراغ للـ footer
            'margin_left'      => 16,
            'margin_right'     => 16,
            'default_font'     => 'dejavusans',
            'direction'        => 'rtl',
            'fontDir'          => $defaultConfig['fontDir'],
            'fontdata'         => $defaultFontConfig['fontdata'],
            'autoScriptToLang' => true,
            'autoLangToFont'   => true,
        ]);
        $mpdf->SetDirectionality('rtl');

        // ── جمع البيانات ──
        $order    = $invoice->order;
        $merchant = $invoice->merchant;
        $customer = $invoice->customer;
        $items    = $order?->orderDetails ?? collect();
        $tx       = $invoice->transactions->where('status', 'ناجحة')->first()
                    ?? $invoice->transactions->first();

        // بيانات التاجر
        $merchantName  = htmlspecialchars($merchant?->store_name ?? 'غير محدد');
        $merchantAddr  = htmlspecialchars($merchant?->address   ?? '');
        $ci            = $merchant?->commercial_info ?? [];
        $merchantPhone = htmlspecialchars($ci['phone'] ?? ($merchant?->owner?->phone ?? ''));

        // بيانات العميل (User)
        $cOwner = $customer?->owner;
        $cName  = $cOwner->full_name;
        $cPhone = htmlspecialchars($cOwner?->phone ?? '');
        $cStore = htmlspecialchars($customer?->store_name ?? 'غير محدد');

        // ── بناء HTML الكامل (header + footer + body في وثيقة واحدة) ──
        $html  = $this->pdfStyles();
        $html .= $this->pdfHeader($merchantName, $merchantPhone, $merchantAddr);
        $html .= $this->pdfFooter($invoice, $order, $tx);

        // تفعيل الـ header والـ footer على الصفحة الأولى
        $html .= '<sethtmlpageheader name="main-header" value="on" show-this-page="1" />';
        $html .= '<sethtmlpagefooter name="main-footer" value="on" />';

        $html .= $this->pdfBody($invoice, $order, $items, $cName, $cPhone, $cStore, $tx);

        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    // =========================================================
    // HEADER — يظهر ثابتاً في أعلى كل صفحة
    // ─────────────────────────────────────────────────────────
    // ┌─────────────────────────────────────────────────────┐
    // │  ██  اسم التاجر الكبير          الهاتف / العنوان  │
    // │      فاتورة إلكترونية رسمية                        │
    // ├─────────────────── شريط ذهبي ──────────────────────┤
    // =========================================================
    private function pdfHeader(string $name, string $phone, string $addr): string
{
    $phoneRow = $phone ? "<div style='font-size:10px;color:#d7e6ff;'>☎ {$phone}</div>" : '';
    $addrRow  = $addr  ? "<div style='font-size:10px;color:#d7e6ff;'>📍 {$addr}</div>"  : '';

    return <<<HTML
<htmlpageheader name="main-header">

<table width="100%" style="border-bottom:3px solid #c9963a;background:#0b1f5e;" cellpadding="0" cellspacing="0">
<tr>

<td style="padding:15px 18px;vertical-align:middle">

<div style="font-size:22px;font-weight:bold;color:#ffffff;">
{$name}
</div>

<div style="font-size:11px;color:#a9c4f3;margin-top:2px;">
فاتورة إلكترونية رسمية
</div>

</td>

<td style="padding:15px 18px;text-align:left;vertical-align:middle">

{$phoneRow}
{$addrRow}

</td>

</tr>
</table>

<table width="100%" style="background:#f5f8ff;border-bottom:1px solid #d8e4f5" cellpadding="0" cellspacing="0">
<tr>

<td style="padding:8px 18px;font-size:11px;color:#333;">
رقم الفاتورة: <strong># {PAGENO}</strong>
</td>

<td style="padding:8px 18px;text-align:left;font-size:11px;color:#333;">
التاريخ: {DATE j-m-Y}
</td>

</tr>
</table>

</htmlpageheader>
HTML;
}

    // =========================================================
    // FOOTER — يظهر ثابتاً في أسفل كل صفحة
    // ─────────────────────────────────────────────────────────
    // ├─────────────────── شريط ذهبي ──────────────────────┤
    // │  📅 التاريخ  |  🔖 رقم الطلب  |  📋 المرجع        │
    // │  [حالة]  فاتورة #X | نظام صله © 2025              │
    // │  نص: هذه فاتورة إلكترونية...                       │
    // └─────────────────────────────────────────────────────┘
    // =========================================================
 private function pdfFooter(Invoice $invoice, $order, $tx): string
{
    $date   = $invoice->created_at?->format('Y/m/d');
    $ordId  = $order?->id ?? '—';
    $ref    = htmlspecialchars($tx?->reference ?? "ORD-{$ordId}");
    $year   = date('Y');

    return <<<HTML
<htmlpagefooter name="main-footer">

<table width="100%" cellpadding="0" cellspacing="0" style="border-top:2px solid #c9963a">

<tr>
<td style="padding:6px 18px;font-size:10px;color:#444;">

رقم الطلب: <strong>#{$ordId}</strong>
&nbsp;&nbsp;|&nbsp;&nbsp;
المرجع: <strong>{$ref}</strong>

</td>

<td style="padding:6px 18px;text-align:left;font-size:10px;color:#444;">
{$date}
</td>
</tr>

</table>

<table width="100%" cellpadding="0" cellspacing="0" style="background:#0b1f5e">

<tr>

<td style="padding:8px 18px;color:#d6e3ff;font-size:9px;text-align:center">

هذه فاتورة إلكترونية صادرة من نظام صله ولا تحتاج إلى توقيع أو ختم

</td>

</tr>

</table>

<table width="100%" cellpadding="0" cellspacing="0" style="background:#08153f">

<tr>

<td style="padding:6px 18px;color:#8aa5db;font-size:9px">

© {$year} نظام صله

</td>

<td style="padding:6px 18px;text-align:left;color:#8aa5db;font-size:9px">

صفحة {PAGENO} من {nbpg}

</td>

</tr>

</table>

</htmlpagefooter>
HTML;
}

    // =========================================================
    // CSS الصفحة الداخلية
    // =========================================================
    private function pdfStyles(): string
    {
        return <<<HTML
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body  { font-family:dejavusans,sans-serif; color:#1a2340; direction:rtl; font-size:12px; }

/* ── عنوان الفاتورة ── */
.page-title      { font-size:22px; font-weight:bold; color:#0b1f5e; letter-spacing:1px; }
.page-title span { color:#c9963a; }
.page-subtitle   { font-size:10px; color:#888; margin-top:2px; }

/* ── خط فاصل ذهبي ── */
.gold-line { height:2px; background:linear-gradient(90deg,#c9963a,#f5d97e,#c9963a); margin:10px 0; border:none; }

/* ── بطاقة معلومات ── */
.card {
    border:1px solid #d8e4f5;
    border-radius:7px;
    padding:10px 14px;
    background:#f5f8ff;
    margin-bottom:12px;
}
.card-title {
    font-size:10px;
    font-weight:bold;
    color:#0b1f5e;
    letter-spacing:0.5px;
    text-transform:uppercase;
    border-bottom:1px solid #d8e4f5;
    padding-bottom:5px;
    margin-bottom:7px;
}
.card-row { font-size:12px; color:#444; margin-bottom:3px; line-height:1.55; }
.card-row strong { color:#0b1f5e; }

/* ── جدول المنتجات ── */
.tbl { width:100%; border-collapse:collapse; margin-bottom:14px; border-radius:7px; overflow:hidden; }
.tbl thead tr  { background:#0b1f5e; }
.tbl thead th  { color:#fff; font-size:11px; font-weight:bold; padding:9px 10px; text-align:center; }
.tbl thead th.left { text-align:right; }
.tbl tbody td  { padding:8px 10px; font-size:12px; border-bottom:1px solid #eaf0fb; text-align:center; color:#333; }
.tbl tbody td.left  { text-align:right; font-weight:bold; color:#0b1f5e; }
.tbl tbody td.money { font-weight:bold; color:#1a3a8f; }
.tbl tbody tr:last-child td { border-bottom:none; }
.tbl tbody tr.even { background:#f2f6ff; }

/* ── جدول الإجماليات ── */
.totals { width:42%; border-collapse:collapse; float:left; margin-bottom:14px; }
.totals td { padding:6px 12px; font-size:12px; border-bottom:1px solid #eaf0fb; }
.totals .lbl { color:#666; }
.totals .val { font-weight:bold; color:#0b1f5e; text-align:left; }
.totals tr.grand td {
    background:#0b1f5e;
    color:#fff;
    font-size:14px;
    font-weight:bold;
    padding:9px 12px;
    border:none;
}
.totals tr.grand td.val { color:#f5d97e; text-align:left; }

/* ── شارة الحالة ── */
.badge {
    display:inline-block;
    padding:4px 14px;
    border-radius:20px;
    font-size:11px;
    font-weight:bold;
}
</style>
HTML;
    }

    // =========================================================
    // BODY — المحتوى المتغير بين الـ header والـ footer
    // ─────────────────────────────────────────────────────────
    //   [ عنوان الفاتورة كبير ]  +  [ شارة الحالة ]
    //   ──────────── خط ذهبي ────────────
    //   [ بطاقة العميل: الاسم / الهاتف / المتجر ]
    //   ──────────── خط ذهبي ────────────
    //   [ جدول المنتجات ]
    //   [ جدول الإجماليات ]
    // =========================================================
    private function pdfBody(
        Invoice $invoice,
        $order,
        $items,
        string $cName,
        string $cPhone,
        string $cStore,
        $tx,
    ): string {
        $invType = $invoice->invoice_type === 'master' ? 'فاتورة إجمالية' : 'فاتورة تاجر';
        $invId   = $invoice->id;

        // شارة الحالة
        [$sBg, $sFg] = match($invoice->invoice_status) {
            'مدفوعة' => ['#e6f5ea', '#1a6b2f'],
            'مرسلة'  => ['#e6ecff', '#1a3a9f'],
            default  => ['#fff8e0', '#8a5a00'],
        };

        $payMethod = htmlspecialchars($order?->paymentMethod?->name ?? 'دين');

        // ── صفوف المنتجات ──
        $rows   = '';
        $rowNum = 1;
        foreach ($items as $item) {
            $cls   = $rowNum % 2 === 0 ? ' class="even"' : '';
            $pName = htmlspecialchars($item->product->name     ?? '—');
            $unit  = htmlspecialchars($item->product->unit_type ?? '');
            $rows .= "
            <tr{$cls}>
              <td style='color:#aaa;'>{$rowNum}</td>
              <td class='left'>{$pName}</td>
              <td>{$unit}</td>
              <td>{$item->quantity}</td>
              <td>" . number_format($item->price_at_time, 2) . "</td>
              <td class='money'>" . number_format($item->subtotal, 2) . "</td>
            </tr>";
            $rowNum++;
        }

        $totalFmt = number_format($invoice->total_amount, 2);

        return <<<HTML
<!-- ══ عنوان الفاتورة ══ -->
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:6px;">
  <tr>
    <td style="vertical-align:bottom;">
      <div class="page-title">الـ<span>فـاتـو</span>رة</div>
      <div class="page-subtitle">{$invType} &nbsp;#&nbsp;{$invId}</div>
    </td>
    <td style="vertical-align:top; text-align:left; width:28%;">
      <span class="badge" style="background:{$sBg}; color:{$sFg}; border:1px solid {$sFg};">
        {$invoice->invoice_status}
      </span>
    </td>
  </tr>
</table>

<div class="gold-line"></div>

<!-- ══ بيانات العميل ══ -->
<div class="card">
  <div class="card-title">&#128100; بيانات العميل</div>
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td width="50%">
        <div class="card-row"><strong>الاسم:</strong> {$cName}</div>
        <div class="card-row"><strong>المتجر:</strong> {$cStore}</div>
      </td>
      <td width="50%">
        <div class="card-row"><strong>الهاتف:</strong> {$cPhone}</div>
        <div class="card-row"><strong>طريقة الدفع:</strong> {$payMethod}</div>
      </td>
    </tr>
  </table>
</div>

<div class="gold-line"></div>

<!-- ══ جدول المنتجات ══ -->
<table class="tbl">
  <thead>
    <tr>
      <th style="width:6%;">#</th>
      <th class="left" style="width:33%;">المنتج</th>
      <th style="width:11%;">الوحدة</th>
      <th style="width:10%;">الكمية</th>
      <th style="width:18%;">سعر الوحدة</th>
      <th style="width:18%;">الإجمالي</th>
    </tr>
  </thead>
  <tbody>
    {$rows}
  </tbody>
</table>

<!-- ══ الإجماليات ══ -->
<table class="totals">
  <tr>
    <td class="lbl">المجموع الفرعي</td>
    <td class="val">{$totalFmt} ر.ي</td>
  </tr>
  <tr>
    <td class="lbl">الضريبة (0%)</td>
    <td class="val">0.00 ر.ي</td>
  </tr>
  <tr class="grand">
    <td class="lbl">المجموع الكلي</td>
    <td class="val">{$totalFmt} ر.ي</td>
  </tr>
</table>
HTML;
    }

    private function serverError()
    {
        return response()->json(['status' => false, 'message' => 'حدث خطأ في الخادم'], 500);
    }
}
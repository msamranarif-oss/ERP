<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseBill;
use App\Models\Installment;
use App\Models\TenantSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class DocumentController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────

    private function tid(): int
    {
        return auth()->user()->tenant_id;
    }

    /**
     * Resolve template theme from request. Accepts: classic | modern | minimal.
     */
    private function theme(Request $request): string
    {
        return in_array($request->get('template'), ['classic', 'modern', 'minimal'])
            ? $request->get('template')
            : 'classic';
    }

    /**
     * Company info pulled from tenant settings (falls back gracefully).
     */
    private function company(): array
    {
        $settings = TenantSetting::where('tenant_id', $this->tid())->first();
        $logoPath = $settings->logo_path ?? null;

        // Resolve absolute path for logos stored in storage/public
        $logoBase64 = null;
        if ($logoPath) {
            $absPath = str_starts_with($logoPath, '/')
                ? $logoPath
                : storage_path('app/public/' . ltrim($logoPath, '/'));
            if (file_exists($absPath)) {
                $mime       = mime_content_type($absPath);
                $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($absPath));
            }
        }

        return [
            'name'        => $settings->company_name ?? config('app.name'),
            'address'     => $settings->address       ?? '',
            'phone'       => $settings->phone         ?? '',
            'email'       => $settings->email         ?? '',
            'tax_number'  => $settings->tax_number    ?? '',
            'logo_base64' => $logoBase64,
        ];
    }

    /** Render a PDF and download it. */
    private function pdf(string $view, array $data, string $filename, string $theme = 'classic'): Response
    {
        $payload = array_merge($data, [
            'company' => $this->company(),
            'theme'   => $theme,
        ]);
        $pdf = Pdf::loadView($view, $payload)->setPaper('a4', 'portrait');
        return $pdf->download($filename);
    }

    /** Stream a PDF in-browser. */
    private function stream(string $view, array $data, string $filename, string $theme = 'classic'): Response
    {
        $payload = array_merge($data, [
            'company' => $this->company(),
            'theme'   => $theme,
        ]);
        return Pdf::loadView($view, $payload)->setPaper('a4', 'portrait')->stream($filename);
    }

    // ─────────────────────────────────────────────────────────────────
    //  SALE INVOICE  (?template=classic|modern|minimal)
    // ─────────────────────────────────────────────────────────────────

    /** Download sale invoice as PDF */
    public function downloadInvoice(Request $request, int $id): Response
    {
        $sale = Sale::where('tenant_id', $this->tid())
            ->with(['customer', 'items.product', 'items.unit', 'payments.paymentMethod', 'cashier', 'branch'])
            ->findOrFail($id);

        return $this->pdf('pdf.invoice', ['sale' => $sale],
            'invoice-' . ($sale->invoice_number ?? $sale->id) . '.pdf',
            $this->theme($request));
    }

    /** Stream invoice in-browser */
    public function streamInvoice(Request $request, int $id): Response
    {
        $sale = Sale::where('tenant_id', $this->tid())
            ->with(['customer', 'items.product', 'items.unit', 'payments.paymentMethod', 'cashier', 'branch'])
            ->findOrFail($id);

        return $this->stream('pdf.invoice', ['sale' => $sale],
            'invoice-' . $sale->id . '.pdf',
            $this->theme($request));
    }

    // ─────────────────────────────────────────────────────────────────
    //  PAYMENT RECEIPT
    // ─────────────────────────────────────────────────────────────────

    public function downloadPaymentReceipt(Request $request, int $paymentId): Response
    {
        $payment = SalePayment::with(['sale.customer', 'sale.payments', 'paymentMethod'])
            ->whereHas('sale', fn($q) => $q->where('tenant_id', $this->tid()))
            ->findOrFail($paymentId);

        return $this->pdf('pdf.payment_receipt', ['payment' => $payment],
            'receipt-RCT-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT) . '.pdf',
            $this->theme($request));
    }

    // ─────────────────────────────────────────────────────────────────
    //  PURCHASE ORDER
    // ─────────────────────────────────────────────────────────────────

    public function downloadPurchaseOrder(Request $request, int $id): Response
    {
        $po = PurchaseOrder::where('tenant_id', $this->tid())
            ->with(['supplier', 'items.product', 'warehouse', 'createdBy'])
            ->findOrFail($id);

        return $this->pdf('pdf.purchase_order', ['po' => $po],
            'PO-' . ($po->po_number ?? $po->id) . '.pdf',
            $this->theme($request));
    }

    // ─────────────────────────────────────────────────────────────────
    //  PURCHASE BILL
    // ─────────────────────────────────────────────────────────────────

    public function downloadPurchaseBill(Request $request, int $id): Response
    {
        $bill = PurchaseBill::where('tenant_id', $this->tid())
            ->with(['supplier', 'items.product', 'items.purchaseOrderItem.grnItems', 'purchaseOrder', 'createdBy'])
            ->findOrFail($id);

        return $this->pdf('pdf.purchase_bill', ['bill' => $bill],
            'bill-' . ($bill->bill_number ?? $bill->id) . '.pdf',
            $this->theme($request));
    }

    // ─────────────────────────────────────────────────────────────────
    //  INSTALLMENT RECEIPT
    // ─────────────────────────────────────────────────────────────────

    public function downloadInstallmentReceipt(Request $request, int $installmentId): Response
    {
        $installment = Installment::with([
                'creditSale.customer',
                'creditSale.installments',
                'paymentMethod',
            ])
            ->where('tenant_id', $this->tid())
            ->findOrFail($installmentId);

        return $this->pdf('pdf.installment_receipt', ['installment' => $installment],
            'installment-receipt-' . $installment->id . '.pdf',
            $this->theme($request));
    }

    // ─────────────────────────────────────────────────────────────────
    //  PARTIAL INVOICE PAYMENTS
    // ─────────────────────────────────────────────────────────────────

    /**
     * List all payments for a sale + outstanding balance.
     */
    public function salePayments(int $saleId): JsonResponse
    {
        $sale = Sale::where('tenant_id', $this->tid())
            ->with(['payments.paymentMethod', 'customer'])
            ->findOrFail($saleId);

        $paid    = $sale->payments->sum('amount');
        $balance = ($sale->total ?? 0) - $paid;

        return response()->json([
            'success' => true,
            'data' => [
                'sale_id'        => $sale->id,
                'invoice_number' => $sale->invoice_number ?? 'INV-'.$sale->id,
                'customer'       => $sale->customer?->only('id','name','phone'),
                'total'          => (float) ($sale->total ?? 0),
                'amount_paid'    => (float) $paid,
                'balance_due'    => (float) max($balance, 0),
                'payment_status' => $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
                'payments'       => $sale->payments,
            ],
        ]);
    }

    /**
     * Record a partial (or full) payment against a sale invoice.
     */
    public function recordSalePayment(Request $request, int $saleId): JsonResponse
    {
        $sale = Sale::where('tenant_id', $this->tid())->findOrFail($saleId);

        $data = $request->validate([
            'amount'            => 'required|numeric|min:0.01',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'payment_date'      => 'nullable|date',
            'reference'         => 'nullable|string|max:100',
            'notes'             => 'nullable|string|max:500',
        ]);

        $paid    = $sale->payments()->sum('amount');
        $balance = ($sale->total ?? 0) - $paid;

        if ($balance <= 0) {
            return response()->json(['success' => false, 'message' => 'This invoice is already fully paid.'], 422);
        }

        if ((float)$data['amount'] > $balance) {
            return response()->json([
                'success' => false,
                'message' => "Amount exceeds balance due. Maximum payable: " . number_format($balance, 2),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $payment = SalePayment::create([
                'tenant_id'         => $this->tid(),
                'sale_id'           => $sale->id,
                'payment_method_id' => $data['payment_method_id'],
                'amount'            => $data['amount'],
                'payment_date'      => $data['payment_date'] ?? now()->toDateString(),
                'reference'         => $data['reference'] ?? null,
                'notes'             => $data['notes'] ?? null,
            ]);

            $newPaid    = $paid + (float)$data['amount'];
            $newBalance = ($sale->total ?? 0) - $newPaid;
            $sale->update([
                'payment_status' => $newBalance <= 0 ? 'paid' : 'partial',
                'paid_amount'    => $newPaid,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Record sale payment failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        $freshPaid    = $sale->fresh()->payments()->sum('amount');
        $freshBalance = ($sale->total ?? 0) - $freshPaid;

        return response()->json([
            'success' => true,
            'message' => 'Payment recorded successfully.',
            'data' => [
                'payment'        => $payment->load('paymentMethod'),
                'total'          => (float) ($sale->total ?? 0),
                'amount_paid'    => (float) $freshPaid,
                'balance_due'    => (float) max($freshBalance, 0),
                'payment_status' => $freshBalance <= 0 ? 'paid' : 'partial',
            ],
        ], 201);
    }

    /**
     * List payments for a purchase bill.
     */
    public function billPayments(int $billId): JsonResponse
    {
        $bill = PurchaseBill::where('tenant_id', $this->tid())
            ->with(['supplier'])
            ->findOrFail($billId);

        $balance = ($bill->total ?? 0) - ($bill->paid_amount ?? 0);

        return response()->json([
            'success' => true,
            'data' => [
                'bill_id'        => $bill->id,
                'bill_number'    => $bill->bill_number ?? 'BILL-'.$bill->id,
                'supplier'       => $bill->supplier?->only('id','name'),
                'total_amount'   => (float) ($bill->total ?? 0),
                'paid_amount'    => (float) ($bill->paid_amount ?? 0),
                'balance_due'    => (float) max($balance, 0),
                'payment_status' => $balance <= 0 ? 'paid' : (($bill->paid_amount ?? 0) > 0 ? 'partial' : 'unpaid'),
            ],
        ]);
    }

    /**
     * Record a partial payment against a purchase bill.
     */
    public function recordBillPayment(Request $request, int $billId): JsonResponse
    {
        $bill = PurchaseBill::where('tenant_id', $this->tid())->findOrFail($billId);

        $data = $request->validate([
            'amount'       => 'required|numeric|min:0.01',
            'payment_date' => 'nullable|date',
            'reference'    => 'nullable|string|max:100',
            'notes'        => 'nullable|string|max:500',
        ]);

        $balance = ($bill->total ?? 0) - ($bill->paid_amount ?? 0);

        if ($balance <= 0) {
            return response()->json(['success' => false, 'message' => 'This bill is already fully paid.'], 422);
        }
        if ((float)$data['amount'] > $balance) {
            return response()->json(['success' => false, 'message' => "Amount exceeds balance due. Maximum: " . number_format($balance, 2)], 422);
        }

        DB::beginTransaction();
        try {
            $bill->increment('paid_amount', (float)$data['amount']);
            $bill->refresh();
            $newBalance = ($bill->total ?? 0) - $bill->paid_amount;
            $bill->update(['payment_status' => $newBalance <= 0 ? 'paid' : 'partial']);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Record bill payment failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        return response()->json([
            'success' => true,
            'message' => 'Bill payment recorded.',
            'data' => [
                'total_amount'   => (float) ($bill->total ?? 0),
                'paid_amount'    => (float) $bill->paid_amount,
                'balance_due'    => (float) max($newBalance, 0),
                'payment_status' => $newBalance <= 0 ? 'paid' : 'partial',
            ],
        ]);
    }
}

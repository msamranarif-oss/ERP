<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CreditSale;
use App\Models\CreditSaleItem;
use App\Models\Installment;
use App\Models\CreditCustomer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CreditSaleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant');
    }

    public function index(Request $request)
    {
        $query = CreditSale::with(['customer.customer', 'items.product', 'installments']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('credit_sale_number', 'like', '%' . $request->search . '%')
                  ->orWhereHas('customer.customer', function ($sub) use ($request) {
                      $sub->where('name', 'like', '%' . $request->search . '%');
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $creditSales = $query->orderBy('created_at', 'desc')
                             ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $creditSales
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:credit_customers,id',
            'down_payment' => 'required|numeric|min:0',
            'loan_amount' => 'required|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'number_of_installments' => 'required|integer|min:1|max:120',
            'installment_frequency' => 'required|in:weekly,biweekly,monthly,quarterly',
            'first_installment_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_percent' => 'nullable|numeric|min:0|max:100',
            'tax_amount' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $creditCustomer = CreditCustomer::find($validated['customer_id']);
        if ($creditCustomer->tenant_id !== auth()->user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credit customer.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $creditSale = CreditSale::create([
                'credit_sale_number' => 'CS-' . date('Y') . '-' . str_pad(CreditSale::whereYear('created_at', date('Y'))->count() + 1, 4, '0', STR_PAD_LEFT),
                'customer_id' => $validated['customer_id'],
                'down_payment' => $validated['down_payment'],
                'loan_amount' => $validated['loan_amount'],
                'interest_rate' => $validated['interest_rate'],
                'number_of_installments' => $validated['number_of_installments'],
                'installment_frequency' => $validated['installment_frequency'],
                'first_installment_date' => $validated['first_installment_date'],
                'sub_total' => $validated['total_amount'] - ($validated['tax_amount'] ?? 0) - ($validated['shipping_cost'] ?? 0),
                'discount_amount' => $validated['discount_amount'] ?? 0,
                'tax_amount' => $validated['tax_amount'] ?? 0,
                'shipping_cost' => $validated['shipping_cost'] ?? 0,
                'total_amount' => $validated['total_amount'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'active',
                'tenant_id' => auth()->user()->tenant_id,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                CreditSaleItem::create([
                    'credit_sale_id' => $creditSale->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                    'tax_percent' => $item['tax_percent'] ?? 0,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'subtotal' => $item['quantity'] * $item['unit_price'],
                    'tenant_id' => auth()->user()->tenant_id,
                ]);
            }

            // Create installments
            $this->generateInstallments($creditSale, $validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $creditSale->load(['customer.customer', 'items.product', 'installments']),
                'message' => 'Credit sale created successfully.'
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create credit sale: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(CreditSale $credit_sale)
    {
        return response()->json([
            'success' => true,
            'data' => $credit_sale->load(['customer.customer', 'items.product', 'installments'])
        ]);
    }

    public function update(Request $request, CreditSale $credit_sale)
    {
        $validated = $request->validate([
            'notes' => 'sometimes|nullable|string|max:1000',
        ]);

        $credit_sale->update($validated);

        return response()->json([
            'success' => true,
            'data' => $credit_sale->load(['customer.customer', 'items.product', 'installments']),
            'message' => 'Credit sale updated successfully.'
        ]);
    }

    public function destroy(CreditSale $credit_sale)
    {
        if ($credit_sale->status !== 'pending' && $credit_sale->installments()->where('status', 'pending')->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete credit sale with pending installments.'
            ], 422);
        }

        $credit_sale->items()->delete();
        $credit_sale->installments()->delete();
        $credit_sale->delete();

        return response()->json([
            'success' => true,
            'message' => 'Credit sale deleted successfully.'
        ]);
    }

    public function recordPayment(CreditSale $credit_sale, Request $request)
    {
        $validated = $request->validate([
            'installment_id' => 'required|exists:installments,id',
            'amount' => 'required|numeric|min:0',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $installment = Installment::find($validated['installment_id']);
        if ($installment->credit_sale_id !== $credit_sale->id) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid installment for this credit sale.'
            ], 422);
        }

        if ($installment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Installment is not pending.'
            ], 422);
        }

        if ($validated['amount'] > $installment->remaining_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Payment amount exceeds remaining installment amount.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create payment record
            $payment = \App\Models\Payment::create([
                'payment_number' => 'PMT-' . date('Y') . '-' . str_pad(\App\Models\Payment::whereYear('created_at', date('Y'))->count() + 1, 4, '0', STR_PAD_LEFT),
                'credit_sale_id' => $credit_sale->id,
                'installment_id' => $validated['installment_id'],
                'payment_method_id' => $validated['payment_method_id'],
                'amount' => $validated['amount'],
                'status' => 'completed',
                'tenant_id' => auth()->user()->tenant_id,
                'created_by' => auth()->id(),
            ]);

            // Update installment
            $installment->update([
                'paid_amount' => $installment->paid_amount + $validated['amount'],
                'remaining_amount' => $installment->remaining_amount - $validated['amount'],
                'status' => ($installment->remaining_amount - $validated['amount']) <= 0 ? 'paid' : 'partial',
            ]);

            // Update credit sale status if all installments are paid
            if ($credit_sale->installments()->where('status', '!=', 'paid')->count() === 0) {
                $credit_sale->update(['status' => 'completed']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $payment,
                'message' => 'Payment recorded successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to record payment: ' . $e->getMessage()
            ], 500);
        }
    }

    public function schedule(CreditSale $credit_sale)
    {
        $installments = $credit_sale->installments()->orderBy('due_date')->get();

        return response()->json([
            'success' => true,
            'data' => $installments
        ]);
    }

    private function generateInstallments(CreditSale $creditSale, array $data)
    {
        $principalPerInstallment = $creditSale->loan_amount / $creditSale->number_of_installments;
        $interestPerInstallment = ($creditSale->loan_amount * ($creditSale->interest_rate / 100)) / $creditSale->number_of_installments;
        $amountPerInstallment = $principalPerInstallment + $interestPerInstallment;

        $currentDate = new \DateTime($data['first_installment_date']);
        $frequencyInterval = $this->getFrequencyInterval($data['installment_frequency']);

        for ($i = 1; $i <= $data['number_of_installments']; $i++) {
            Installment::create([
                'credit_sale_id' => $creditSale->id,
                'installment_number' => $i,
                'due_date' => $currentDate->format('Y-m-d'),
                'principal_amount' => $principalPerInstallment,
                'interest_amount' => $interestPerInstallment,
                'total_amount' => $amountPerInstallment,
                'paid_amount' => 0,
                'remaining_amount' => $amountPerInstallment,
                'status' => 'pending',
                'tenant_id' => auth()->user()->tenant_id,
            ]);

            // Move to next installment date based on frequency
            $currentDate->modify($frequencyInterval);
        }
    }

    private function getFrequencyInterval(string $frequency): string
    {
        switch ($frequency) {
            case 'weekly':
                return '+1 week';
            case 'biweekly':
                return '+2 weeks';
            case 'monthly':
                return '+1 month';
            case 'quarterly':
                return '+3 months';
            default:
                return '+1 month';
        }
    }
}
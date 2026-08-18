<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PurchaseBill;
use App\Services\PurchaseBillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseBillController extends Controller
{
    protected PurchaseBillService $billService;

    public function __construct(PurchaseBillService $billService)
    {
        $this->billService = $billService;
        $this->authorizeResource(PurchaseBill::class, 'purchase_bill');
    }

    public function index(Request $request): JsonResponse
    {
        $query = PurchaseBill::with(['purchaseOrder', 'supplier', 'createdBy', 'items.purchaseOrderItem.grnItems']);

        if ($request->filled('search')) {
            $query->where('bill_number', 'like', '%'.$request->search.'%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        $bills = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $bills,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'bill_number' => 'nullable|string|max:100',
            'bill_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:bill_date',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $bill = $this->billService->createFromPO($validated['purchase_order_id'], $validated);

            return response()->json([
                'success' => true,
                'data' => $bill,
                'message' => 'Purchase Bill created successfully.',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(PurchaseBill $purchase_bill): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $purchase_bill->load(['purchaseOrder', 'supplier', 'items.product', 'items.purchaseOrderItem.grnItems', 'createdBy']),
        ]);
    }

    public function approve(PurchaseBill $purchase_bill): JsonResponse
    {
        try {
            $bill = $this->billService->approve($purchase_bill->id);

            return response()->json([
                'success' => true,
                'data' => $bill,
                'message' => 'Purchase Bill approved and posted to accounting.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function pay(Request $request, PurchaseBill $purchase_bill): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'payment_method' => 'nullable|in:cash,bank,card,cheque,transfer',
            'reference' => 'nullable|string|max:100',
        ]);

        try {
            $bill = $this->billService->processPayment(
                billId: $purchase_bill->id,
                amount: $validated['amount'],
                bankAccountId: $validated['bank_account_id'] ?? null,
                paymentMethod: $validated['payment_method'] ?? null,
                reference: $validated['reference'] ?? null
            );

            return response()->json([
                'success' => true,
                'data' => $bill,
                'message' => 'Payment processed successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}

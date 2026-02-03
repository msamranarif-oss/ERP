<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Installment;
use App\Models\CreditSale;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InstallmentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant');
    }

    public function overdue()
    {
        $today = now()->toDateString();
        $installments = Installment::where('due_date', '<', $today)
                                  ->where('status', 'pending')
                                  ->whereHas('creditSale', function ($query) {
                                      $query->where('status', 'active');
                                  })
                                  ->with(['creditSale.customer.customer'])
                                  ->orderBy('due_date', 'asc')
                                  ->get();

        return response()->json([
            'success' => true,
            'data' => $installments
        ]);
    }

    public function dueToday()
    {
        $today = now()->toDateString();
        $installments = Installment::where('due_date', $today)
                                  ->where('status', 'pending')
                                  ->whereHas('creditSale', function ($query) {
                                      $query->where('status', 'active');
                                  })
                                  ->with(['creditSale.customer.customer'])
                                  ->get();

        return response()->json([
            'success' => true,
            'data' => $installments
        ]);
    }

    public function upcoming()
    {
        $today = now()->toDateString();
        $nextWeek = now()->addDays(7)->toDateString();
        
        $installments = Installment::whereBetween('due_date', [$today, $nextWeek])
                                  ->where('status', 'pending')
                                  ->whereHas('creditSale', function ($query) {
                                      $query->where('status', 'active');
                                  })
                                  ->with(['creditSale.customer.customer'])
                                  ->orderBy('due_date', 'asc')
                                  ->get();

        return response()->json([
            'success' => true,
            'data' => $installments
        ]);
    }

    public function index(Request $request)
    {
        $query = Installment::with(['creditSale.customer.customer']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('creditSale.creditSaleNumber', 'like', '%' . $request->search . '%')
                  ->orWhereHas('creditSale.customer.customer.name', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_id')) {
            $query->whereHas('creditSale', function ($sub) use ($request) {
                $sub->where('customer_id', $request->customer_id);
            });
        }

        $installments = $query->orderBy('due_date', 'asc')
                               ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $installments
        ]);
    }

    public function show(Installment $installment)
    {
        return response()->json([
            'success' => true,
            'data' => $installment->load(['creditSale.customer.customer'])
        ]);
    }

    public function update(Installment $installment, Request $request)
    {
        $validated = $request->validate([
            'notes' => 'sometimes|nullable|string|max:1000',
        ]);

        $installment->update($validated);

        return response()->json([
            'success' => true,
            'data' => $installment->load(['creditSale.customer.customer']),
            'message' => 'Installment updated successfully.'
        ]);
    }

    public function pay(Installment $installment, Request $request)
    {
        if ($installment->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Installment is not pending.'
            ], 422);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0|max:' . $installment->remaining_amount,
            'payment_method_id' => 'required|exists:payment_methods,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $creditSale = $installment->creditSale;

        if ($creditSale->tenant_id !== auth()->user()->tenant_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        \DB::beginTransaction();
        try {
            // Create payment record
            $payment = \App\Models\Payment::create([
                'payment_number' => 'PMT-' . date('Y') . '-' . str_pad(\App\Models\Payment::whereYear('created_at', date('Y'))->count() + 1, 4, '0', STR_PAD_LEFT),
                'credit_sale_id' => $creditSale->id,
                'installment_id' => $installment->id,
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
                'status' => ($installment->remaining_amount <= 0) ? 'paid' : 'partial',
            ]);

            // Update credit sale status if all installments are paid
            if ($creditSale->installments()->where('status', '!=', 'paid')->count() === 0) {
                $creditSale->update(['status' => 'completed']);
            }

            \DB::commit();

            return response()->json([
                'success' => true,
                'data' => $payment,
                'message' => 'Payment recorded successfully.'
            ]);
        } catch (\Exception $e) {
            \DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to record payment: ' . $e->getMessage()
            ], 500);
        }
    }
}
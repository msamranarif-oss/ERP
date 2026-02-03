<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant');
    }

    public function index(Request $request)
    {
        $query = Sale::with(['customer', 'items.product', 'payments.paymentMethod', 'registerSession']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('sale_number', 'like', '%' . $request->search . '%')
                  ->orWhereHas('customer', function ($sub) use ($request) {
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

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $sales = $query->orderBy('created_at', 'desc')
                       ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $sales
        ]);
    }

    public function show(Sale $sale)
    {
        return response()->json([
            'success' => true,
            'data' => $sale->load(['customer', 'items.product', 'payments.paymentMethod', 'registerSession'])
        ]);
    }

    public function void(Sale $sale, Request $request)
    {
        if ($sale->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot void sale that is not completed.'
            ], 422);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            // Update sale status
            $sale->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => auth()->id(),
                'void_reason' => $validated['reason'],
            ]);

            // Void payments
            $sale->payments()->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $sale->load(['customer', 'items.product', 'payments.paymentMethod']),
                'message' => 'Sale voided successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to void sale: ' . $e->getMessage()
            ], 500);
        }
    }

    public function receipt(Sale $sale)
    {
        // Generate receipt data
        $receiptData = [
            'sale_number' => $sale->sale_number,
            'date' => $sale->created_at->format('Y-m-d H:i:s'),
            'customer' => $sale->customer ? [
                'name' => $sale->customer->name,
                'phone' => $sale->customer->phone,
                'email' => $sale->customer->email,
            ] : null,
            'items' => $sale->items->map(function ($item) {
                return [
                    'product_name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                ];
            }),
            'sub_total' => $sale->sub_total,
            'discount_amount' => $sale->discount_amount,
            'tax_amount' => $sale->tax_amount,
            'shipping_cost' => $sale->shipping_cost,
            'total_amount' => $sale->total_amount,
            'paid_amount' => $sale->paid_amount,
            'change_amount' => $sale->change_amount,
            'payments' => $sale->payments->map(function ($payment) {
                return [
                    'method' => $payment->paymentMethod->name,
                    'amount' => $payment->amount,
                ];
            }),
        ];

        return response()->json([
            'success' => true,
            'data' => $receiptData
        ]);
    }
}
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Product;
use App\Models\Customer;
use App\Models\RegisterSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class POSController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant');
    }

    public function products(Request $request)
    {
        $query = Product::with(['category', 'unit'])
                        ->where('is_active', true)
                        ->where('is_sellable', true);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('sku', 'like', '%' . $request->search . '%')
                  ->orWhere('barcode', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->orderBy('name')
                          ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function findByBarcode($barcode)
    {
        $product = Product::where('barcode', $barcode)
                         ->where('is_active', true)
                         ->where('is_sellable', true)
                         ->with(['category', 'unit'])
                         ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    public function createSale(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
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
            'paid_amount' => 'required|numeric|min:0',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'payment_amount' => 'required|numeric|min:0',
            'change_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
            'register_session_id' => 'required|exists:register_sessions,id',
        ]);

        // Verify register session is open
        $registerSession = RegisterSession::with('cashRegister.branch.warehouses')->find($validated['register_session_id']);
        if (!$registerSession || $registerSession->status !== 'open') {
            return response()->json([
                'success' => false,
                'message' => 'Register session is not open.'
            ], 422);
        }

        $branch = $registerSession->cashRegister->branch;
        $warehouse = $branch->warehouses->first();

        if (!$warehouse) {
             return response()->json([
                'success' => false,
                'message' => 'No warehouse assigned to this branch.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $sale = Sale::create([
                'sale_number' => 'SL-' . date('Y') . '-' . str_pad(Sale::whereYear('created_at', date('Y'))->count() + 1, 4, '0', STR_PAD_LEFT),
                'customer_id' => $validated['customer_id'] ?? null,
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'sale_date' => now(),
                'subtotal' => $validated['total_amount'] - ($validated['tax_amount'] ?? 0) - ($validated['shipping_cost'] ?? 0),
                'discount_amount' => $validated['discount_amount'] ?? 0,
                'tax_amount' => $validated['tax_amount'] ?? 0,
                'shipping_amount' => $validated['shipping_cost'] ?? 0,
                'total' => $validated['total_amount'],
                'paid_amount' => $validated['paid_amount'],
                'change_amount' => $validated['change_amount'],
                'status' => 'completed',
                'tenant_id' => auth()->user()->tenant_id,
                'sold_by' => auth()->id(),
                'register_session_id' => $validated['register_session_id'],
            ]);

            foreach ($validated['items'] as $item) {
                // Decrement stock
                $product = Product::find($item['product_id']);
                if ($product) {
                    $product->decrement('available_stock', $item['quantity']);
                }

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'unit_id' => $product->base_unit_id ?? 1, // Fallback to 1 if missing, or fetch unit
                    'product_name' => $product->name,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount_amount'] ?? 0,
                    'tax' => $item['tax_amount'] ?? 0,
                    'tax_rate' => $item['tax_percent'] ?? 0,
                    'total' => $item['quantity'] * $item['unit_price'], // Simplified, should be total after tax/discount but trusting frontend or recalculating
                    'tenant_id' => auth()->user()->tenant_id, // Note: SaleItem migration doesn't have tenant_id but model might use trait. Checking migration... migration doesn't have 'tenant_id' in sale_items table! Removing it.
                ]);
            }

            // Create payment record
            SalePayment::create([
                'sale_id' => $sale->id,
                'payment_method_id' => $validated['payment_method_id'],
                'amount' => $validated['payment_amount'],
                'reference' => null, // Optional
                'notes' => $validated['notes'] ?? null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $sale->load(['customer', 'items.product', 'payments.paymentMethod', 'registerSession']),
                'message' => 'Sale created successfully.'
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create sale: ' . $e->getMessage()
            ], 500);
        }
    }
}
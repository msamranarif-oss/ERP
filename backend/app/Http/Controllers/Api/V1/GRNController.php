<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceivedNote;
use App\Models\PurchaseOrder;
use App\Services\GRNService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GRNController extends Controller
{
    protected GRNService $grnService;

    public function __construct(GRNService $grnService)
    {
        $this->grnService = $grnService;
        $this->authorizeResource(GoodsReceivedNote::class, 'goods_received_note');
    }

    public function index(Request $request): JsonResponse
    {
        $query = GoodsReceivedNote::with(['purchaseOrder', 'warehouse', 'createdBy']);

        if ($request->filled('search')) {
            $query->where('grn_number', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('purchase_order_id')) {
            $query->where('purchase_order_id', $request->purchase_order_id);
        }

        $grns = $query->orderBy('created_at', 'desc')
                      ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $grns
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'received_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_received' => 'required|numeric|min:0',
        ]);

        try {
            $grn = $this->grnService->createFromPO($validated['purchase_order_id'], $validated);

            return response()->json([
                'success' => true,
                'data' => $grn,
                'message' => 'Goods Received Note created successfully.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show(GoodsReceivedNote $goods_received_note): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $goods_received_note->load(['purchaseOrder', 'warehouse', 'items.product', 'createdBy'])
        ]);
    }
}

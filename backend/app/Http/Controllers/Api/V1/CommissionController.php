<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CommissionRule;
use App\Models\SaleCommission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    // ── Rules ─────────────────────────────────────────────────────────

    public function indexRules(Request $request): JsonResponse
    {
        $rules = CommissionRule::where('tenant_id', auth()->user()->tenant_id)
                               ->with('user')
                               ->get();
        return response()->json(['success' => true, 'data' => $rules]);
    }

    public function storeRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'      => 'nullable|exists:users,id',
            'rate_percent' => 'required|numeric|min:0|max:100',
            'is_active'    => 'boolean',
        ]);
        $data['tenant_id'] = auth()->user()->tenant_id;
        $rule = CommissionRule::create($data);
        return response()->json(['success' => true, 'data' => $rule], 201);
    }

    public function updateRule(Request $request, CommissionRule $rule): JsonResponse
    {
        abort_if($rule->tenant_id !== auth()->user()->tenant_id, 403);
        $rule->update($request->validate([
            'rate_percent' => 'sometimes|numeric|min:0|max:100',
            'is_active'    => 'boolean',
        ]));
        return response()->json(['success' => true, 'data' => $rule->fresh()]);
    }

    public function destroyRule(CommissionRule $rule): JsonResponse
    {
        abort_if($rule->tenant_id !== auth()->user()->tenant_id, 403);
        $rule->delete();
        return response()->json(['success' => true, 'message' => 'Rule deleted.']);
    }

    // ── Earned Commissions ────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = SaleCommission::where('tenant_id', auth()->user()->tenant_id)
                               ->with(['sale', 'user']);

        if ($request->filled('user_id')) $query->where('user_id', $request->user_id);
        if ($request->filled('status'))  $query->where('status', $request->status);

        return response()->json(['success' => true, 'data' => $query->orderByDesc('created_at')->paginate($request->per_page ?? 20)]);
    }

    public function markPaid(Request $request): JsonResponse
    {
        $ids = $request->validate(['commission_ids' => 'required|array', 'commission_ids.*' => 'exists:sale_commissions,id'])['commission_ids'];
        SaleCommission::whereIn('id', $ids)
                      ->where('tenant_id', auth()->user()->tenant_id)
                      ->update(['status' => 'paid']);
        return response()->json(['success' => true, 'message' => count($ids) . ' commissions marked as paid.']);
    }
}

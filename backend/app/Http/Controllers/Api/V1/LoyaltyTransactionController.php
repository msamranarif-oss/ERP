<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyTransaction;
use App\Http\Resources\LoyaltyTransactionResource;
use App\Http\Requests\LoyaltyTransactionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class LoyaltyTransactionController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(LoyaltyTransaction::class, 'loyalty_transaction');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = LoyaltyTransaction::with(['customer', 'sale']);

        // Filter by customer
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by points range
        if ($request->filled('min_points')) {
            $query->where('points', '>=', $request->min_points);
        }
        if ($request->filled('max_points')) {
            $query->where('points', '<=', $request->max_points);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        // Filter by reference
        if ($request->filled('reference')) {
            $query->where('reference', 'like', '%' . $request->reference . '%');
        }

        $perPage = min($request->per_page ?? 15, 100);
        $loyaltyTransactions = $query->paginate($perPage);

        return LoyaltyTransactionResource::collection($loyaltyTransactions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LoyaltyTransactionRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $loyaltyTransaction = LoyaltyTransaction::create($validated);
            
            DB::commit();

            return new LoyaltyTransactionResource($loyaltyTransaction);
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(LoyaltyTransaction $loyalty_transaction)
    {
        return new LoyaltyTransactionResource($loyalty_transaction->load(['customer', 'sale']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(LoyaltyTransactionRequest $request, LoyaltyTransaction $loyalty_transaction)
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $loyalty_transaction->update($validated);
            
            DB::commit();

            return new LoyaltyTransactionResource($loyalty_transaction);
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LoyaltyTransaction $loyalty_transaction)
    {
        $loyalty_transaction->delete();
        
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Get customer's loyalty points balance
     */
    public function getBalance(Request $request, $customerId)
    {
        $totalEarned = LoyaltyTransaction::where('customer_id', $customerId)
            ->where('type', 'earned')
            ->sum('points');
            
        $totalRedeemed = LoyaltyTransaction::where('customer_id', $customerId)
            ->where('type', 'redeemed')
            ->sum('points');
            
        $balance = $totalEarned - $totalRedeemed;

        return response()->json([
            'customer_id' => $customerId,
            'total_earned' => $totalEarned,
            'total_redeemed' => $totalRedeemed,
            'balance' => max(0, $balance),
        ]);
    }

    /**
     * Get customer's loyalty transaction history
     */
    public function getHistory(Request $request, $customerId)
    {
        $query = LoyaltyTransaction::where('customer_id', $customerId)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc');

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        $perPage = min($request->per_page ?? 15, 100);
        $transactions = $query->paginate($perPage);

        return LoyaltyTransactionResource::collection($transactions);
    }
}
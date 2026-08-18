<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CashTransaction;
use App\Http\Resources\CashTransactionResource;
use App\Http\Requests\CashTransactionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class CashTransactionController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(CashTransaction::class, 'cash_transaction');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = CashTransaction::query();

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filter by amount range
        if ($request->filled('min_amount')) {
            $query->where('amount', '>=', $request->min_amount);
        }
        if ($request->filled('max_amount')) {
            $query->where('amount', '<=', $request->max_amount);
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

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by branch
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $perPage = min($request->per_page ?? 15, 100);
        $cashTransactions = $query->paginate($perPage);

        return CashTransactionResource::collection($cashTransactions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CashTransactionRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $cashTransaction = CashTransaction::create($validated);
            
            DB::commit();

            return new CashTransactionResource($cashTransaction);
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(CashTransaction $cash_transaction)
    {
        return new CashTransactionResource($cash_transaction);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CashTransactionRequest $request, CashTransaction $cash_transaction)
    {
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $cash_transaction->update($validated);
            
            DB::commit();

            return new CashTransactionResource($cash_transaction);
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CashTransaction $cash_transaction)
    {
        $cash_transaction->delete();
        
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Get transactions by type
     */
    public function getByType(Request $request, string $type)
    {
        $query = CashTransaction::where('type', $type);

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        // Filter by branch
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $perPage = min($request->per_page ?? 15, 100);
        $cashTransactions = $query->paginate($perPage);

        return CashTransactionResource::collection($cashTransactions);
    }

    /**
     * Get daily totals
     */
    public function dailyTotals(Request $request)
    {
        $query = CashTransaction::selectRaw(
            'DATE(transaction_date) as date, type, SUM(amount) as total, COUNT(*) as count'
        )->groupBy('date', 'type');

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        // Filter by branch
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $results = $query->get();

        return response()->json($results);
    }
}
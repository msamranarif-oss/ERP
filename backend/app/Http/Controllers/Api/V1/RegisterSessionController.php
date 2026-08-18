<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Models\RegisterSession;
use App\Models\CashRegister;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RegisterSessionController extends ApiController
{
    public function __construct()
    {
        $this->authorizeResource(RegisterSession::class, 'register_session');
    }

    public function index(Request $request)
    {
        // Uses BelongsToTenant global scope
        $query = RegisterSession::with(['cashRegister', 'openedBy', 'closedBy']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('cashRegister', function ($sub) use ($request) {
                    $sub->where('name', 'like', '%' . $request->search . '%');
                });
            });
        }

        if ($request->filled('cash_register_id')) {
            $query->where('cash_register_id', $request->cash_register_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $sessions = $query->orderBy('created_at', 'desc')
                          ->paginate($request->per_page ?? 15);

        return response()->json(['success' => true, 'data' => $sessions]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cash_register_id' => 'required|exists:cash_registers,id',
            'opening_cash'     => 'required|numeric|min:0',
            'notes'            => 'nullable|string|max:1000',
        ]);

        // Check if there's already an open session for this register (tenant-scoped via BelongsToTenant)
        $openSession = RegisterSession::where('cash_register_id', $validated['cash_register_id'])
                                      ->where('status', 'open')
                                      ->first();

        if ($openSession) {
            return response()->json([
                'success' => false,
                'message' => 'This cash register already has an open session.',
            ], 422);
        }

        $session = RegisterSession::create([
            'cash_register_id' => $validated['cash_register_id'],
            'opening_balance'  => $validated['opening_cash'],
            'opening_notes'    => $validated['notes'] ?? null,
            'status'           => 'open',
            'tenant_id'        => auth()->user()->tenant_id,
            'user_id'          => auth()->id(),
            'opened_at'        => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $session->load(['cashRegister', 'openedBy']),
            'message' => 'Register session opened successfully.',
        ], 201);
    }

    public function show(RegisterSession $register_session)
    {
        return response()->json([
            'success' => true,
            'data'    => $register_session->load(['cashRegister', 'openedBy', 'closedBy']),
        ]);
    }

    public function close(RegisterSession $register_session, Request $request)
    {
        if ($register_session->status !== 'open') {
            return response()->json([
                'success' => false,
                'message' => 'Register session is not open.',
            ], 422);
        }

        $validated = $request->validate([
            'closing_balance' => 'nullable|numeric|min:0',
            'closing_cash'    => 'nullable|numeric|min:0',
            'expected_balance' => 'nullable|numeric|min:0',
            'expected_cash'   => 'nullable|numeric|min:0',
            'difference'      => 'nullable|numeric',
            'variance'        => 'nullable|numeric',
            'notes'           => 'nullable|string|max:1000',
        ]);

        $closingBalance = (float) ($validated['closing_balance'] ?? $validated['closing_cash'] ?? 0);
        $expectedBalance = (float) ($validated['expected_balance'] ?? $validated['expected_cash'] ?? $register_session->calculateExpectedBalance());
        $difference = isset($validated['difference'])
            ? (float) $validated['difference']
            : (isset($validated['variance']) ? (float) $validated['variance'] : $closingBalance - $expectedBalance);

        DB::beginTransaction();
        try {
            $register_session->update([
                'closing_balance'  => $closingBalance,
                'expected_balance' => $expectedBalance,
                'difference'       => $difference,
                'closing_notes'    => $validated['notes'] ?? null,
                'status'           => 'closed',
                'closed_at'        => now(),
                'closed_by'        => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data'    => $register_session->load(['cashRegister', 'openedBy', 'closedBy']),
                'message' => 'Register session closed successfully.',
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to close register session: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fix 12: Returns the current user's open session, not just any open session for the tenant.
     */
    public function current(): JsonResponse
    {
        $session = RegisterSession::where('status', 'open')
                                 ->where('tenant_id', auth()->user()->tenant_id)
                                 ->where('user_id', auth()->id())
                                 ->with(['cashRegister', 'openedBy'])
                                 ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'No open register session found for your account.',
            ], 404);
        }

        return response()->json(['success' => true, 'data' => $session]);
    }

    /**
     * Z-Report: sales summary, payment method breakdown, returns for a session.
     */
    public function zReport(RegisterSession $register_session, Request $request): JsonResponse
    {
        $tenantId   = auth()->user()->tenant_id;
        $sessionId  = $register_session->id;

        // Sales summary for this session
        $sales = \App\Models\Sale::where('tenant_id', $tenantId)
            ->where('register_session_id', $sessionId)
            ->selectRaw('COUNT(*) as total_transactions, SUM(subtotal) as gross_sales, SUM(discount_amount) as total_discounts, SUM(tax_amount) as total_tax, SUM(total) as net_sales')
            ->first();

        // Fix 6: JOIN payment_methods — sale_payments has no 'payment_method' text column
        $payments = \App\Models\SalePayment::join(
                        'payment_methods',
                        'sale_payments.payment_method_id',
                        '=',
                        'payment_methods.id'
                    )
                    ->whereHas('sale', fn($q) => $q->where('register_session_id', $sessionId))
                    ->selectRaw('payment_methods.name as payment_method, SUM(sale_payments.amount) as total')
                    ->groupBy('payment_methods.id', 'payment_methods.name')
                    ->get();

        // Refunds/returns
        $returns = \App\Models\Sale::where('tenant_id', $tenantId)
            ->where('register_session_id', $sessionId)
            ->where('status', 'refunded')
            ->selectRaw('COUNT(*) as count, SUM(total) as total')
            ->first();

        return response()->json(['success' => true, 'data' => [
            'register_session' => $register_session->only('id', 'opened_at', 'closed_at', 'opening_balance', 'closing_balance', 'difference'),
            'sales_summary'    => $sales,
            'payment_methods'  => $payments,
            'returns'          => $returns,
            'generated_at'     => now()->toDateTimeString(),
        ]]);
    }

    public function cashIn(RegisterSession $register_session, Request $request): JsonResponse
    {
        if ($register_session->status !== 'open') {
            return $this->errorResponse('Register session is not open.', 422);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($register_session, $validated) {
            \App\Models\CashTransaction::create([
                'tenant_id' => $register_session->tenant_id,
                'register_session_id' => $register_session->id,
                'type' => 'in',
                'amount' => $validated['amount'],
                'reason' => $validated['reason'],
                'transacted_by' => auth()->id(),
            ]);

            $register_session->increment('cash_in', $validated['amount']);
        });

        return $this->successResponse($register_session->refresh(), 'Cash added to drawer successfully.');
    }

    public function cashOut(RegisterSession $register_session, Request $request): JsonResponse
    {
        if ($register_session->status !== 'open') {
            return $this->errorResponse('Register session is not open.', 422);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
        ]);

        if ($register_session->expected_cash < $validated['amount']) {
             return $this->errorResponse('Insufficient cash in drawer.', 422);
        }

        DB::transaction(function () use ($register_session, $validated) {
            \App\Models\CashTransaction::create([
                'tenant_id' => $register_session->tenant_id,
                'register_session_id' => $register_session->id,
                'type' => 'out',
                'amount' => $validated['amount'],
                'reason' => $validated['reason'],
                'transacted_by' => auth()->id(),
            ]);

            $register_session->increment('cash_out', $validated['amount']);
        });

        return $this->successResponse($register_session->refresh(), 'Cash removed from drawer successfully.');
    }
}
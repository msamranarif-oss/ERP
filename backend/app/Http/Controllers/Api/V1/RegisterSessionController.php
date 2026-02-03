<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RegisterSession;
use App\Models\CashRegister;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class RegisterSessionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant');
    }

    public function index(Request $request)
    {
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

        return response()->json([
            'success' => true,
            'data' => $sessions
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cash_register_id' => 'required|exists:cash_registers,id',
            'opening_cash' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Check if there's already an open session for this register
        $openSession = RegisterSession::where('cash_register_id', $validated['cash_register_id'])
                                      ->whereNull('closed_at')
                                      ->first();

        if ($openSession) {
            return response()->json([
                'success' => false,
                'message' => 'This cash register already has an open session.',
            ], 422);
        }

        $session = RegisterSession::create([
            'cash_register_id' => $validated['cash_register_id'],
            'opening_cash' => $validated['opening_cash'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'open',
            'tenant_id' => auth()->user()->tenant_id,
            'opened_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $session->load(['cashRegister', 'openedBy']),
            'message' => 'Register session opened successfully.'
        ], 201);
    }

    public function show(RegisterSession $register_session)
    {
        return response()->json([
            'success' => true,
            'data' => $register_session->load(['cashRegister', 'openedBy', 'closedBy'])
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
            'closing_cash' => 'required|numeric|min:0',
            'expected_cash' => 'required|numeric|min:0',
            'variance' => 'required|numeric',
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            $register_session->update([
                'closing_cash' => $validated['closing_cash'],
                'expected_cash' => $validated['expected_cash'],
                'variance' => $validated['variance'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => auth()->id(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $register_session->load(['cashRegister', 'openedBy', 'closedBy']),
                'message' => 'Register session closed successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to close register session: ' . $e->getMessage()
            ], 500);
        }
    }

    public function current()
    {
        $session = RegisterSession::where('status', 'open')
                                 ->where('tenant_id', auth()->user()->tenant_id)
                                 ->with(['cashRegister', 'openedBy'])
                                 ->first();

        if (!$session) {
            return response()->json([
                'success' => false,
                'message' => 'No open register session found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $session
        ]);
    }
}
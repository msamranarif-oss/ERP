<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\ApiController;
use App\Models\PaymentReminder;
use App\Http\Requests\Credit\PaymentReminderRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentReminderController extends ApiController
{
    public function __construct()
    {
        $this->authorizeResource(PaymentReminder::class, 'payment_reminder');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PaymentReminder::with(['creditSale', 'installment'])
                              ->where('tenant_id', Auth::user()->tenant_id);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('type', 'like', '%' . $request->search . '%')
                  ->orWhere('status', 'like', '%' . $request->search . '%')
                  ->orWhere('message', 'like', '%' . $request->search . '%')
                  ->orWhereHas('creditSale.customer', function ($sub) use ($request) {
                      $sub->where('name', 'like', '%' . $request->search . '%');
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('credit_sale_id')) {
            $query->where('credit_sale_id', $request->credit_sale_id);
        }

        $paymentReminders = $query->orderBy('created_at', 'desc')
                                 ->paginate($request->per_page ?? 15);

        return $this->successResponse($paymentReminders, 'Payment reminders retrieved successfully');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PaymentReminderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = Auth::user()->tenant_id;

        $paymentReminder = PaymentReminder::create($data);

        return $this->successResponse($paymentReminder, 'Payment reminder created successfully.', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(PaymentReminder $payment_reminder): JsonResponse
    {
        $this->authorize('view', $payment_reminder);

        $paymentReminder = $payment_reminder->load(['creditSale', 'installment']);

        return $this->successResponse($paymentReminder, 'Payment reminder retrieved successfully');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PaymentReminderRequest $request, PaymentReminder $payment_reminder): JsonResponse
    {
        $this->authorize('update', $payment_reminder);

        $payment_reminder->update($request->validated());

        return $this->successResponse($payment_reminder, 'Payment reminder updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentReminder $payment_reminder): JsonResponse
    {
        $this->authorize('delete', $payment_reminder);

        $payment_reminder->delete();

        return $this->successResponse(null, 'Payment reminder deleted successfully.');
    }

    /**
     * Send a payment reminder
     */
    public function send(PaymentReminder $payment_reminder): JsonResponse
    {
        $this->authorize('update', $payment_reminder);

        if ($payment_reminder->status !== 'pending') {
            return $this->errorResponse('Payment reminder is not in pending status.', 422);
        }

        // This would typically integrate with an SMS/email service
        $payment_reminder->update([
            'status' => 'sent',
            'sent_at' => now()
        ]);

        return $this->successResponse($payment_reminder, 'Payment reminder sent successfully.');
    }

    /**
     * Get pending payment reminders
     */
    public function pending(): JsonResponse
    {
        $paymentReminders = PaymentReminder::where('tenant_id', Auth::user()->tenant_id)
                                         ->where('status', 'pending')
                                         ->with(['creditSale', 'installment'])
                                         ->orderBy('scheduled_at')
                                         ->get();

        return $this->successResponse($paymentReminders, 'Pending payment reminders retrieved successfully');
    }

    /**
     * Get overdue payment reminders
     */
    public function overdue(): JsonResponse
    {
        $paymentReminders = PaymentReminder::where('tenant_id', Auth::user()->tenant_id)
                                         ->where('status', 'pending')
                                         ->where('scheduled_at', '<', now())
                                         ->with(['creditSale', 'installment'])
                                         ->orderBy('scheduled_at')
                                         ->get();

        return $this->successResponse($paymentReminders, 'Overdue payment reminders retrieved successfully');
    }
}
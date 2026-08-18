<?php

namespace App\Services;

use App\Models\PaymentReminder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentReminderService extends BaseService
{
    public function __construct()
    {
        parent::__construct(new PaymentReminder());
    }

    /**
     * Get pending payment reminders
     */
    public function getPendingReminders()
    {
        return PaymentReminder::with(['creditSale.customer.customer'])
            ->where('status', 'pending')
            ->orderBy('reminder_date', 'asc')
            ->get();
    }

    /**
     * Get reminders for overdue installments
     */
    public function getOverduePaymentReminders()
    {
        return PaymentReminder::with(['creditSale.customer.customer', 'installment'])
            ->whereHas('installment', function ($query) {
                $query->where('due_date', '<', now())
                      ->where('status', '!=', 'paid');
            })
            ->where('status', '!=', 'sent')
            ->orderBy('reminder_date', 'asc')
            ->get();
    }

    /**
     * Send payment reminder
     */
    public function sendReminder(int $reminderId)
    {
        try {
            DB::beginTransaction();

            $reminder = PaymentReminder::findOrFail($reminderId);
            $reminder->update([
                'status' => 'sent',
                'sent_date' => now(),
            ]);

            DB::commit();

            return $reminder;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error sending payment reminder', [
                'error' => $e->getMessage(),
                'reminder_id' => $reminderId
            ]);
            
            throw $e;
        }
    }

    /**
     * Mark reminder as failed
     */
    public function markAsFailed(int $reminderId, string $failureReason = null)
    {
        try {
            DB::beginTransaction();

            $reminder = PaymentReminder::findOrFail($reminderId);
            $reminder->update([
                'status' => 'failed',
                'message' => $failureReason ?? $reminder->message,
            ]);

            DB::commit();

            return $reminder;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error marking payment reminder as failed', [
                'error' => $e->getMessage(),
                'reminder_id' => $reminderId,
                'reason' => $failureReason
            ]);
            
            throw $e;
        }
    }
}
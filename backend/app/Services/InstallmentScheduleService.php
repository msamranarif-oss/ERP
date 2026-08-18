<?php

namespace App\Services;

use App\Models\InstallmentSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InstallmentScheduleService extends BaseService
{
    public function __construct()
    {
        parent::__construct(new InstallmentSchedule());
    }

    /**
     * Get overdue installment schedules
     */
    public function getOverdueSchedules()
    {
        return InstallmentSchedule::with(['creditSale.customer.customer'])
            ->where('due_date', '<', now())
            ->where('status', '!=', 'paid')
            ->orderBy('due_date', 'asc')
            ->get();
    }

    /**
     * Get upcoming installment schedules due today
     */
    public function getDueTodaySchedules()
    {
        return InstallmentSchedule::with(['creditSale.customer.customer'])
            ->whereDate('due_date', now())
            ->where('status', '!=', 'paid')
            ->orderBy('due_date', 'asc')
            ->get();
    }

    /**
     * Get upcoming installment schedules
     */
    public function getUpcomingSchedules($days = 7)
    {
        return InstallmentSchedule::with(['creditSale.customer.customer'])
            ->whereBetween('due_date', [now(), now()->addDays($days)])
            ->where('status', '!=', 'paid')
            ->orderBy('due_date', 'asc')
            ->get();
    }

    /**
     * Calculate schedule totals
     */
    public function calculateScheduleTotals($creditSaleId = null)
    {
        $query = InstallmentSchedule::query();
        
        if ($creditSaleId) {
            $query->where('credit_sale_id', $creditSaleId);
        }

        $results = $query->selectRaw('
            SUM(amount) as total_scheduled,
            SUM(CASE WHEN status = "paid" THEN amount ELSE 0 END) as total_paid,
            SUM(CASE WHEN status != "paid" THEN amount ELSE 0 END) as total_remaining
        ')->first();

        return [
            'total_scheduled' => $results->total_scheduled ?? 0,
            'total_paid' => $results->total_paid ?? 0,
            'total_remaining' => $results->total_remaining ?? 0,
        ];
    }
}
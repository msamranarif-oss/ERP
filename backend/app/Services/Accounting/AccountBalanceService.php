<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class AccountBalanceService
{
    public function getBalance(
        int $accountId,
        ?DateTimeInterface $asAt = null,
        bool $withSign = true,
        ?int $tenantId = null,
    ): array {
        $account = ChartOfAccount::with('accountType')->findOrFail($accountId);
        $tenantId ??= $account->tenant_id;

        $asAtDate = $asAt ? $asAt->format('Y-m-d') : now()->format('Y-m-d');

        $fyStart = $this->resolveFyStart($tenantId, $asAtDate);

        $openingBalance = (float) $account->opening_balance;

        $priorMovements = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'jel.journal_entry_id', '=', 'je.id')
            ->where('jel.account_id', $accountId)
            ->where('jel.tenant_id', $tenantId)
            ->where('je.status', 'posted')
            ->whereDate('je.entry_date', '<', $fyStart)
            ->select([
                DB::raw('COALESCE(SUM(jel.debit), 0) as debit_total'),
                DB::raw('COALESCE(SUM(jel.credit), 0) as credit_total'),
            ])
            ->first();

        $periodMovements = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'jel.journal_entry_id', '=', 'je.id')
            ->where('jel.account_id', $accountId)
            ->where('jel.tenant_id', $tenantId)
            ->where('je.status', 'posted')
            ->whereDate('je.entry_date', '>=', $fyStart)
            ->whereDate('je.entry_date', '<=', $asAtDate)
            ->select([
                DB::raw('COALESCE(SUM(jel.debit), 0) as debit_total'),
                DB::raw('COALESCE(SUM(jel.credit), 0) as credit_total'),
            ])
            ->first();

        $priorDebit = (float) ($priorMovements->debit_total ?? 0);
        $priorCredit = (float) ($priorMovements->credit_total ?? 0);
        $periodDebit = (float) ($periodMovements->debit_total ?? 0);
        $periodCredit = (float) ($periodMovements->credit_total ?? 0);

        $debitNormal = $account->accountType->normal_balance === 'debit';

        $priorNet = $priorDebit - $priorCredit;
        $openingAdjusted = $openingBalance + $priorNet;

        $debitTotal = $periodDebit;
        $creditTotal = $periodCredit;
        $netMovement = $debitTotal - $creditTotal;

        $closingRaw = $openingAdjusted + $netMovement;

        $closingBalance = $withSign
            ? ($debitNormal ? $closingRaw : -$closingRaw)
            : $closingRaw;

        $openingForReturn = $withSign
            ? ($debitNormal ? $openingAdjusted : -$openingAdjusted)
            : $openingAdjusted;

        return [
            'account_id' => $accountId,
            'as_at' => $asAtDate,
            'fy_start' => $fyStart,
            'opening_balance' => round($openingForReturn, 2),
            'opening_balance_raw' => round($openingAdjusted, 2),
            'debit_total' => round($debitTotal, 2),
            'credit_total' => round($creditTotal, 2),
            'net_movement' => round($withSign ? ($debitNormal ? $netMovement : -$netMovement) : $netMovement, 2),
            'net_movement_raw' => round($netMovement, 2),
            'closing_balance' => round($closingBalance, 2),
            'closing_balance_raw' => round($closingRaw, 2),
            'normal_balance' => $account->accountType->normal_balance,
            'is_debit_normal' => $debitNormal,
        ];
    }

    public function getBulkBalances(
        array $accountIds,
        ?DateTimeInterface $asAt = null,
        ?int $tenantId = null,
    ): array {
        if (empty($accountIds)) {
            return [];
        }

        $asAtDate = $asAt ? $asAt->format('Y-m-d') : now()->format('Y-m-d');
        $tenantId ??= auth()->user()?->tenant_id;

        $fyStart = $this->resolveFyStart($tenantId, $asAtDate);

        $rows = DB::table('chart_of_accounts as coa')
            ->leftJoin('account_types as at', 'coa.account_type_id', '=', 'at.id')
            ->leftJoin('journal_entry_lines as jel', 'coa.id', '=', 'jel.account_id')
            ->leftJoin('journal_entries as je', function ($join) {
                $join->on('jel.journal_entry_id', '=', 'je.id')
                    ->where('je.status', 'posted');
            })
            ->whereIn('coa.id', $accountIds)
            ->where('coa.tenant_id', $tenantId)
            ->groupBy([
                'coa.id',
                'coa.code',
                'coa.name',
                'coa.opening_balance',
                'at.normal_balance',
            ])
            ->select([
                'coa.id as account_id',
                'coa.code',
                'coa.name',
                'coa.opening_balance',
                'at.normal_balance',
                DB::raw(sprintf(
                    'COALESCE(SUM(CASE WHEN je.entry_date < \'%s\' THEN jel.debit ELSE 0 END), 0) as prior_debit',
                    $fyStart,
                )),
                DB::raw(sprintf(
                    'COALESCE(SUM(CASE WHEN je.entry_date < \'%s\' THEN jel.credit ELSE 0 END), 0) as prior_credit',
                    $fyStart,
                )),
                DB::raw(sprintf(
                    'COALESCE(SUM(CASE WHEN je.entry_date >= \'%s\' AND je.entry_date <= \'%s\' THEN jel.debit ELSE 0 END), 0) as period_debit',
                    $fyStart,
                    $asAtDate,
                )),
                DB::raw(sprintf(
                    'COALESCE(SUM(CASE WHEN je.entry_date >= \'%s\' AND je.entry_date <= \'%s\' THEN jel.credit ELSE 0 END), 0) as period_credit',
                    $fyStart,
                    $asAtDate,
                )),
            ])
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $debitNormal = $row->normal_balance === 'debit';
            $priorNet = (float) $row->prior_debit - (float) $row->prior_credit;
            $openingAdjusted = (float) $row->opening_balance + $priorNet;
            $debitTotal = (float) $row->period_debit;
            $creditTotal = (float) $row->period_credit;
            $netMovement = $debitTotal - $creditTotal;
            $closingRaw = $openingAdjusted + $netMovement;

            $result[$row->account_id] = [
                'account_id' => $row->account_id,
                'code' => $row->code,
                'name' => $row->name,
                'as_at' => $asAtDate,
                'opening_balance' => round($debitNormal ? $openingAdjusted : -$openingAdjusted, 2),
                'opening_balance_raw' => round($openingAdjusted, 2),
                'debit_total' => round($debitTotal, 2),
                'credit_total' => round($creditTotal, 2),
                'net_movement' => round($debitNormal ? $netMovement : -$netMovement, 2),
                'net_movement_raw' => round($netMovement, 2),
                'closing_balance' => round($debitNormal ? $closingRaw : -$closingRaw, 2),
                'closing_balance_raw' => round($closingRaw, 2),
                'normal_balance' => $row->normal_balance,
                'is_debit_normal' => $debitNormal,
            ];
        }

        return $result;
    }

    protected function resolveFyStart(?int $tenantId, string $asAtDate): string
    {
        if ($tenantId === null) {
            return now()->startOfYear()->format('Y-m-d');
        }

        $fy = FiscalYear::where('tenant_id', $tenantId)
            ->whereDate('start_date', '<=', $asAtDate)
            ->whereDate('end_date', '>=', $asAtDate)
            ->first();

        if ($fy) {
            return $fy->start_date->format('Y-m-d');
        }

        $latestFy = FiscalYear::where('tenant_id', $tenantId)
            ->whereDate('start_date', '<=', $asAtDate)
            ->orderByDesc('start_date')
            ->first();

        if ($latestFy) {
            return $latestFy->start_date->format('Y-m-d');
        }

        return now()->startOfYear()->format('Y-m-d');
    }

    public function resolvePeriodForDate(?int $tenantId, string $date): array
    {
        $fy = FiscalYear::where('tenant_id', $tenantId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();

        $period = null;
        if ($fy) {
            $period = $fy->accountingPeriods()
                ->whereDate('start_date', '<=', $date)
                ->whereDate('end_date', '>=', $date)
                ->first();
        }

        return [
            'fiscal_year_id' => $fy?->id,
            'accounting_period_id' => $period?->id,
            'is_closed' => $period?->is_closed ?? false,
            'fiscal_year' => $fy,
            'accounting_period' => $period,
        ];
    }
}

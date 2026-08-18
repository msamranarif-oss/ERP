<?php

namespace App\Services;

use App\Models\Lot;
use App\Models\Batch;
use App\Models\SerialNumber;
use Illuminate\Support\Facades\DB;

class LotService
{
    public function createLot(array $data): Lot
    {
        return Lot::create($data);
    }

    public function approveQC(int $lotId, string $notes): Lot
    {
        $lot = Lot::findOrFail($lotId);

        if ($lot->qc_status !== 'pending') {
            throw new \Exception("Lot QC has already been reviewed.");
        }

        $lot->update([
            'qc_status'      => 'passed',
            'qc_notes'       => $notes,
            'qc_reviewed_at' => now(),
            'qc_reviewed_by' => auth()->id(),
            'status'         => 'available',
        ]);

        return $lot->fresh();
    }

    public function rejectQC(int $lotId, string $notes): Lot
    {
        $lot = Lot::findOrFail($lotId);

        if ($lot->qc_status !== 'pending') {
            throw new \Exception("Lot QC has already been reviewed.");
        }

        $lot->update([
            'qc_status'      => 'rejected',
            'qc_notes'       => $notes,
            'qc_reviewed_at' => now(),
            'qc_reviewed_by' => auth()->id(),
            'status'         => 'quarantine',
        ]);

        // Quarantine all batches in this lot
        Batch::where('lot_id', $lotId)->update(['status' => 'quarantine']);

        return $lot->fresh();
    }

    /**
     * Full traceability chain: lot → batches → serials → sale_items
     */
    public function getLotTrace(int $lotId): array
    {
        $lot = Lot::with(['supplier', 'batches.product', 'batches.warehouse'])->findOrFail($lotId);

        $batchIds = $lot->batches->pluck('id');

        $serials = SerialNumber::whereIn('batch_id', $batchIds)
                               ->with(['product', 'customer'])
                               ->get();

        return [
            'lot'     => $lot,
            'batches' => $lot->batches,
            'serials' => $serials,
        ];
    }
}

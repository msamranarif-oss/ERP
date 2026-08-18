<?php

namespace App\Console\Commands;

use App\Models\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckBatchExpiry extends Command
{
    protected $signature   = 'batches:check-expiry';
    protected $description = 'Mark expired batches and log alerts for items expiring soon';

    public function handle(): int
    {
        // Mark expired batches
        $expired = Batch::active()
                        ->whereNotNull('expiry_date')
                        ->where('expiry_date', '<', now()->toDateString())
                        ->get();

        foreach ($expired as $batch) {
            $batch->update(['status' => 'expired']);
            Log::warning("Batch expired", ['batch_id' => $batch->id, 'batch_number' => $batch->batch_number, 'product_id' => $batch->product_id]);
        }

        $this->info("Marked {$expired->count()} batches as expired.");

        // Alert on batches expiring within 30 days
        $expiring = Batch::active()->expiringSoon(30)->with('product')->get();
        foreach ($expiring as $batch) {
            Log::info("Batch expiring soon", [
                'batch_id'     => $batch->id,
                'batch_number' => $batch->batch_number,
                'product'      => optional($batch->product)->name,
                'expiry_date'  => $batch->expiry_date?->toDateString(),
            ]);
        }

        $this->info("Logged alerts for {$expiring->count()} batches expiring within 30 days.");
        return 0;
    }
}

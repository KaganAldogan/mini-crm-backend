<?php

namespace App\Console\Commands;

use App\Models\Lease;
use Illuminate\Console\Command;

class CloseExpiredLeases extends Command
{
    protected $signature = 'leases:close-expired';

    protected $description = 'Bitiş tarihi geçmiş aktif sözleşmeleri kapatır ve mülkü uygunsa Aktif yapar';

    public function handle(): int
    {
        $today = now()->startOfDay();

        $leases = Lease::query()
            ->with('property')
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', $today)
            ->get();

        $count = 0;
        foreach ($leases as $lease) {
            $lease->closeAndReleaseProperty();
            $count++;
        }

        $this->info("{$count} sözleşme kapatıldı.");

        return self::SUCCESS;
    }
}

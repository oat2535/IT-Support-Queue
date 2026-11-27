<?php

namespace App\Console\Commands;

use App\Services\BmsSyncService;
use Illuminate\Console\Command;

class SyncBmsJobsCommand extends Command
{
    protected $signature = 'bms:sync-jobs';

    protected $description = 'Synchronize repair jobs from BMS (MSSQL) into PostgreSQL queue tables';

    public function handle(BmsSyncService $syncService): int
    {
        $summary = $syncService->sync();

        if ($summary['skipped_locked']) {
            $this->warn('Skipped BMS sync: another sync run holds the lock.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'BMS sync complete: round1=%d, round2=%d, deleted=%d, queues=%d',
            $summary['round1_synced'],
            $summary['round2_updated'],
            $summary['deleted_non_t'],
            $summary['queues_created'],
        ));

        return self::SUCCESS;
    }
}

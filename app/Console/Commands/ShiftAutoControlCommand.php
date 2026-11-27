<?php

namespace App\Console\Commands;

use App\Services\ShiftService;
use Illuminate\Console\Command;

class ShiftAutoControlCommand extends Command
{
    protected $signature = 'shift:auto-control {--mode=close : Execution mode (close|open)}';

    protected $description = 'Automatically close shift after 21:00 (respecting OT) or open shift at 08:00';

    public function handle(ShiftService $shiftService): int
    {
        $mode = (string) $this->option('mode');

        if (! in_array($mode, ['close', 'open'], true)) {
            $this->error("Invalid mode '{$mode}'. Expected 'close' or 'open'.");

            return self::FAILURE;
        }

        if ($mode === 'close') {
            $closed = $shiftService->autoCloseShiftIfAfterHours();
            if ($closed) {
                $this->info('Auto-closed shift.');
            }

            return self::SUCCESS;
        }

        $opened = $shiftService->autoOpenShift();
        if ($opened > 0) {
            $this->info("Auto-opened {$opened} shift record(s).");
        }

        return self::SUCCESS;
    }
}

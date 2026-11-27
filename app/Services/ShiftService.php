<?php

namespace App\Services;

use App\Models\ShiftClosure;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    /**
     * Manually close or reopen the active service shift.
     *
     * @return array{success: bool, is_closed: bool, timestamp: string}
     */
    public function toggleShiftStatus(bool $shouldClose, string $actorHostname): array
    {
        $timestamp = Carbon::now('Asia/Bangkok')->format('Y-m-d H:i:s');

        DB::transaction(function () use ($shouldClose, $actorHostname, $timestamp): void {
            if ($shouldClose) {
                ShiftClosure::create([
                    'closed_at' => $timestamp,
                    'closed_by' => $actorHostname,
                ]);
            } else {
                ShiftClosure::query()
                    ->whereNull('opened_at')
                    ->update([
                        'opened_at' => $timestamp,
                        'opened_by' => $actorHostname,
                    ]);
            }
        });

        return [
            'success' => true,
            'is_closed' => $shouldClose,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * Auto-close shift between 21:00 and 05:59 unless already closed or reopened for OT since 21:00.
     */
    public function autoCloseShiftIfAfterHours(): bool
    {
        $now = Carbon::now('Asia/Bangkok');

        if ($now->hour < 21 && $now->hour >= 6) {
            return false;
        }

        $lock = Cache::lock('shift_auto_control_lock', 30);
        if (! $lock->get()) {
            return false;
        }

        try {
            return DB::transaction(function () use ($now): bool {
                if (ShiftClosure::query()->whereNull('opened_at')->lockForUpdate()->exists()) {
                    return false;
                }

                $referenceDate = $now->hour < 6 ? $now->copy()->subDay() : $now->copy();
                $shiftStartCheck = $referenceDate->setTime(21, 0, 0)->format('Y-m-d H:i:s');

                $hasOtOverride = ShiftClosure::query()
                    ->where('opened_at', '>=', $shiftStartCheck)
                    ->exists();

                if ($hasOtOverride) {
                    return false;
                }

                ShiftClosure::create([
                    'closed_at' => $now->format('Y-m-d H:i:s'),
                    'closed_by' => 'System (Auto)',
                ]);

                return true;
            });
        } finally {
            $lock->release();
        }
    }

    /**
     * Reopen any currently closed shifts automatically at 08:00.
     */
    public function autoOpenShift(): int
    {
        $now = Carbon::now('Asia/Bangkok')->format('Y-m-d H:i:s');

        return ShiftClosure::query()
            ->whereNull('opened_at')
            ->update([
                'opened_at' => $now,
                'opened_by' => 'System (Auto)',
            ]);
    }
}

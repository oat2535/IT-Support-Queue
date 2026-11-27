<?php

namespace App\Services;

use App\Models\JobsBms;
use App\Models\QueueItem;
use App\Models\QueueStatus;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QueueWorkflowService
{
    /**
     * Complete current normal active queue items and promote the next waiting item.
     *
     * @return array{success: bool, error?: string}
     */
    public function callNext(): array
    {
        return DB::transaction(function (): array {
            $statuses = QueueStatus::query()
                ->whereIn('code', ['ACTIVE', 'WAITING', 'DONE'])
                ->lockForUpdate()
                ->get()
                ->keyBy('code');

            $activeStatus = $statuses->get('ACTIVE');
            $waitingStatus = $statuses->get('WAITING');
            $doneStatus = $statuses->get('DONE');

            if (! $activeStatus || ! $waitingStatus || ! $doneStatus) {
                return ['success' => false, 'error' => 'System statuses not defined'];
            }

            $currentActiveItems = QueueItem::query()
                ->where('status_id', $activeStatus->id)
                ->where('is_adhoc', 0)
                ->lockForUpdate()
                ->get();

            $bmsError = $this->validateBmsCompletion(
                $currentActiveItems,
                'ไม่สามารถกดเรียกคิวถัดไปได้ รบกวนปิดงานในระบบ BMS ก่อน'
            );
            if ($bmsError !== null) {
                return ['success' => false, 'error' => $bmsError];
            }

            foreach ($currentActiveItems as $item) {
                $item->status_id = $doneStatus->id;
                $item->save();
            }

            $nextItem = QueueItem::query()
                ->where('status_id', $waitingStatus->id)
                ->orderByDesc('is_urgent')
                ->orderBy('queue_number')
                ->lockForUpdate()
                ->first();

            if ($nextItem !== null) {
                $nextItem->status_id = $activeStatus->id;
                $nextItem->call_queue_date = Carbon::now('Asia/Bangkok')->format('Y-m-d H:i:s');
                $nextItem->save();
            }

            return ['success' => true];
        });
    }

    /**
     * Finish all normal active queue items without BMS check (parity with legacy finish_current_queue).
     */
    public function finishCurrentWithoutBmsCheck(): void
    {
        DB::transaction(function (): void {
            $activeStatus = QueueStatus::query()->where('code', 'ACTIVE')->lockForUpdate()->first();
            $doneStatus = QueueStatus::query()->where('code', 'DONE')->first();

            if (! $activeStatus || ! $doneStatus) {
                return;
            }

            QueueItem::query()
                ->where('status_id', $activeStatus->id)
                ->where('is_adhoc', 0)
                ->update(['status_id' => $doneStatus->id]);
        });
    }

    /**
     * Update a queue item's comment and optionally its status.
     *
     * @return array{success: bool, error?: string}
     */
    public function updateDescription(int $id, string $comment, ?int $statusId = null): array
    {
        $item = QueueItem::query()->find($id);
        if (! $item) {
            return ['success' => false, 'error' => 'Queue Item not found'];
        }

        $item->comment = $comment;

        if ($statusId !== null) {
            $status = QueueStatus::query()->find($statusId);
            if ($status !== null) {
                $item->status_id = $status->id;
            }
        }

        $item->save();

        return ['success' => true];
    }

    /**
     * Toggle urgent flag on a queue item.
     *
     * @return array{success: bool, error?: string}
     */
    public function toggleUrgent(int $id, mixed $rawUrgent): array
    {
        $item = QueueItem::query()->find($id);
        if (! $item) {
            return ['success' => false, 'error' => 'Queue Item not found'];
        }

        $isUrgent = $rawUrgent === true || $rawUrgent === 'true' || $rawUrgent === 1 || $rawUrgent === '1';
        $item->is_urgent = $isUrgent ? 1 : 0;
        $item->save();

        return ['success' => true];
    }

    /**
     * Promote a WAITING queue item into an active Ad-hoc queue if no other Ad-hoc queue is currently active.
     *
     * @return array{success: bool, error?: string}
     */
    public function insertAdhoc(int $id): array
    {
        return DB::transaction(function () use ($id): array {
            // Lock the ACTIVE QueueStatus row to serialize concurrent ad-hoc checks even when 0 active ad-hoc rows exist.
            $activeStatus = QueueStatus::query()->where('code', 'ACTIVE')->lockForUpdate()->first();
            if (! $activeStatus) {
                return ['success' => false, 'error' => 'Status ACTIVE not found'];
            }

            $hasExistingAdhoc = QueueItem::query()
                ->where('status_id', $activeStatus->id)
                ->where('is_adhoc', 1)
                ->lockForUpdate()
                ->exists();

            if ($hasExistingAdhoc) {
                return [
                    'success' => false,
                    'error' => 'มีรายการคิวแทรก (Ad-hoc) ที่กำลังดำเนินการอยู่ ไม่สามารถแทรกคิวซ้ำซ้อนได้ กรุณาจบงานคิวแทรกปัจจุบันก่อน',
                ];
            }

            $item = QueueItem::query()->with('status')->lockForUpdate()->find($id);
            if (! $item) {
                return ['success' => false, 'error' => 'Queue Item not found'];
            }

            if (in_array($item->status?->code, ['DONE', 'ACTIVE'], true)) {
                return ['success' => false, 'error' => 'ไม่สามารถแทรกคิวรายการที่เสร็จสิ้นหรือกำลังดำเนินการอยู่ได้'];
            }

            $item->status_id = $activeStatus->id;
            $item->call_queue_date = Carbon::now('Asia/Bangkok')->format('Y-m-d H:i:s');
            $item->is_adhoc = 1;
            $item->save();

            return ['success' => true];
        });
    }

    /**
     * Finish active Ad-hoc queue items after verifying BMS job completion.
     *
     * @return array{success: bool, error?: string}
     */
    public function finishAdhoc(): array
    {
        return DB::transaction(function (): array {
            $activeStatus = QueueStatus::query()->where('code', 'ACTIVE')->lockForUpdate()->first();
            $doneStatus = QueueStatus::query()->where('code', 'DONE')->first();

            if (! $activeStatus || ! $doneStatus) {
                return ['success' => false, 'error' => 'System statuses not defined'];
            }

            $adhocItems = QueueItem::query()
                ->where('status_id', $activeStatus->id)
                ->where('is_adhoc', 1)
                ->lockForUpdate()
                ->get();

            $bmsError = $this->validateBmsCompletion(
                $adhocItems,
                'ไม่สามารถปิดงานคิวแทรก (Ad-hoc) ได้ รบกวนปิดงานในระบบ BMS ก่อน'
            );
            if ($bmsError !== null) {
                return ['success' => false, 'error' => $bmsError];
            }

            foreach ($adhocItems as $item) {
                $item->status_id = $doneStatus->id;
                $item->save();
            }

            return ['success' => true];
        });
    }

    /**
     * Close a specific queue item (e.g., from Pending list) after verifying BMS job completion.
     *
     * @return array{success: bool, error?: string}
     */
    public function closeItem(int $id): array
    {
        return DB::transaction(function () use ($id): array {
            $item = QueueItem::query()->lockForUpdate()->find($id);
            if (! $item) {
                return ['success' => false, 'error' => 'Queue Item not found'];
            }

            $doneStatus = QueueStatus::query()->where('code', 'DONE')->first();
            if (! $doneStatus) {
                return ['success' => false, 'error' => 'System statuses not defined'];
            }

            $bmsError = $this->validateBmsCompletion(
                collect([$item]),
                'ไม่สามารถปิดงานได้ รบกวนปิดงานในระบบ BMS ก่อน'
            );
            if ($bmsError !== null) {
                return ['success' => false, 'error' => $bmsError];
            }

            $item->status_id = $doneStatus->id;
            $item->save();

            return ['success' => true];
        });
    }

    /**
     * Verify all linked BMS jobs in the collection have status 2 or 12.
     *
     * @param  Collection<int, QueueItem>  $items
     */
    private function validateBmsCompletion(Collection $items, string $messagePrefix): ?string
    {
        $jobNumbers = $items->pluck('linked_job_no')
            ->filter(fn ($value) => $value !== null)
            ->unique()
            ->values();

        if ($jobNumbers->isEmpty()) {
            return null;
        }

        $bmsJobs = JobsBms::query()
            ->whereIn('jobno', $jobNumbers)
            ->get()
            ->keyBy('jobno');

        foreach ($items as $item) {
            if ($item->linked_job_no === null) {
                continue;
            }

            $bmsJob = $bmsJobs->get($item->linked_job_no);
            if ($bmsJob !== null && ! $bmsJob->isCompletedInBms()) {
                return "{$messagePrefix} (BMS Status: {$bmsJob->getJobStatusDisplay()})";
            }
        }

        return null;
    }
}

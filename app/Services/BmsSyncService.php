<?php

namespace App\Services;

use App\Contracts\BmsJobSource;
use App\Models\Concerns\FormatsNaiveTimestamps;
use App\Models\JobsBms;
use App\Models\QueueItem;
use App\Models\QueueStatus;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BmsSyncService
{
    private const UPSERT_COLUMNS = [
        'catagory',
        'description',
        'dept_tech',
        'name',
        'jobdate',
        'assign_date',
        'arrive_date',
        'req_date',
        'caller',
        'sap_code',
        'aname',
        'note',
        'act_dstart',
        'act_dfin',
        'job_status',
        'return_date',
        'enterdate',
        'enterby',
        'outsource_date',
        'abb_desc',
        'descriptions',
        'difficulty',
        'job_category_type',
    ];

    public function __construct(
        private readonly BmsJobSource $bmsSource,
    ) {}

    /**
     * Synchronize BMS jobs into jobs_bms and queue_app_queueitem with overlap protection.
     *
     * @return array{round1_synced: int, round2_updated: int, deleted_non_t: int, queues_created: int, skipped_locked: bool}
     */
    public function sync(): array
    {
        $lock = Cache::lock('bms_sync_jobs_lock', 55);
        if (! $lock->get()) {
            return [
                'round1_synced' => 0,
                'round2_updated' => 0,
                'deleted_non_t' => 0,
                'queues_created' => 0,
                'skipped_locked' => true,
            ];
        }

        try {
            $round1Rows = $this->bmsSource->fetchNewActiveJobs();
            $this->upsertJobBatch($round1Rows);
            $round1Count = count($round1Rows);

            $incompleteJobNos = JobsBms::query()
                ->where(function ($q): void {
                    $q->whereNotIn('job_status', JobsBms::COMPLETED_STATUSES)
                        ->orWhereNull('job_status');
                })
                ->pluck('jobno')
                ->map(fn ($v): int => (int) $v)
                ->all();

            $round2Updated = 0;
            $deletedNonT = 0;
            if ($incompleteJobNos !== []) {
                $round2Rows = $this->bmsSource->fetchJobsByNumbers($incompleteJobNos);
                $toDeleteJobNos = [];
                $toUpsertRows = [];

                foreach ($round2Rows as $row) {
                    $jobNo = (int) ($row['jobno'] ?? 0);
                    $deptTech = $row['dept_tech'] ?? null;

                    if ($deptTech !== null && $deptTech !== '' && ! str_starts_with((string) $deptTech, 'T')) {
                        $toDeleteJobNos[] = $jobNo;
                    } else {
                        $toUpsertRows[] = $row;
                    }
                }

                if ($toDeleteJobNos !== []) {
                    DB::transaction(function () use ($toDeleteJobNos): void {
                        foreach (array_chunk($toDeleteJobNos, 100) as $chunk) {
                            QueueItem::query()->whereIn('linked_job_no', $chunk)->delete();
                            JobsBms::query()->whereIn('jobno', $chunk)->delete();
                        }
                    });
                    $deletedNonT = count($toDeleteJobNos);
                }

                if ($toUpsertRows !== []) {
                    $this->upsertJobBatch($toUpsertRows);
                    $round2Updated = count($toUpsertRows);
                }
            }

            $queuesCreated = $this->syncToQueueItems();
            $this->syncOutsourceStatuses();

            return [
                'round1_synced' => $round1Count,
                'round2_updated' => $round2Updated,
                'deleted_non_t' => $deletedNonT,
                'queues_created' => $queuesCreated,
                'skipped_locked' => false,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function upsertJobBatch(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $payload = array_map(fn (array $row): array => [
            'jobno' => (int) $row['jobno'],
            'catagory' => $row['catagory'] ?? null,
            'description' => $row['description'] ?? null,
            'dept_tech' => $row['dept_tech'] ?? null,
            'name' => $row['name'] ?? null,
            'jobdate' => FormatsNaiveTimestamps::normalizeNaiveTimestamp($row['jobdate'] ?? null),
            'assign_date' => FormatsNaiveTimestamps::normalizeNaiveTimestamp($row['assign_date'] ?? null),
            'arrive_date' => FormatsNaiveTimestamps::normalizeNaiveTimestamp($row['arrive_date'] ?? null),
            'req_date' => FormatsNaiveTimestamps::normalizeNaiveTimestamp($row['req_date'] ?? null),
            'caller' => $row['caller'] ?? null,
            'sap_code' => $row['sap_code'] ?? null,
            'aname' => $row['aname'] ?? null,
            'note' => $row['note'] ?? null,
            'act_dstart' => FormatsNaiveTimestamps::normalizeNaiveTimestamp($row['act_dstart'] ?? null),
            'act_dfin' => FormatsNaiveTimestamps::normalizeNaiveTimestamp($row['act_dfin'] ?? null),
            'job_status' => isset($row['job_status']) ? (string) $row['job_status'] : null,
            'return_date' => FormatsNaiveTimestamps::normalizeNaiveTimestamp($row['return_date'] ?? null),
            'enterdate' => FormatsNaiveTimestamps::normalizeNaiveTimestamp($row['enterdate'] ?? null),
            'enterby' => $row['enterby'] ?? null,
            'outsource_date' => FormatsNaiveTimestamps::normalizeNaiveTimestamp($row['outsource_date'] ?? null, true),
            'abb_desc' => $row['abb_desc'] ?? null,
            'descriptions' => $row['descriptions'] ?? null,
            'difficulty' => isset($row['difficulty']) ? (int) $row['difficulty'] : null,
            'job_category_type' => $row['job_category_type'] ?? null,
        ], $rows);

        foreach (array_chunk($payload, 100) as $chunk) {
            JobsBms::query()->upsert($chunk, ['jobno'], self::UPSERT_COLUMNS);
        }
    }

    private function syncToQueueItems(): int
    {
        return DB::transaction(function (): int {
            $waitingStatus = QueueStatus::query()->lockForUpdate()->find(1);
            if (! $waitingStatus) {
                $waitingStatus = QueueStatus::query()->create([
                    'id' => 1,
                    'code' => 'WAITING',
                    'name' => 'รอรับบริการ',
                    'color' => 'warning',
                ]);
                Cache::forget('queue_statuses_map_v1');
            }

            $newJobs = JobsBms::query()
                ->whereNotExists(function (Builder $sub): void {
                    $sub->selectRaw('1')
                        ->from('queue_app_queueitem')
                        ->whereColumn('queue_app_queueitem.linked_job_no', 'jobs_bms.jobno');
                })
                ->orderBy('req_date')
                ->get(['jobno', 'caller', 'descriptions', 'description', 'req_date']);

            if ($newJobs->isEmpty()) {
                return 0;
            }

            $lastItem = QueueItem::query()->orderByDesc('id')->lockForUpdate()->first(['id', 'queue_number']);
            $currentNum = 0;
            if ($lastItem !== null && str_starts_with((string) $lastItem->queue_number, 'IT-')) {
                $parts = explode('-', (string) $lastItem->queue_number);
                if (isset($parts[1]) && is_numeric($parts[1])) {
                    $currentNum = (int) $parts[1];
                }
            }

            $existingItNumbers = array_flip(
                QueueItem::query()->where('queue_number', 'like', 'IT-%')->pluck('queue_number')->all()
            );

            $now = Carbon::now('Asia/Bangkok')->format('Y-m-d H:i:s');
            $records = [];

            foreach ($newJobs as $job) {
                do {
                    $currentNum++;
                    $queueNumber = sprintf('IT-%04d', $currentNum);
                } while (isset($existingItNumbers[$queueNumber]));

                $existingItNumbers[$queueNumber] = true;

                $records[] = [
                    'queue_number' => $queueNumber,
                    'user_name' => $job->caller ?: 'Unknown',
                    'user_department' => $job->descriptions ?: 'Unknown',
                    'issue_description' => $job->description ?: '',
                    'created_at' => FormatsNaiveTimestamps::normalizeNaiveTimestamp($job->req_date ?: $now),
                    'status_id' => $waitingStatus->id,
                    'linked_job_no' => $job->jobno,
                    'is_urgent' => 0,
                    'is_adhoc' => 0,
                ];
            }

            foreach (array_chunk($records, 100) as $chunk) {
                QueueItem::query()->insert($chunk);
            }

            return count($records);
        });
    }

    private function syncOutsourceStatuses(): void
    {
        DB::transaction(function (): void {
            $status5 = QueueStatus::query()->find(5);
            if ($status5) {
                QueueItem::query()->where('status_id', 6)->update(['status_id' => $status5->id]);
                if (QueueStatus::query()->where('id', 6)->delete() > 0) {
                    Cache::forget('queue_statuses_map_v1');
                }
            }

            $status1 = QueueStatus::query()->find(1);
            $doneStatus = QueueStatus::query()->where('code', 'DONE')->first();

            if (! $status5 || ! $status1 || ! $doneStatus) {
                return;
            }

            QueueItem::query()
                ->whereIn('linked_job_no', function (Builder $sub): void {
                    $sub->select('jobno')->from('jobs_bms')->whereNotNull('outsource_date');
                })
                ->whereNotIn('status_id', [$doneStatus->id, $status5->id])
                ->update(['status_id' => $status5->id]);

            QueueItem::query()
                ->whereIn('linked_job_no', function (Builder $sub): void {
                    $sub->select('jobno')->from('jobs_bms')->whereNull('outsource_date');
                })
                ->where('status_id', $status5->id)
                ->update(['status_id' => $status1->id]);
        });
    }
}

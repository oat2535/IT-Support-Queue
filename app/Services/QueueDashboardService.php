<?php

namespace App\Services;

use App\Models\JobsBms;
use App\Models\QueueItem;
use App\Models\QueueStatus;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as ConcretePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class QueueDashboardService
{
    public const ITEMS_PER_PAGE = 5;

    /**
     * Build all view data required by the single-page IT Support Queue dashboard.
     *
     * @return array<string, mixed>
     */
    public function buildDashboardData(string $statusFilter, string $searchQuery, int $page): array
    {
        $now = Carbon::now('Asia/Bangkok');
        $monthStart = $now->copy()->startOfMonth()->format('Y-m-d H:i:s');
        $monthEnd = $now->copy()->endOfMonth()->format('Y-m-d H:i:s');
        $normalizedFilter = $this->normalizeStatusFilter($statusFilter);

        $statusesById = $this->getStatusesById();
        $statusesByCode = $statusesById->keyBy('code');

        $counts = $this->fetchStatusCounts($monthStart, $monthEnd);

        $activeStatusId = $statusesByCode->get('ACTIVE')?->id;
        [$currentQueue, $currentAdhoc] = $this->fetchActiveItems($activeStatusId, $statusesById);

        [$listQuery, $listTitle, $knownTotal] = $this->buildListQuery(
            $normalizedFilter,
            $statusesByCode,
            $counts,
            $monthStart,
            $monthEnd
        );

        $hasSearch = $searchQuery !== '';
        if ($hasSearch) {
            $this->applyMultiWordSearch($listQuery, $searchQuery);
            $knownTotal = null;
        }

        $queueList = $this->paginateList($listQuery, $page, $knownTotal);
        $this->hydrateStatuses($queueList, $statusesById);

        if ($normalizedFilter === 'waiting') {
            $this->attachWaitingRanks($queueList, $statusesByCode->get('WAITING')?->id, $hasSearch);
        }

        $this->attachBmsMetadata($queueList, $currentQueue, $currentAdhoc);

        return [
            'waiting_count' => $counts['WAITING'],
            'active_count' => $counts['ACTIVE'],
            'done_count' => $counts['DONE_MONTH'],
            'coordinating_count' => $counts['COORDINATING'],
            'waiting_parts_count' => $counts['WAITING_PARTS'],
            'current_queue' => $currentQueue,
            'current_adhoc' => $currentAdhoc,
            'queue_list' => $queueList,
            'active_filter' => $normalizedFilter,
            'list_title' => $listTitle,
            'search_query' => $searchQuery,
            'is_shift_closed' => DB::table('queue_app_shiftclosure')->whereNull('opened_at')->exists(),
        ];
    }

    /**
     * Cache the tiny QueueStatus lookup table (4 rows) to eliminate repeated joins/eager loads.
     *
     * @return Collection<int, QueueStatus>
     */
    private function getStatusesById(): Collection
    {
        $rows = Cache::remember('queue_statuses_map_v1', 300, function (): array {
            return QueueStatus::query()
                ->orderBy('id')
                ->get(['id', 'name', 'code', 'color'])
                ->map(fn (QueueStatus $s): array => $s->toArray())
                ->all();
        });

        return collect($rows)
            ->map(function (array $row): QueueStatus {
                $model = new QueueStatus;
                $model->setRawAttributes($row, true);
                $model->exists = true;

                return $model;
            })
            ->keyBy('id');
    }

    /**
     * Fetch both normal active and ad-hoc active items in one query.
     *
     * @param  Collection<int, QueueStatus>  $statusesById
     * @return array{0: ?QueueItem, 1: ?QueueItem}
     */
    private function fetchActiveItems(?int $activeStatusId, Collection $statusesById): array
    {
        if ($activeStatusId === null) {
            return [null, null];
        }

        $activeItems = QueueItem::query()
            ->where('status_id', $activeStatusId)
            ->orderBy('created_at')
            ->get();

        $this->hydrateStatuses($activeItems, $statusesById);

        $currentQueue = $activeItems->first(fn (QueueItem $item): bool => (int) $item->is_adhoc === 0);
        $currentAdhoc = $activeItems->first(fn (QueueItem $item): bool => (int) $item->is_adhoc === 1);

        return [$currentQueue, $currentAdhoc];
    }

    /**
     * Fetch all status KPI counters in a single aggregated query.
     *
     * @return array{WAITING: int, ACTIVE: int, DONE_MONTH: int, COORDINATING: int, WAITING_PARTS: int}
     */
    private function fetchStatusCounts(string $monthStart, string $monthEnd): array
    {
        $row = QueueItem::query()
            ->join('queue_app_queuestatus as qs', 'qs.id', '=', 'queue_app_queueitem.status_id')
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN qs.code = 'WAITING' THEN 1 ELSE 0 END), 0) as waiting_count,
                 COALESCE(SUM(CASE WHEN qs.code = 'ACTIVE' THEN 1 ELSE 0 END), 0) as active_count,
                 COALESCE(SUM(CASE WHEN qs.code = 'DONE' AND queue_app_queueitem.created_at BETWEEN ? AND ? THEN 1 ELSE 0 END), 0) as done_count,
                 COALESCE(SUM(CASE WHEN qs.code = 'COORDINATING' THEN 1 ELSE 0 END), 0) as coordinating_count,
                 COALESCE(SUM(CASE WHEN qs.code = 'WAITING_PARTS' THEN 1 ELSE 0 END), 0) as waiting_parts_count",
                [$monthStart, $monthEnd]
            )
            ->first();

        return [
            'WAITING' => (int) ($row?->waiting_count ?? 0),
            'ACTIVE' => (int) ($row?->active_count ?? 0),
            'DONE_MONTH' => (int) ($row?->done_count ?? 0),
            'COORDINATING' => (int) ($row?->coordinating_count ?? 0),
            'WAITING_PARTS' => (int) ($row?->waiting_parts_count ?? 0),
        ];
    }

    private function normalizeStatusFilter(string $statusFilter): string
    {
        return in_array($statusFilter, ['waiting', 'active', 'done', 'pending'], true)
            ? $statusFilter
            : 'waiting';
    }

    /**
     * @param  Collection<string, QueueStatus>  $statusesByCode
     * @param  array{WAITING: int, ACTIVE: int, DONE_MONTH: int, COORDINATING: int, WAITING_PARTS: int}  $counts
     * @return array{0: Builder, 1: string, 2: ?int}
     */
    private function buildListQuery(
        string $filter,
        Collection $statusesByCode,
        array $counts,
        string $monthStart,
        string $monthEnd
    ): array {
        $query = QueueItem::query();

        return match ($filter) {
            'active' => [
                $query->where('status_id', $statusesByCode->get('ACTIVE')?->id ?? -1)
                    ->orderBy('created_at'),
                'รายการที่กำลังดำเนินการ (Active)',
                $counts['ACTIVE'],
            ],
            'done' => [
                $query->where('status_id', $statusesByCode->get('DONE')?->id ?? -1)
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->orderBy('created_at'),
                'รายการที่เสร็จสิ้น (Done)',
                $counts['DONE_MONTH'],
            ],
            'pending' => [
                $query->whereIn('status_id', array_values(array_filter([
                    $statusesByCode->get('COORDINATING')?->id,
                    $statusesByCode->get('WAITING_PARTS')?->id,
                ])) ?: [-1])
                    ->orderBy('created_at'),
                'รายการที่อยู่ระหว่างประสานงานและรออะไหล่',
                $counts['COORDINATING'] + $counts['WAITING_PARTS'],
            ],
            default => [
                $query->where('status_id', $statusesByCode->get('WAITING')?->id ?? -1)
                    ->orderByDesc('is_urgent')
                    ->orderBy('queue_number'),
                'รายการที่รอคิว (Waiting)',
                $counts['WAITING'],
            ],
        };
    }

    private function applyMultiWordSearch(Builder $query, string $searchQuery): void
    {
        $words = preg_split('/\s+/', trim($searchQuery)) ?: [];
        $likeOperator = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $word);
            $pattern = "%{$escaped}%";
            $query->where(function (Builder $sub) use ($likeOperator, $pattern): void {
                $sub->where('issue_description', $likeOperator, $pattern)
                    ->orWhere('user_name', $likeOperator, $pattern)
                    ->orWhere('queue_number', $likeOperator, $pattern);
            });
        }
    }

    private function paginateList(Builder $query, int $page, ?int $knownTotal): LengthAwarePaginator
    {
        $safePage = max(1, $page);

        if ($knownTotal !== null) {
            $lastPage = max(1, (int) ceil($knownTotal / self::ITEMS_PER_PAGE));
            $clampedPage = min($safePage, $lastPage);
            $items = $knownTotal === 0
                ? collect()
                : $query->forPage($clampedPage, self::ITEMS_PER_PAGE)->get();

            return (new ConcretePaginator(
                $items,
                $knownTotal,
                self::ITEMS_PER_PAGE,
                $clampedPage,
                ['path' => ConcretePaginator::resolveCurrentPath()]
            ))->withQueryString();
        }

        $paginator = $query->paginate(self::ITEMS_PER_PAGE, ['*'], 'page', $safePage);

        if ($safePage > $paginator->lastPage() && $paginator->lastPage() >= 1) {
            $paginator = $query->paginate(self::ITEMS_PER_PAGE, ['*'], 'page', $paginator->lastPage());
        }

        return $paginator->withQueryString();
    }

    /**
     * @param  iterable<int, QueueItem>  $items
     * @param  Collection<int, QueueStatus>  $statusesById
     */
    private function hydrateStatuses(iterable $items, Collection $statusesById): void
    {
        foreach ($items as $item) {
            $item->setRelation('status', $statusesById->get((int) $item->status_id));
        }
    }

    private function attachWaitingRanks(LengthAwarePaginator $queueList, ?int $waitingStatusId, bool $hasSearch): void
    {
        if (! $hasSearch) {
            $offset = ($queueList->currentPage() - 1) * self::ITEMS_PER_PAGE;
            foreach ($queueList->items() as $index => $item) {
                $item->setAttribute('waiting_rank', $offset + $index + 1);
            }

            return;
        }

        if ($waitingStatusId === null || $queueList->isEmpty()) {
            return;
        }

        $ids = collect($queueList->items())->pluck('id')->all();
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));

        $rankedRows = DB::select(
            "SELECT ranked.id, ranked.waiting_rank FROM (
                SELECT id, ROW_NUMBER() OVER (ORDER BY is_urgent DESC, queue_number ASC) AS waiting_rank
                FROM queue_app_queueitem
                WHERE status_id = ?
            ) AS ranked WHERE ranked.id IN ({$placeholders})",
            array_merge([$waitingStatusId], $ids)
        );

        $rankMap = [];
        foreach ($rankedRows as $row) {
            $rankMap[(int) $row->id] = (int) $row->waiting_rank;
        }

        foreach ($queueList as $item) {
            $item->setAttribute('waiting_rank', $rankMap[(int) $item->id] ?? null);
        }
    }

    private function attachBmsMetadata(
        LengthAwarePaginator $queueList,
        ?QueueItem $currentQueue,
        ?QueueItem $currentAdhoc
    ): void {
        /** @var Collection<int, QueueItem> $items */
        $items = collect($queueList->items());
        if ($currentQueue !== null) {
            $items->push($currentQueue);
        }
        if ($currentAdhoc !== null) {
            $items->push($currentAdhoc);
        }

        $jobNumbers = $items->pluck('linked_job_no')
            ->filter(fn ($value) => $value !== null)
            ->unique()
            ->values();

        $bmsByJobNo = $jobNumbers->isEmpty()
            ? collect()
            : JobsBms::query()->whereIn('jobno', $jobNumbers)->get(['jobno', 'name', 'note'])->keyBy('jobno');

        foreach ($items as $item) {
            $bms = $item->linked_job_no !== null ? $bmsByJobNo->get($item->linked_job_no) : null;
            $item->setAttribute('operator_name', $bms?->name);
            $item->setAttribute('bms_note', $bms?->note ?? '');
        }
    }
}

<?php

namespace Tests\Feature;

use App\Contracts\BmsJobSource;
use App\Models\JobsBms;
use App\Models\QueueItem;
use App\Models\QueueStatus;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BmsSyncParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        QueueStatus::create(['id' => 1, 'name' => 'รอรับบริการ', 'code' => 'WAITING', 'color' => 'warning']);
        QueueStatus::create(['id' => 2, 'name' => 'กำลังดำเนินการ', 'code' => 'ACTIVE', 'color' => 'info']);
        QueueStatus::create(['id' => 3, 'name' => 'เสร็จสิ้น', 'code' => 'DONE', 'color' => 'success']);
        QueueStatus::create(['id' => 5, 'name' => 'รอประสานงาน', 'code' => 'COORDINATING', 'color' => 'primary']);
        QueueStatus::create(['id' => 6, 'name' => 'รออะไหล่', 'code' => 'WAITING_PARTS', 'color' => 'primary']);
    }

    public function test_bms_sync_imports_new_jobs_generates_sequential_queue_numbers_and_handles_outsource_and_non_t_cleanup(): void
    {
        // Existing local incomplete jobs for Round 2:
        // 500 -> dept_tech switches to 'M01' (non-T) => must delete QueueItem + JobsBms
        // 501 -> dept_tech is null => must NOT be deleted
        // 502 -> gets outsource_date => must move WAITING (1) -> COORDINATING (5)
        JobsBms::create(['jobno' => 500, 'job_status' => '1', 'dept_tech' => 'T01', 'req_date' => '2026-11-27 08:00:00']);
        JobsBms::create(['jobno' => 501, 'job_status' => '1', 'dept_tech' => null, 'req_date' => '2026-11-27 08:05:00']);
        JobsBms::create(['jobno' => 502, 'job_status' => '1', 'dept_tech' => 'T02', 'req_date' => '2026-11-27 08:10:00']);

        QueueItem::create([
            'queue_number' => 'IT-0009',
            'user_name' => 'Old 500',
            'user_department' => 'OPD',
            'issue_description' => 'Transferred out',
            'created_at' => '2026-11-27 08:00:00',
            'linked_job_no' => 500,
            'status_id' => 1,
        ]);
        QueueItem::create([
            'queue_number' => 'IT-0010',
            'user_name' => 'Keep 501',
            'user_department' => 'OPD',
            'issue_description' => 'Unassigned tech',
            'created_at' => '2026-11-27 08:05:00',
            'linked_job_no' => 501,
            'status_id' => 6, // legacy status 6 -> migrated to 5, then reverted to 1 since outsource_date is null
        ]);
        QueueItem::create([
            'queue_number' => 'IT-0011',
            'user_name' => 'Outsource 502',
            'user_department' => 'ICU',
            'issue_description' => 'Sent to vendor',
            'created_at' => '2026-11-27 08:10:00',
            'linked_job_no' => 502,
            'status_id' => 1,
        ]);

        $this->app->instance(BmsJobSource::class, new class implements BmsJobSource
        {
            public function fetchNewActiveJobs(): array
            {
                return [
                    [
                        'jobno' => 601,
                        'catagory' => 'IT',
                        'description' => 'Server down',
                        'dept_tech' => 'T01',
                        'name' => 'Tech A',
                        'req_date' => '2026-11-27 09:00:00.123456',
                        'caller' => 'Somchai',
                        'descriptions' => 'ER',
                        'job_status' => '11',
                        'outsource_date' => null,
                        'difficulty' => 5,
                        'job_category_type' => 'SERVER',
                    ],
                ];
            }

            public function fetchJobsByNumbers(array $jobNumbers): array
            {
                return [
                    [
                        'jobno' => 500,
                        'dept_tech' => 'M01',
                        'job_status' => '1',
                    ],
                    [
                        'jobno' => 501,
                        'dept_tech' => null,
                        'job_status' => '1',
                        'outsource_date' => null,
                    ],
                    [
                        'jobno' => 502,
                        'dept_tech' => 'T02',
                        'job_status' => '7',
                        'outsource_date' => '2026-11-27 12:34:56.789',
                    ],
                ];
            }
        });

        $this->artisan('bms:sync-jobs')->assertSuccessful();

        // 500 deleted because dept_tech = 'M01' (does not start with 'T')
        $this->assertDatabaseMissing('jobs_bms', ['jobno' => 500]);
        $this->assertDatabaseMissing('queue_app_queueitem', ['linked_job_no' => 500]);

        // 501 kept (null dept_tech) and status 6 deleted + migrated back to 1 (no outsource_date)
        $this->assertDatabaseMissing('queue_app_queuestatus', ['id' => 6]);
        $this->assertDatabaseHas('queue_app_queueitem', ['linked_job_no' => 501, 'status_id' => 1]);

        // 502 moved to COORDINATING (5) and outsource_date seconds zeroed
        $this->assertDatabaseHas('jobs_bms', ['jobno' => 502, 'outsource_date' => '2026-11-27 12:34:00']);
        $this->assertDatabaseHas('queue_app_queueitem', ['linked_job_no' => 502, 'status_id' => 5]);

        // 601 created as IT-0012 (next after IT-0011) with microseconds stripped
        $this->assertDatabaseHas('queue_app_queueitem', [
            'linked_job_no' => 601,
            'queue_number' => 'IT-0012',
            'user_name' => 'Somchai',
            'user_department' => 'ER',
            'created_at' => '2026-11-27 09:00:00',
            'status_id' => 1,
        ]);
    }

    public function test_bms_sync_skips_when_lock_is_already_held_and_scheduler_registers_jobs(): void
    {
        $lock = Cache::lock('bms_sync_jobs_lock', 55);
        $this->assertTrue($lock->get());

        try {
            $this->app->instance(BmsJobSource::class, new class implements BmsJobSource
            {
                public function fetchNewActiveJobs(): array
                {
                    throw new \RuntimeException('Should not query while locked');
                }

                public function fetchJobsByNumbers(array $jobNumbers): array
                {
                    return [];
                }
            });

            $this->artisan('bms:sync-jobs')->assertSuccessful();
        } finally {
            $lock->release();
        }

        $events = collect(app(Schedule::class)->events())->map(fn ($e) => $e->command);
        $this->assertTrue($events->contains(fn (string $cmd) => str_contains($cmd, 'bms:sync-jobs')));
        $this->assertTrue($events->contains(fn (string $cmd) => str_contains($cmd, 'shift:auto-control --mode=close')));
        $this->assertTrue($events->contains(fn (string $cmd) => str_contains($cmd, 'shift:auto-control --mode=open')));
    }
}

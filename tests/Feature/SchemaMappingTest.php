<?php

namespace Tests\Feature;

use App\Models\JobsBms;
use App\Models\Member;
use App\Models\QueueItem;
use App\Models\QueueStatus;
use App\Models\ShiftClosure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_models_persist_legacy_schema_and_truncate_microseconds(): void
    {
        $waiting = QueueStatus::create([
            'id' => 1,
            'name' => 'รอรับบริการ',
            'code' => 'WAITING',
            'color' => 'warning',
        ]);

        $bmsJob = JobsBms::create([
            'jobno' => 1001,
            'catagory' => 'IT',
            'description' => 'Printer offline',
            'dept_tech' => 'T01',
            'name' => 'Tech A',
            'req_date' => '2026-11-27 10:15:30.654321',
            'outsource_date' => '2026-11-27 14:22:49.123456',
            'job_status' => '1',
            'descriptions' => 'OPD',
        ]);

        $item = QueueItem::create([
            'queue_number' => 'IT-0001',
            'user_name' => 'Somchai',
            'user_department' => 'OPD',
            'issue_description' => 'Printer offline',
            'created_at' => '2026-11-27 10:15:30.999999',
            'call_queue_date' => '2026-11-27 10:20:05.555555',
            'linked_job_no' => 1001,
            'status_id' => $waiting->id,
            'is_urgent' => 0,
            'is_adhoc' => 0,
        ]);

        $closure = ShiftClosure::create([
            'closed_at' => '2026-11-27 21:00:05.123456',
            'closed_by' => 'IT-PC01',
        ]);

        $member = Member::create([
            'username' => 'staff01',
            'password' => 'pbkdf2_sha256$600000$salt$hash',
            'first_name' => 'Somying',
            'last_name' => 'Jaidee',
            'is_staff' => 1,
        ]);

        $this->assertSame('2026-11-27 10:15:30', $item->fresh()->created_at);
        $this->assertSame('2026-11-27 10:20:05', $item->fresh()->call_queue_date);
        $this->assertSame('2026-11-27 14:22:00', $bmsJob->fresh()->outsource_date);
        $this->assertSame('2026-11-27 21:00:05', $closure->fresh()->closed_at);
        $this->assertSame('WAITING', $item->status->code);
        $this->assertSame(1001, $item->bmsJob->jobno);
        $this->assertSame('กำลังดำเนินการ', $bmsJob->getJobStatusDisplay());
        $this->assertSame('Unknown (999)', (new JobsBms(['job_status' => '999']))->getJobStatusDisplay());
        $this->assertSame('Somying Jaidee', $member->fullName());
    }
}

<?php

namespace Tests\Feature;

use App\Models\JobsBms;
use App\Models\QueueItem;
use App\Models\QueueStatus;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueOperationsParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 11, 27, 15, 30, 0, 'Asia/Bangkok'));

        QueueStatus::create(['id' => 1, 'name' => 'รอรับบริการ', 'code' => 'WAITING', 'color' => 'warning']);
        QueueStatus::create(['id' => 2, 'name' => 'กำลังดำเนินการ', 'code' => 'ACTIVE', 'color' => 'info']);
        QueueStatus::create(['id' => 3, 'name' => 'เสร็จสิ้น', 'code' => 'DONE', 'color' => 'success']);
        QueueStatus::create(['id' => 5, 'name' => 'รอประสานงาน', 'code' => 'COORDINATING', 'color' => 'primary']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function asStaff(): self
    {
        return $this->withSession(['member_id' => 1, 'is_staff' => 1]);
    }

    public function test_mutation_endpoints_require_staff_session_and_post_method(): void
    {
        $this->get('/call-next/')->assertStatus(405);
        $this->get('/finish-adhoc/')->assertStatus(405);
        $this->postJson('/call-next/')->assertStatus(403);
        $this->postJson('/finish-adhoc/')->assertStatus(403);
        $this->postJson('/update-job-desc/', ['id' => 1, 'comment' => 'x'])->assertStatus(403);
        $this->postJson('/toggle-urgent/', ['id' => 1, 'is_urgent' => 1])->assertStatus(403);
        $this->postJson('/insert-queue/', ['id' => 1])->assertStatus(403);
        $this->postJson('/close-queue/', ['id' => 1])->assertStatus(403);
    }

    public function test_call_next_blocks_until_bms_job_is_closed_then_promotes_urgent_first(): void
    {
        JobsBms::create(['jobno' => 101, 'job_status' => '1']);

        $active = QueueItem::create([
            'queue_number' => 'IT-0001',
            'user_name' => 'Active User',
            'user_department' => 'OPD',
            'issue_description' => 'Active Issue',
            'created_at' => '2026-11-27 09:00:00',
            'linked_job_no' => 101,
            'status_id' => 2,
            'is_adhoc' => 0,
        ]);

        $waitNormal = QueueItem::create([
            'queue_number' => 'IT-0002',
            'user_name' => 'Normal',
            'user_department' => 'Lab',
            'issue_description' => 'Task 2',
            'created_at' => '2026-11-27 09:05:00',
            'status_id' => 1,
            'is_urgent' => 0,
        ]);

        $waitUrgent = QueueItem::create([
            'queue_number' => 'IT-0003',
            'user_name' => 'Urgent',
            'user_department' => 'ICU',
            'issue_description' => 'Task 3',
            'created_at' => '2026-11-27 09:10:00',
            'status_id' => 1,
            'is_urgent' => 1,
        ]);

        $blocked = $this->asStaff()->postJson('/call-next/');
        $blocked->assertOk()->assertJson([
            'success' => false,
            'error' => 'ไม่สามารถกดเรียกคิวถัดไปได้ รบกวนปิดงานในระบบ BMS ก่อน (BMS Status: กำลังดำเนินการ)',
        ]);
        $this->assertSame(2, $active->fresh()->status_id);

        JobsBms::where('jobno', 101)->update(['job_status' => '2']);

        $ok = $this->asStaff()->postJson('/call-next/');
        $ok->assertOk()->assertJson(['success' => true]);

        $this->assertSame(3, $active->fresh()->status_id);
        $this->assertSame(2, $waitUrgent->fresh()->status_id);
        $this->assertSame('2026-11-27 15:30:00', $waitUrgent->fresh()->call_queue_date);
        $this->assertSame(1, $waitNormal->fresh()->status_id);
    }

    public function test_adhoc_insert_prevents_duplicates_and_done_items_and_finish_adhoc_validates_bms(): void
    {
        JobsBms::create(['jobno' => 201, 'job_status' => '11']);

        $first = QueueItem::create([
            'queue_number' => 'IT-0010',
            'user_name' => 'User 10',
            'user_department' => 'ER',
            'issue_description' => 'Urgent Adhoc',
            'created_at' => '2026-11-27 10:00:00',
            'linked_job_no' => 201,
            'status_id' => 1,
        ]);

        $second = QueueItem::create([
            'queue_number' => 'IT-0011',
            'user_name' => 'User 11',
            'user_department' => 'OPD',
            'issue_description' => 'Second Adhoc',
            'created_at' => '2026-11-27 10:05:00',
            'status_id' => 1,
        ]);

        $doneItem = QueueItem::create([
            'queue_number' => 'IT-0012',
            'user_name' => 'Done User',
            'user_department' => 'OPD',
            'issue_description' => 'Already done',
            'created_at' => '2026-11-27 10:06:00',
            'status_id' => 3,
        ]);

        $this->asStaff()->postJson('/insert-queue/', ['id' => $doneItem->id])
            ->assertOk()
            ->assertJson(['success' => false]);

        $this->asStaff()->postJson('/insert-queue/', ['id' => $first->id])
            ->assertOk()
            ->assertJson(['success' => true]);
        $this->assertSame(2, $first->fresh()->status_id);
        $this->assertSame(1, $first->fresh()->is_adhoc);

        $this->asStaff()->postJson('/insert-queue/', ['id' => $second->id])
            ->assertOk()
            ->assertJson([
                'success' => false,
                'error' => 'มีรายการคิวแทรก (Ad-hoc) ที่กำลังดำเนินการอยู่ ไม่สามารถแทรกคิวซ้ำซ้อนได้ กรุณาจบงานคิวแทรกปัจจุบันก่อน',
            ]);

        $this->asStaff()->postJson('/finish-adhoc/')
            ->assertOk()
            ->assertJson([
                'success' => false,
                'error' => 'ไม่สามารถปิดงานคิวแทรก (Ad-hoc) ได้ รบกวนปิดงานในระบบ BMS ก่อน (BMS Status: รอจ่ายงาน)',
            ]);

        JobsBms::where('jobno', 201)->update(['job_status' => '12']);

        $this->asStaff()->postJson('/finish-adhoc/')
            ->assertOk()
            ->assertJson(['success' => true]);
        $this->assertSame(3, $first->fresh()->status_id);
    }

    public function test_update_description_toggle_urgent_and_close_pending_item(): void
    {
        JobsBms::create(['jobno' => 301, 'job_status' => '7']);

        $item = QueueItem::create([
            'queue_number' => 'IT-0020',
            'user_name' => 'Coord User',
            'user_department' => 'Finance',
            'issue_description' => 'Hardware repair',
            'created_at' => '2026-11-27 11:00:00',
            'linked_job_no' => 301,
            'status_id' => 5,
            'is_urgent' => 0,
        ]);

        $this->asStaff()->postJson('/update-job-desc/', [
            'id' => $item->id,
            'comment' => 'Waiting for vendor part',
        ])->assertOk()->assertJson(['success' => true]);
        $this->assertSame('Waiting for vendor part', $item->fresh()->comment);

        $this->asStaff()->postJson('/toggle-urgent/', [
            'id' => $item->id,
            'is_urgent' => 1,
        ])->assertOk()->assertJson(['success' => true]);
        $this->assertSame(1, $item->fresh()->is_urgent);

        $this->asStaff()->postJson('/close-queue/', ['id' => $item->id])
            ->assertOk()
            ->assertJson([
                'success' => false,
                'error' => 'ไม่สามารถปิดงานได้ รบกวนปิดงานในระบบ BMS ก่อน (BMS Status: ส่งซ่อมภายนอก)',
            ]);

        JobsBms::where('jobno', 301)->update(['job_status' => '2']);
        $this->asStaff()->postJson('/close-queue/', ['id' => $item->id])
            ->assertOk()
            ->assertJson(['success' => true]);
        $this->assertSame(3, $item->fresh()->status_id);
    }
}

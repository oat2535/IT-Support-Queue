<?php

namespace Tests\Feature;

use App\Contracts\HostnameResolver;
use App\Models\JobsBms;
use App\Models\QueueItem;
use App\Models\QueueStatus;
use App\Models\ShiftClosure;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class DashboardReadParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 11, 27, 14, 0, 0, 'Asia/Bangkok'));

        $this->app->instance(HostnameResolver::class, new class implements HostnameResolver
        {
            public function resolveClientIp(Request $request): string
            {
                return '192.168.10.50';
            }

            public function resolveHostname(string $ipAddress): string
            {
                return 'IT-DESK-01';
            }
        });

        QueueStatus::create(['id' => 1, 'name' => 'รอรับบริการ', 'code' => 'WAITING', 'color' => 'warning']);
        QueueStatus::create(['id' => 2, 'name' => 'กำลังดำเนินการ', 'code' => 'ACTIVE', 'color' => 'info']);
        QueueStatus::create(['id' => 3, 'name' => 'เสร็จสิ้น', 'code' => 'DONE', 'color' => 'success']);
        QueueStatus::create(['id' => 5, 'name' => 'รอประสานงาน', 'code' => 'COORDINATING', 'color' => 'primary']);
        QueueStatus::create(['id' => 6, 'name' => 'รออะไหล่', 'code' => 'WAITING_PARTS', 'color' => 'primary']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_renders_sb_admin_pro_kpis_active_cards_ranks_and_csp_headers(): void
    {
        JobsBms::create(['jobno' => 501, 'name' => 'Tech Somchai', 'note' => 'Replaced cable']);
        JobsBms::create(['jobno' => 502, 'name' => 'Tech Mali', 'note' => 'VIP fix']);

        QueueItem::create([
            'queue_number' => 'IT-0001',
            'user_name' => 'Active User',
            'user_department' => 'OPD',
            'issue_description' => 'POS frozen',
            'created_at' => '2026-11-27 09:00:00',
            'linked_job_no' => 501,
            'status_id' => 2,
            'is_adhoc' => 0,
        ]);

        QueueItem::create([
            'queue_number' => 'IT-0002',
            'user_name' => 'Adhoc User',
            'user_department' => 'ER',
            'issue_description' => 'Emergency scanner',
            'created_at' => '2026-11-27 09:10:00',
            'linked_job_no' => 502,
            'status_id' => 2,
            'is_adhoc' => 1,
        ]);

        // Waiting items: IT-0004 is urgent so it must rank #1 ahead of IT-0003
        QueueItem::create([
            'queue_number' => 'IT-0003',
            'user_name' => 'Normal Waiter',
            'user_department' => 'Lab',
            'issue_description' => 'Printer jam',
            'created_at' => '2026-11-27 09:20:00',
            'status_id' => 1,
            'is_urgent' => 0,
        ]);

        QueueItem::create([
            'queue_number' => 'IT-0004',
            'user_name' => 'Urgent Waiter',
            'user_department' => 'ICU',
            'issue_description' => 'Network down',
            'created_at' => '2026-11-27 09:30:00',
            'status_id' => 1,
            'is_urgent' => 1,
        ]);

        // Current month DONE vs last month DONE
        QueueItem::create([
            'queue_number' => 'IT-0005',
            'user_name' => 'Done Nov',
            'user_department' => 'Ward',
            'issue_description' => 'Mouse replacement',
            'created_at' => '2026-11-10 10:00:00',
            'status_id' => 3,
        ]);
        QueueItem::create([
            'queue_number' => 'IT-0006',
            'user_name' => 'Done Oct',
            'user_department' => 'Ward',
            'issue_description' => 'Old issue',
            'created_at' => '2026-10-15 10:00:00',
            'status_id' => 3,
        ]);

        // Pending (Coordinating + Waiting Parts)
        QueueItem::create([
            'queue_number' => 'IT-0007',
            'user_name' => 'Coord User',
            'user_department' => 'Admin',
            'issue_description' => 'Vendor call',
            'created_at' => '2026-11-27 10:00:00',
            'status_id' => 5,
        ]);
        QueueItem::create([
            'queue_number' => 'IT-0008',
            'user_name' => 'Parts User',
            'user_department' => 'Finance',
            'issue_description' => 'Need SSD',
            'created_at' => '2026-11-27 10:05:00',
            'status_id' => 6,
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader(
            'Content-Security-Policy',
            "frame-ancestors 'self' http://imedx-uat.thonglorpet.com:9444 https://imedx-uat.thonglorpet.com:9444"
        );
        $this->assertNull($response->headers->get('X-Frame-Options'));

        $response->assertViewHas('waiting_count', 2);
        $response->assertViewHas('done_count', 1);
        $response->assertViewHas('coordinating_count', 1);
        $response->assertViewHas('waiting_parts_count', 1);

        $response->assertSee('IT Smart Queue');
        $response->assertSee('Tech Somchai');
        $response->assertSee('Tech Mali');
        $response->assertSee('IT-DESK-01');
        $response->assertSee('192.168.10.50');

        $queueList = $response->viewData('queue_list');
        $this->assertSame('IT-0004', $queueList[0]->queue_number);
        $this->assertSame(1, $queueList[0]->waiting_rank);
        $this->assertSame('IT-0003', $queueList[1]->queue_number);
        $this->assertSame(2, $queueList[1]->waiting_rank);
    }

    public function test_dashboard_multi_word_search_pagination_and_shift_closed_flag(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            QueueItem::create([
                'queue_number' => sprintf('IT-%04d', $i),
                'user_name' => "Requester {$i}",
                'user_department' => 'OPD',
                'issue_description' => $i === 6 ? 'Urgent Zebra Printer' : "General Task {$i}",
                'created_at' => '2026-11-27 08:00:00',
                'status_id' => 1,
            ]);
        }

        ShiftClosure::create([
            'closed_at' => '2026-11-26 21:00:00',
            'closed_by' => 'System (Auto)',
            'opened_at' => null,
        ]);

        // Page 2 of 6 waiting items (5 per page -> 1 item on page 2)
        $page2 = $this->get('/?status=waiting&page=2');
        $page2->assertOk();
        $page2->assertViewHas('is_shift_closed', true);
        $this->assertCount(1, $page2->viewData('queue_list'));

        // Out-of-bounds page clamps to last page (2)
        $outOfRange = $this->get('/?status=waiting&page=99');
        $outOfRange->assertOk();
        $this->assertSame(2, $outOfRange->viewData('queue_list')->currentPage());

        // Multi-word AND search across fields
        $search = $this->get('/?status=waiting&q=Zebra+Requester');
        $search->assertOk();
        $results = $search->viewData('queue_list');
        $this->assertCount(1, $results);
        $this->assertSame('IT-0006', $results[0]->queue_number);
        $this->assertSame(6, $results[0]->waiting_rank);
    }
}

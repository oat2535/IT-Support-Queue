<?php

namespace Tests\Feature;

use App\Contracts\HostnameResolver;
use App\Models\Member;
use App\Models\ShiftClosure;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuthAndShiftParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(HostnameResolver::class, new class implements HostnameResolver
        {
            public function resolveClientIp(Request $request): string
            {
                return '192.168.10.25';
            }

            public function resolveHostname(string $ipAddress): string
            {
                return 'ADMIN-PC01';
            }
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeDjangoPbkdf2Hash(string $plain, string $salt = 'staticSalt123', int $iterations = 600000): string
    {
        $raw = hash_pbkdf2('sha256', $plain, $salt, $iterations, 0, true);

        return "pbkdf2_sha256\${$iterations}\${$salt}\$".base64_encode($raw);
    }

    public function test_member_can_login_with_django_pbkdf2_hash_and_logout(): void
    {
        $member = Member::create([
            'username' => 'oat_it',
            'password' => $this->makeDjangoPbkdf2Hash('Secret@123'),
            'first_name' => 'Oat',
            'last_name' => 'IT',
            'is_staff' => 1,
        ]);

        // Bad password fails
        $bad = $this->postJson('/login-member/', [
            'username' => 'oat_it',
            'password' => 'wrong',
        ]);
        $bad->assertOk()->assertJson([
            'success' => false,
            'error' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง',
        ]);

        // Valid Django PBKDF2 password succeeds
        $ok = $this->postJson('/login-member/', [
            'username' => 'oat_it',
            'password' => 'Secret@123',
        ]);
        $ok->assertOk()->assertJson([
            'success' => true,
            'first_name' => 'Oat',
            'is_staff' => true,
        ]);
        $ok->assertSessionHas('member_id', $member->id);
        $ok->assertSessionHas('is_staff', 1);

        // Non-staff member login reports is_staff = false
        Member::create([
            'username' => 'guest_user',
            'password' => $this->makeDjangoPbkdf2Hash('GuestPass!'),
            'first_name' => 'Guest',
            'last_name' => 'User',
            'is_staff' => 0,
        ]);
        $guestLogin = $this->postJson('/login-member/', [
            'username' => 'guest_user',
            'password' => 'GuestPass!',
        ]);
        $guestLogin->assertOk()->assertJson([
            'success' => true,
            'first_name' => 'Guest',
            'is_staff' => false,
        ]);

        // Logout clears session
        $logout = $this->postJson('/logout-member/');
        $logout->assertOk()->assertJson(['success' => true]);
        $logout->assertSessionMissing('member_id');
    }

    public function test_toggle_shift_requires_staff_and_records_hostname(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 11, 27, 20, 45, 12, 'Asia/Bangkok'));

        // Unauthenticated blocked
        $this->postJson('/toggle-shift-status/', ['closed' => true])
            ->assertStatus(403)
            ->assertJson(['success' => false]);

        // Non-staff blocked
        $this->withSession(['member_id' => 2, 'is_staff' => 0])
            ->postJson('/toggle-shift-status/', ['closed' => true])
            ->assertStatus(403)
            ->assertJson(['success' => false]);

        // Staff closes shift
        $close = $this->withSession(['member_id' => 1, 'is_staff' => 1])
            ->postJson('/toggle-shift-status/', ['closed' => true]);
        $close->assertOk()->assertJson([
            'success' => true,
            'is_closed' => true,
            'timestamp' => '2026-11-27 20:45:12',
        ]);

        $this->assertDatabaseHas('queue_app_shiftclosure', [
            'closed_by' => 'ADMIN-PC01',
            'closed_at' => '2026-11-27 20:45:12',
            'opened_at' => null,
        ]);

        // Staff reopens shift
        Carbon::setTestNow(Carbon::create(2026, 11, 27, 20, 50, 0, 'Asia/Bangkok'));
        $open = $this->withSession(['member_id' => 1, 'is_staff' => 1])
            ->postJson('/toggle-shift-status/', ['closed' => false]);
        $open->assertOk()->assertJson([
            'success' => true,
            'is_closed' => false,
            'timestamp' => '2026-11-27 20:50:00',
        ]);

        $this->assertDatabaseHas('queue_app_shiftclosure', [
            'closed_by' => 'ADMIN-PC01',
            'opened_by' => 'ADMIN-PC01',
            'opened_at' => '2026-11-27 20:50:00',
        ]);
    }

    public function test_auto_shift_control_closes_at_2100_respects_ot_and_opens_at_0800(): void
    {
        // Before 21:00 -> no-op
        Carbon::setTestNow(Carbon::create(2026, 11, 27, 20, 55, 0, 'Asia/Bangkok'));
        $this->artisan('shift:auto-control', ['--mode' => 'close'])->assertSuccessful();
        $this->assertDatabaseCount('queue_app_shiftclosure', 0);

        // At 21:05 -> auto-closes
        Carbon::setTestNow(Carbon::create(2026, 11, 27, 21, 5, 0, 'Asia/Bangkok'));
        $this->artisan('shift:auto-control', ['--mode' => 'close'])->assertSuccessful();
        $this->assertDatabaseHas('queue_app_shiftclosure', [
            'closed_by' => 'System (Auto)',
            'opened_at' => null,
        ]);

        // Staff opens for OT at 21:15
        ShiftClosure::query()->whereNull('opened_at')->update([
            'opened_at' => '2026-11-27 21:15:00',
            'opened_by' => 'ADMIN-PC01',
        ]);

        // At 01:00 next day -> OT opened_at >= 21:00 yesterday suppresses auto-close
        Carbon::setTestNow(Carbon::create(2026, 11, 28, 1, 0, 0, 'Asia/Bangkok'));
        $this->artisan('shift:auto-control', ['--mode' => 'close'])->assertSuccessful();
        $this->assertDatabaseCount('queue_app_shiftclosure', 1);

        // Simulate a closed shift reopening at 08:00
        ShiftClosure::create([
            'closed_at' => '2026-11-28 06:30:00',
            'closed_by' => 'ADMIN-PC01',
        ]);
        Carbon::setTestNow(Carbon::create(2026, 11, 28, 8, 0, 0, 'Asia/Bangkok'));
        $this->artisan('shift:auto-control', ['--mode' => 'open'])->assertSuccessful();

        $this->assertSame(0, ShiftClosure::query()->whereNull('opened_at')->count());
        $this->assertDatabaseHas('queue_app_shiftclosure', [
            'opened_by' => 'System (Auto)',
            'opened_at' => '2026-11-28 08:00:00',
        ]);
    }
}

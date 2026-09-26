<?php

namespace Tests\Feature\Ui;

use App\Http\Controllers\Maintenance\MaintenanceRatingController;
use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The user manual (help/manual) is written by hand and drifted from the system for months: it sent people to a menu that does not exist
 * ("งานแจ้งซ่อมของฉัน"), promised a QR code nothing produces, said an asset turns "In Repair" only when a technician accepts the job, described
 * the chat as private rooms tied to a job, and knew nothing of the chat's limits, the account rules or the admin pages. This pins the parts
 * that can be pinned: every name it tells a person to press or open exists on that page, every status is there, and a number that a setting
 * controls is read from the setting - so a change to the system breaks a test instead of leaving a stale sentence.
 */
class ManualIsCurrentTest extends TestCase
{
    use RefreshDatabase;

    private function manual(string $role = 'member'): string
    {
        return $this->actingAs(User::factory()->create(['role' => $role]))->get(route('help.manual'))->assertOk()->getContent();
    }

    private function source(string $view): string
    {
        return file_get_contents(resource_path('views/' . $view));
    }

    public function test_it_opens_for_every_role_and_the_admin_section_is_the_admins_alone(): void
    {
        foreach (['member', 'it_support', 'technician', 'supervisor'] as $role) {
            $this->assertStringNotContainsString('id="system-admin"', $this->manual($role), "{$role} has no system administration");
        }

        $admin = $this->manual('admin');
        $this->assertStringContainsString('id="system-admin"', $admin);
        $this->assertStringContainsString('ผู้ใช้งานระบบ', $admin);
    }

    public function test_the_role_tabs_are_for_staff_only(): void
    {
        $this->assertStringNotContainsString('เจ้าหน้าที่เทคนิค / ระบบ</span>', $this->manual('member'));
        $this->assertStringContainsString('เจ้าหน้าที่เทคนิค / ระบบ</span>', $this->manual('supervisor'));
    }

    public function test_every_status_a_request_can_have_is_explained(): void
    {
        $html = $this->manual();
        $labels = MaintenanceRequest::statusLabels();
        unset($labels[MaintenanceRequest::STATUS_COMPLETED]);   // legacy, no longer reachable

        foreach ($labels as $status => $label) {
            $this->assertStringContainsString($label, $html, "status {$status}");
        }
    }

    public function test_every_button_the_manual_names_is_on_the_request_page(): void
    {
        $page = $this->source('maintenance/requests/partials/_page_header.blade.php')
            . $this->source('maintenance/requests/partials/_modal_status_actions.blade.php');
        $html = $this->manual('admin');

        foreach (['รับทราบ', 'รับเรื่อง', 'ดำเนินการ', 'หยุดชั่วคราว', 'กลับเข้าดำเนินการ', 'เสร็จสิ้น', 'อนุมัติปิดงาน', 'ไม่รับเรื่อง', 'ยกเลิกการซ่อมบำรุง', 'พิมพ์ PDF', 'ประวัติการดำเนินงาน', 'ประเมินความพึงพอใจ'] as $button) {
            $this->assertStringContainsString($button, $page, "the page has no button \"{$button}\"");
            $this->assertStringContainsString($button, $html, "the manual never names \"{$button}\"");
        }
    }

    public function test_every_menu_the_manual_names_is_in_the_side_menu(): void
    {
        $sidebar = $this->source('components/sidebar.blade.php')
            . $this->source('maintenance/requests/index.blade.php')
            . $this->source('assets/show.blade.php')
            . $this->source('assets/index.blade.php');
        $html = $this->manual('admin');

        foreach (['แจ้งซ่อมบำรุง', 'รายการงานซ่อม', 'ทะเบียนทรัพย์สิน', 'Livechat', 'ประเมินความพึงพอใจ', 'SLA Dashboard', 'Technician Rating', 'การจัดการระบบ',
            'ประเภทใบแจ้งซ่อม', 'การแจ้งเตือน', 'ผู้ใช้งานระบบ', 'โปรไฟล์ของฉัน', 'สร้างใบแจ้งซ่อม', 'สร้างคำขอซ่อมใหม่', 'ลงทะเบียน'] as $name) {
            $this->assertStringContainsString($name, $sidebar, "\"{$name}\" is not in the system");
            $this->assertStringContainsString($name, $html, "the manual never names \"{$name}\"");
        }
    }

    public function test_what_it_used_to_promise_and_the_system_never_did_is_gone(): void
    {
        $html = $this->manual('admin');

        foreach (['QR Code', 'Example QR', 'งานแจ้งซ่อมของฉัน', 'หรือ "งานใหม่"', 'อนุมัติผลการซ่อม (Closed)', 'ทันทีเมื่อช่างกดรับเรื่อง',
            'ห้องแชท', 'มอบหมายทีมเจ้าหน้าที่ที่เกี่ยวข้องโดยอัตโนมัติ', 'HIS ID integration'] as $claim) {
            $this->assertStringNotContainsString($claim, $html, $claim);
        }
    }

    public function test_the_asset_status_rule_it_states_is_the_one_the_system_applies(): void
    {
        // the manual says a repair request turns the asset "กำลังซ่อม" the moment it is sent, and back to normal when the last one ends
        $asset = Asset::factory()->create(['status' => Asset::STATUS_ACTIVE]);
        $request = MaintenanceRequest::factory()->create(['asset_id' => $asset->id, 'status' => MaintenanceRequest::STATUS_PENDING]);
        $this->assertSame(Asset::STATUS_IN_REPAIR, $asset->fresh()->status, 'set as soon as the request exists, not when a technician accepts');

        $request->forceFill(['status' => MaintenanceRequest::STATUS_RESOLVED])->save();
        $this->assertSame(Asset::STATUS_ACTIVE, $asset->fresh()->status, 'back to normal at "ซ่อมบำรุงเสร็จสิ้น", not only at the approval');

        $html = $this->manual();
        $this->assertStringContainsString('กำลังซ่อม', $html);
        $this->assertStringContainsString('ใช้งานปกติ', $html);
        $this->assertSame('กำลังซ่อม', Asset::statusLabels()[Asset::STATUS_IN_REPAIR]);
        $this->assertSame('ใช้งานปกติ', Asset::statusLabels()[Asset::STATUS_ACTIVE]);
    }

    public function test_the_numbers_a_setting_controls_are_read_from_the_setting(): void
    {
        config(['chat.threads_per_day' => 7, 'chat.lock_idle_after_days' => 45, 'chat.delete_locked_after_days' => 60, 'chat.purge_deleted_after_days' => 20, 'chat.warn_days_before' => 5]);
        $html = $this->manual();

        $this->assertStringContainsString('วันละ 7 กระทู้', $html);
        $this->assertStringContainsString('ไม่มีการตอบครบ 45 วัน', $html);
        $this->assertStringContainsString('ถูกล็อกครบ 60 วัน', $html);
        $this->assertStringContainsString('อีก 20 วัน', $html);
        $this->assertStringContainsString('ก่อนถูกล็อก 5 วัน', $html);

        config(['uploads.max_kb' => 5120]);
        $this->assertStringContainsString('ไม่เกิน <b>5 MB</b> ต่อไฟล์', $this->manual());
    }

    public function test_the_thread_lifetime_is_left_out_when_the_rules_are_off(): void
    {
        config(['chat.lock_idle_after_days' => 0, 'chat.delete_locked_after_days' => 0]);

        $this->assertStringNotContainsString('อายุของกระทู้', $this->manual());
    }

    public function test_the_rating_window_is_the_one_the_rating_pages_enforce(): void
    {
        $html = preg_replace('/\s+/', ' ', $this->manual());

        $this->assertStringContainsString('ภายใน ' . MaintenanceRatingController::RATING_DEADLINE_DAYS . ' วันหลังปิดงาน', $html);
    }

    public function test_who_may_lock_a_thread_is_what_the_chat_allows(): void
    {
        $html = $this->manual();

        // the manual lists the roles that may lock; the model is the authority
        foreach (User::workerRoles() as $role) {
            $lockable = (new \App\Models\ChatThread)->canBeLockedBy(User::factory()->make(['role' => $role]));
            $this->assertTrue($lockable, $role);
        }
        $this->assertFalse((new \App\Models\ChatThread)->canBeLockedBy(User::factory()->make(['role' => 'supervisor'])));
        $this->assertStringContainsString('หัวหน้างาน บุคลากรทั่วไป และแม้แต่เจ้าของกระทู้ ล็อกหรือปลดล็อกไม่ได้', $html);
        foreach (['IT Support', 'Network Engineer', 'Programmer', 'เจ้าหน้าที่ซ่อมบำรุง'] as $name) {
            $this->assertStringContainsString($name, $html);
        }
    }
}

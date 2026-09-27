<?php

namespace Tests\Feature\Ui;

use App\Models\MaintenanceRequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A form's `onsubmit="return confirm('...')"` and a plain `alert(...)` were left over on a handful of pages (admin/users
 * suspend / reactivate, a maintenance type's "ปิดใช้งาน", an attachment's delete, an empty chat-thread title, the
 * notify-sound and job-type-change errors on the repair jobs page) - the app's own dialog and toast were used everywhere
 * else. `window.confirmSubmit` (resources/js/layout/confirm-submit.js) replaces the confirm() spots; `window.showToast`
 * replaces the alert() ones.
 */
class NativeDialogsReplacedTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string}> */
    public static function fixedViews(): array
    {
        return [
            'admin users list' => ['admin/users/index.blade.php'],
            'admin users edit' => ['admin/users/edit.blade.php'],
            'maintenance types list' => ['settings/maintenance-types/index.blade.php'],
            'request attachments' => ['maintenance/requests/partials/_attachments.blade.php'],
        ];
    }

    #[DataProvider('fixedViews')]
    public function test_the_page_has_no_native_confirm_left(string $view): void
    {
        $source = file_get_contents(resource_path('views/' . $view));

        $this->assertStringNotContainsString('return confirm(', $source, "{$view} still gates a form with the browser's own confirm()");
        $this->assertStringContainsString('confirmSubmit(event,', $source, "{$view} should show the app's dialog instead");
    }

    public function test_the_admin_users_list_confirms_suspend_and_reactivate_through_the_shared_dialog(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $active = User::factory()->create(['role' => 'member']);
        $suspended = User::factory()->create(['role' => 'member', 'suspended_at' => now()]);

        $html = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->getContent();

        $this->assertStringContainsString("variant: 'warning'", $html, 'suspending someone is a caution, not a destructive confirm');
        $this->assertStringContainsString("variant: 'success'", $html, 'reactivating someone reads positively');
        $this->assertStringContainsString($active->name, $html);
        $this->assertStringContainsString($suspended->name, $html);
    }

    public function test_the_maintenance_type_disable_confirms_through_the_shared_dialog(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        MaintenanceRequestType::create(['name' => 'ทดสอบ', 'is_active' => true, 'sort_order' => 1]);

        $html = $this->actingAs($admin)->get(route('settings.maintenance-types.index'))->assertOk()->getContent();

        $this->assertStringContainsString('confirmSubmit(event,', $html);
        $this->assertStringNotContainsString('return confirm(', $html);
    }

    public function test_the_chat_page_and_the_repair_jobs_script_have_no_alert_left(): void
    {
        $chat = file_get_contents(resource_path('views/chat/index.blade.php'));
        $this->assertStringNotContainsString("alert('กรุณากรอกหัวข้อกระทู้')", $chat);
        $this->assertStringContainsString("showToast({ type: 'warning', message: 'กรุณากรอกหัวข้อกระทู้' })", $chat);

        $myJobs = file_get_contents(resource_path('js/repair/my-jobs.js'));
        $this->assertDoesNotMatchRegularExpression('/[^.\w]alert\(/', $myJobs, 'a bare alert() call remains');
    }
}

<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Buttons only have to match inside a pattern group: list-page row actions, dialogs (cancel + confirm), the back
 * button, and the settings form. Pins the four spots that used to drift from their group.
 */
class PatternButtonsTest extends TestCase
{
    use RefreshDatabase;

    private const BUTTON_MARK = 'whitespace-nowrap select-none transition-all active:scale-95'; // only <x-ui.button> emits this

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    public function test_user_list_row_action_is_the_same_small_size_as_the_other_lists(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create(['role' => 'member']);

        $html = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->getContent();

        // the row action is edit only (table row + mobile card); deleting accounts is not a front-end feature
        $this->assertStringContainsString(route('admin.users.edit', $other), $html);

        // same look as assets / requests / types: py-1.5, text-[12px] font-medium, no stretched min-width
        $this->assertStringContainsString('border-emerald-300 bg-white px-3 py-1.5 text-[12px] font-medium text-emerald-700', $html);
        $this->assertStringNotContainsString('min-w-[92px]', $html);
        $this->assertDoesNotMatchRegularExpression('/border-emerald-300 bg-white px-3 py-2 /', $html, 'row action drifted back to the 36px size');
    }

    public function test_technician_rating_page_uses_the_shared_back_button(): void
    {
        $admin = $this->admin();
        $tech = User::factory()->create(['role' => 'it_support']);

        $page = $this->actingAs($admin)->get(route('technicians.rating.summary', $tech))->assertOk()->getContent();
        $this->assertStringContainsString(self::BUTTON_MARK, $page);
        $this->assertStringContainsString('>chevron_left<', $page, 'shared back button');
        $this->assertStringContainsString(route('maintenance.requests.rating.technicians'), $page, 'falls back to the board');
        $this->assertDoesNotMatchRegularExpression('/<a [^>]*\bh-9\b[^>]*>/', $page, 'hand-made 36px back link');
    }

    public function test_confirm_dialog_buttons_are_the_shared_ones_and_keep_their_wiring(): void
    {
        $html = Blade::render('<x-confirm-dialog />');

        // one cancel + one confirm per variant (only one is rendered at a time by x-if)
        $this->assertSame(5, substr_count($html, self::BUTTON_MARK));
        $this->assertSame(1, substr_count($html, '@click="cancel()"'));
        $this->assertSame(4, substr_count($html, '@click="confirm()"'));
        $this->assertSame(1, substr_count($html, 'x-text="cancelText"'));
        $this->assertSame(4, substr_count($html, 'x-text="confirmText"'));
        foreach (['primary' => 'bg-[#0F2D5C]', 'danger' => 'bg-rose-600', 'warning' => 'bg-amber-600', 'success' => 'bg-emerald-600'] as $variant => $colour) {
            $this->assertMatchesRegularExpression("/<template x-if=\"variant === '$variant'\"><button[^>]*".preg_quote($colour, '/')."[^>]*@click=\"confirm\(\)\"/", $html, $variant);
        }
        // the old hand-made pills are gone
        $this->assertStringNotContainsString('rounded-xl px-4 py-2.5', $html);
        $this->assertStringNotContainsString('rounded-xl px-6 py-2.5', $html);
    }

    public function test_notification_settings_buttons_are_shared_and_the_select_beside_them_is_44px(): void
    {
        $html = $this->actingAs($this->admin())->get(route('settings.notifications.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<x-ui', $html);
        foreach (['ทดสอบ', 'บันทึกการเลือก', 'ตั้งค่า'] as $label) {
            $this->assertMatchesRegularExpression('/<button[^>]*whitespace-nowrap select-none[^>]*>[^<]*(<span[^>]*>[^<]*<\/span>)?\s*'.preg_quote($label, '/').'\s*<\/button>/u', $html, $label);
        }
        // the save button keeps the page's navy (#0F2D5C), it is not the green of the form pages
        $this->assertMatchesRegularExpression('/<button[^>]*bg-\[#0F2D5C\][^>]*>[^<]*<span[^>]*>save<\/span>\s*บันทึกการเลือก/u', $html);
        $this->assertStringContainsString('onclick="previewSound()"', $html);
        $this->assertStringContainsString('@click="showConfig = !showConfig"', $html);
        $this->assertMatchesRegularExpression('/<select name="notification_sound"\s+class="[^"]*\bh-11\b/', $html);
        // no leftover hand-made 40px buttons in the settings row
        $this->assertStringNotContainsString('h-10 items-center gap-2 rounded-md', $html);
        // "เพิ่มเข้าคลังเสียง" is deliberately NOT the shared button: it stays the full-width green bar of the drop zone
        $this->assertMatchesRegularExpression('/<button type="submit"\s+class="w-full bg-\[#3d8b63\][^"]*py-3\.5 rounded-lg[^"]*">\s*เพิ่มเข้าคลังเสียง\s*<\/button>/u', $html);
    }
}

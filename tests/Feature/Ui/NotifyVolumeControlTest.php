<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The sound that rings when a repair job comes in (and the chat sound) played at the full volume of its file for everybody, and the only
 * control was on / off. Each staff member now sets a level for themselves, from the top bar: an arrow beside the speaker button opens a small
 * panel with a slider and a test button (the phone's menu has the same two under its speaker entry). The page that lists the sound files is
 * the admin's alone, so the control cannot live there. The level itself is kept in the browser (resources/js/layout/notify-volume.js, tested
 * in tests/js/notify-volume.test.mjs); this file holds what the server renders.
 */
class NotifyVolumeControlTest extends TestCase
{
    use RefreshDatabase;

    private function pageOf(string $role): string
    {
        return $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route('maintenance.requests.index'))->assertOk()->getContent();
    }

    public function test_staff_have_a_slider_and_a_test_button_in_the_top_bar_and_in_the_phones_menu(): void
    {
        $html = $this->pageOf('technician');

        $this->assertSame(2, substr_count($html, 'data-notify-volume-range'), 'one slider in the bar, one in the phone\'s menu');
        $this->assertSame(2, substr_count($html, 'data-notify-volume-test'), 'a test button beside each');
        $this->assertSame(2, substr_count($html, 'data-notify-volume-value'), 'and the level in words beside each');

        preg_match_all('/<input[^>]*data-notify-volume-range[^>]*>/s', $html, $inputs);
        foreach ($inputs[0] as $input) {
            $this->assertStringContainsString('type="range"', $input);
            $this->assertStringContainsString('min="10"', $input, 'silence is the speaker button\'s job: the slider has a floor');
            $this->assertStringContainsString('max="100"', $input);
            $this->assertStringContainsString('step="5"', $input);
            $this->assertStringContainsString('value="50"', $input, 'the level before the script has read the saved one: the default');
        }
    }

    public function test_the_arrow_that_opens_the_panel_is_its_own_button_and_the_speaker_still_only_switches_the_sound(): void
    {
        $html = $this->pageOf('technician');

        $this->assertSame(1, preg_match('/<button[^>]*id="notifyVolumeBtn"[^>]*>/s', $html, $arrow), 'the arrow');
        $this->assertStringContainsString('aria-label="ปรับระดับเสียงแจ้งเตือน"', $arrow[0]);
        $this->assertStringContainsString('aria-controls="notifyVolumePanel"', $arrow[0]);

        $this->assertSame(1, preg_match('/<button[^>]*id="notifyToggleBtn".*?<\/button>/s', $html, $speaker));
        $this->assertStringNotContainsString('notifyVolume', $speaker[0], 'clicking the speaker is on / off, as it was');
        $this->assertStringNotContainsString('data-notify-volume', $speaker[0]);
    }

    public function test_the_panel_says_that_an_iphone_ignores_it(): void
    {
        $this->assertStringContainsString('iPhone', $this->pageOf('technician'), 'the browser does not let a page set the volume there');
    }

    public function test_a_member_has_none_of_it(): void
    {
        $html = $this->pageOf('member');

        $this->assertStringNotContainsString('data-notify-volume', $html);
        $this->assertStringNotContainsString('notifyVolumeBtn', $html);
    }

    public function test_the_admins_sound_preview_plays_at_the_level_too(): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('settings.notifications.index'))->assertOk()->getContent();

        $this->assertStringContainsString('applyNotifyVolume(player)', $html, 'the preview must not be louder than the sound will be');
    }

    public function test_the_manual_tells_where_the_level_is_set(): void
    {
        $manual = (string) file_get_contents(resource_path('views/help/manual.blade.php'));

        $this->assertStringContainsString('ปรับระดับเสียงได้ที่ลูกศรข้างปุ่มลำโพง', $manual);
        $this->assertStringContainsString('50%', $manual, 'the level everybody starts at');
    }
}

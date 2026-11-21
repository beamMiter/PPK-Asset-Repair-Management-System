<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The staff's button for the sound that rings when a repair job comes in was a bell, which says "notifications", not "sound": nothing on
 * it told that it only switches the sound on and off. It is a speaker now (the page's script swaps the icon as the sound goes on and off;
 * until it has run the button shows the state a fresh browser is in, which is off).
 */
class SoundToggleIsASpeakerTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        return $this->actingAs(User::factory()->create(['role' => 'technician']))
            ->get(route('maintenance.requests.index'))->assertOk()->getContent();
    }

    /** the three places the toggle sits: the desktop bar, the phone's bar and the phone's menu */
    private function toggles(string $html): array
    {
        $found = [];
        foreach (['notifyToggleBtn', 'notifyToggleBtnMobileTop', 'notifyToggleBtnMobile'] as $id) {
            $this->assertSame(1, preg_match('/<button[^>]*id="'.$id.'".*?<\/button>/s', $html, $m), $id);
            $found[$id] = $m[0];
        }

        return $found;
    }

    public function test_each_toggle_shows_a_speaker_not_a_bell(): void
    {
        foreach ($this->toggles($this->page()) as $id => $button) {
            $this->assertStringContainsString('bi-volume-mute-fill', $button, "$id starts as the speaker of a sound that is off");
            $this->assertStringNotContainsString('bi-bell', $button, "$id is not a bell any more");
        }
    }

    public function test_the_desktop_button_is_named_for_the_sound_it_switches(): void
    {
        $button = $this->toggles($this->page())['notifyToggleBtn'];

        $this->assertStringContainsString('aria-label="เสียงแจ้งเตือน"', $button, 'a screen reader is told it is the sound, not the notifications');
        $this->assertStringNotContainsString('aria-label="แจ้งเตือน"', $button);
    }

    public function test_the_manual_names_the_speaker_button(): void
    {
        $manual = (string) file_get_contents(resource_path('views/help/manual.blade.php'));

        $this->assertStringContainsString('ปุ่มลำโพงบนแถบด้านบน', $manual);
        $this->assertStringNotContainsString('ปุ่มกระดิ่งบนแถบด้านบน', $manual);
    }
}

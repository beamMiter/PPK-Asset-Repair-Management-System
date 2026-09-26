<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The bell in the top bar switches the SOUND that rings when a repair job comes in. That is for the staff who take the jobs; a plain member
 * (general personnel) has no use for it and is not shown it - not on the desktop bar, not on the phone's bar, not in the phone's menu. The chat
 * widget's own bell (desktop pop-ups for chat messages) is another thing, and everybody has that one.
 */
class NotifyBellIsStaffOnlyTest extends TestCase
{
    use RefreshDatabase;

    private const JOB_SOUND_BUTTONS = ['id="notifyToggleBtn"', 'id="notifyToggleBtnMobileTop"', 'id="notifyToggleBtnMobile"'];

    private function page(string $role): string
    {
        return $this->actingAs(User::factory()->create(['role' => $role]))->get(route('maintenance.requests.index'))->assertOk()->getContent();
    }

    public function test_a_plain_member_is_shown_no_job_sound_bell_anywhere(): void
    {
        $html = $this->page('member');

        foreach (self::JOB_SOUND_BUTTONS as $button) {
            $this->assertStringNotContainsString($button, $html, $button);
        }
        $this->assertStringNotContainsString('id="notifySound"', $html, 'and the sound file for it is not even on the page');
    }

    /** @return array<string,array{0:string}> */
    public static function staff(): array
    {
        return ['admin' => ['admin'], 'supervisor' => ['supervisor'], 'it_support' => ['it_support'], 'network' => ['network'], 'programmer' => ['programmer'], 'technician' => ['technician']];
    }

    #[DataProvider('staff')]
    public function test_staff_have_the_bell(string $role): void
    {
        $html = $this->page($role);

        foreach (self::JOB_SOUND_BUTTONS as $button) {
            $this->assertStringContainsString($button, $html, "$role: $button");
        }
    }

    public function test_the_chat_widgets_own_bell_is_there_for_everybody(): void
    {
        foreach (['member', 'it_support'] as $role) {
            $this->assertStringContainsString('id="chatNotifyAsk"', $this->page($role), $role);
        }
    }
}

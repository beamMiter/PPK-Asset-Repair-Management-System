<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The notification-sound page says "เสียงแจ้งเตือนที่ใช้งานอยู่" and stores the pick in `users.notification_sound` — but the
 * `<audio id="notifySound">` in the layout was hard-wired to `new-request.mp3`, so the choice was saved and never played
 * (only the preview button used it).
 */
class NotificationSoundPlaybackTest extends TestCase
{
    use RefreshDatabase;

    private array $made = [];

    protected function tearDown(): void
    {
        foreach ($this->made as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function sound(string $name): string
    {
        @mkdir(public_path('sounds'), 0777, true);
        file_put_contents($this->made[] = public_path('sounds/'.$name), 'RIFF....WAVEfmt ');

        return $name;
    }

    /** the src of the layout's alert player for this user, or null when the page has none */
    private function playerSrc(User $user): ?string
    {
        $html = $this->actingAs($user)->get(route('repair.dashboard'))->assertOk()->getContent();

        return preg_match('/<audio\b[^>]*\bid="notifySound"[^>]*>/', $html, $tag) && preg_match('/\bsrc="([^"]+)"/', $html, $m, 0, strpos($html, $tag[0]))
            ? html_entity_decode($m[1])
            : null;
    }

    public function test_the_layout_plays_the_sound_the_user_chose(): void
    {
        $file = $this->sound('zz-test-'.uniqid().'.mp3');
        $user = User::factory()->create(['role' => 'admin', 'notification_sound' => $file]);

        $this->assertSame(asset('sounds/'.$file), $this->playerSrc($user));
    }

    public function test_the_default_sound_plays_for_a_user_who_never_chose(): void
    {
        $user = User::factory()->create(['role' => 'admin']); // column default: new-request.mp3

        $this->assertSame(asset('sounds/new-request.mp3'), $this->playerSrc($user));
    }

    public function test_a_choice_whose_file_has_been_deleted_falls_back_to_the_default(): void
    {
        $file = $this->sound('zz-test-'.uniqid().'.mp3');
        $user = User::factory()->create(['role' => 'admin', 'notification_sound' => $file]);
        unlink(public_path('sounds/'.$file));

        $this->assertSame(asset('sounds/new-request.mp3'), $this->playerSrc($user));
    }

    public function test_an_odd_value_in_the_column_cannot_point_the_player_anywhere_else(): void
    {
        foreach (['../../.env', '', '  ', '../sounds/../index.php', 'missing.mp3'] as $value) {
            $user = User::factory()->create(['role' => 'admin', 'notification_sound' => $value]);

            $this->assertSame(asset('sounds/new-request.mp3'), $this->playerSrc($user), var_export($value, true));
        }
    }

    public function test_a_file_name_with_spaces_or_thai_is_url_encoded(): void
    {
        $file = $this->sound('zz-test-เสียง ทดสอบ '.uniqid().'.wav');
        $user = User::factory()->create(['role' => 'admin', 'notification_sound' => $file]);

        $src = $this->playerSrc($user);

        $this->assertSame(asset('sounds/'.rawurlencode($file)), $src);
        $this->assertStringNotContainsString(' ', $src);
    }

    public function test_members_get_no_player_as_before(): void
    {
        $this->assertNull($this->playerSrc(User::factory()->create(['role' => 'member'])));
    }

    public function test_saving_a_choice_on_the_settings_page_changes_what_the_next_page_plays(): void
    {
        $file = $this->sound('zz-test-'.uniqid().'.mp3');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->patch(route('settings.notifications.update_sound'), ['notification_sound' => $file])->assertRedirect();

        $this->assertSame(asset('sounds/'.$file), $this->playerSrc($admin->fresh()));
    }
}

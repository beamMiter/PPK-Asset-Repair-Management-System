<?php

namespace Tests\Feature;

use App\Events\ChatThreadDeleted;
use App\Events\ChatThreadLockChanged;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Locking, unlocking and deleting a thread reach everyone who has it open with no refresh: the server broadcasts on the thread's
 * channel (and the poll answers with an X-Thread-Locked header for browsers with no websocket), and the page follows the state
 * (resources/js/chat/page.js, tests/js/chat-page.test.mjs). The one who locks is answered in JSON, so their page does not reload.
 */
class ChatThreadLiveStateTest extends TestCase
{
    use RefreshDatabase;

    private function thread(bool $locked = false): ChatThread
    {
        $author = User::factory()->create(['role' => 'it_support']);

        return ChatThread::create(['title' => 'เครื่องพิมพ์ชั้น 2', 'author_id' => $author->id, 'is_locked' => $locked]);
    }

    // ---- the server tells everyone ----------------------------------------------------------------------------

    public function test_locking_answers_json_and_tells_the_thread_s_channel(): void
    {
        Event::fake([ChatThreadLockChanged::class]);
        $thread = $this->thread();
        $staff = User::factory()->create(['role' => 'it_support']);

        $this->actingAs($staff)->postJson(route('chat.lock', $thread))
            ->assertOk()
            ->assertJsonPath('is_locked', true)
            ->assertJsonPath('message', 'ล็อกกระทู้เรียบร้อยแล้ว ผู้ใช้อื่นจะไม่สามารถส่งข้อความได้');

        $this->assertTrue((bool) $thread->fresh()->is_locked);
        Event::assertDispatched(ChatThreadLockChanged::class, fn ($e) => $e->threadId === $thread->id && $e->isLocked === true);
        $this->assertNull(session('toast'), 'a JSON answer flashes no toast: it would show a second time on the next page');
    }

    public function test_unlocking_does_the_same(): void
    {
        Event::fake([ChatThreadLockChanged::class]);
        $thread = $this->thread(true);
        $staff = User::factory()->create(['role' => 'it_support']);

        $this->actingAs($staff)->postJson(route('chat.unlock', $thread))->assertOk()->assertJsonPath('is_locked', false);

        $this->assertFalse((bool) $thread->fresh()->is_locked);
        Event::assertDispatched(ChatThreadLockChanged::class, fn ($e) => $e->threadId === $thread->id && $e->isLocked === false);
    }

    public function test_a_plain_form_post_still_works_with_the_flashed_toast(): void
    {
        Event::fake([ChatThreadLockChanged::class]);
        $thread = $this->thread();
        $staff = User::factory()->create(['role' => 'it_support']);

        $this->actingAs($staff)->from(route('chat.index'))->post(route('chat.lock', $thread))
            ->assertRedirect(route('chat.index'))
            ->assertSessionHas('toast.type', 'success');

        $this->assertTrue((bool) $thread->fresh()->is_locked);
        Event::assertDispatched(ChatThreadLockChanged::class);
    }

    public function test_a_member_cannot_lock_and_nothing_is_broadcast(): void
    {
        Event::fake([ChatThreadLockChanged::class]);
        $thread = $this->thread();
        $member = User::factory()->create(['role' => 'member']);

        $this->actingAs($member)->postJson(route('chat.lock', $thread))->assertForbidden();

        $this->assertFalse((bool) $thread->fresh()->is_locked);
        Event::assertNotDispatched(ChatThreadLockChanged::class);
    }

    public function test_the_app_locking_over_the_api_tells_the_thread_s_channel_too(): void
    {
        Event::fake([ChatThreadLockChanged::class]);
        $thread = $this->thread();
        \Laravel\Sanctum\Sanctum::actingAs(User::factory()->create(['role' => 'technician']));

        $this->postJson("/api/threads/{$thread->id}/lock")->assertOk();
        Event::assertDispatched(ChatThreadLockChanged::class, fn ($e) => $e->threadId === $thread->id && $e->isLocked === true);

        $this->postJson("/api/threads/{$thread->id}/unlock")->assertOk();
        Event::assertDispatched(ChatThreadLockChanged::class, fn ($e) => $e->threadId === $thread->id && $e->isLocked === false);
    }

    public function test_deleting_a_thread_tells_whoever_has_it_open(): void
    {
        Event::fake([ChatThreadDeleted::class]);
        $thread = $this->thread();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->delete(route('chat.destroy', $thread))->assertRedirect(route('chat.index'));

        Event::assertDispatched(ChatThreadDeleted::class, fn ($e) => $e->threadId === $thread->id);
    }

    public function test_a_non_admin_cannot_delete_and_nothing_is_broadcast(): void
    {
        Event::fake([ChatThreadDeleted::class]);
        $thread = $this->thread();
        $staff = User::factory()->create(['role' => 'it_support']);

        $this->actingAs($staff)->delete(route('chat.destroy', $thread));

        $this->assertNotNull($thread->fresh());
        Event::assertNotDispatched(ChatThreadDeleted::class);
    }

    public function test_the_events_go_out_on_the_thread_s_channel_under_the_names_the_page_listens_for(): void
    {
        $lock = new ChatThreadLockChanged(42, true);
        $this->assertSame('chat.42', $lock->broadcastOn()[0]->name);
        $this->assertSame('thread.lock', $lock->broadcastAs());
        $this->assertSame(['thread_id' => 42, 'is_locked' => true], $lock->broadcastWith());

        $gone = new ChatThreadDeleted(42);
        $this->assertSame('chat.42', $gone->broadcastOn()[0]->name);
        $this->assertSame('thread.deleted', $gone->broadcastAs());
        $this->assertSame(['thread_id' => 42], $gone->broadcastWith());

        $js = file_get_contents(resource_path('js/chat/page.js'));
        $this->assertStringContainsString(".listen('.thread.lock'", $js);
        $this->assertStringContainsString(".listen('.thread.deleted'", $js);
    }

    public function test_the_lock_still_happens_while_the_push_service_is_down(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher', 'key' => 'k', 'secret' => 's', 'app_id' => '1',
                'options' => ['host' => '127.0.0.1', 'port' => 9, 'scheme' => 'http', 'useTLS' => false, 'timeout' => 2],
            ],
        ]);
        $thread = $this->thread();
        $staff = User::factory()->create(['role' => 'it_support']);

        $this->actingAs($staff)->postJson(route('chat.lock', $thread))->assertOk()->assertJsonPath('is_locked', true);

        $this->assertTrue((bool) $thread->fresh()->is_locked);
    }

    // ---- the polling fallback ---------------------------------------------------------------------------------

    public function test_the_poll_carries_the_lock_state_in_a_header_and_keeps_its_body(): void
    {
        $thread = $this->thread();
        $staff = User::factory()->create(['role' => 'it_support']);

        $open = $this->actingAs($staff)->getJson(route('chat.messages', $thread))->assertOk();
        $open->assertHeader('X-Thread-Locked', '0');
        $this->assertSame([], $open->json(), 'still a bare array: the page reads it as one');

        $thread->update(['is_locked' => true]);
        $this->actingAs($staff)->getJson(route('chat.messages', $thread))->assertOk()->assertHeader('X-Thread-Locked', '1');
    }

    public function test_the_poll_of_a_deleted_thread_is_a_404_which_is_how_the_page_learns_it(): void
    {
        $thread = $this->thread();
        $staff = User::factory()->create(['role' => 'it_support']);
        $thread->delete();

        $this->actingAs($staff)->getJson(route('chat.messages', $thread->id))->assertNotFound();
    }

    // ---- the page ---------------------------------------------------------------------------------------------

    private function page(ChatThread $thread, string $role = 'it_support'): string
    {
        $me = User::factory()->create(['role' => $role]);

        return $this->actingAs($me)->get(route('chat.index', ['thread_id' => $thread->id]))->assertOk()->getContent();
    }

    /** the opening tag of the element that carries $needle */
    private function tagOf(string $html, string $needle): string
    {
        $at = strpos($html, $needle);
        $this->assertNotFalse($at, $needle);
        $start = strrpos(substr($html, 0, $at), '<');

        return substr($html, $start, strpos($html, '>', $at) - $start);
    }

    /** whether the element that carries $needle is hidden as first drawn */
    private function hiddenAtStart(string $html, string $needle): bool
    {
        return str_contains($this->tagOf($html, $needle), 'style="display: none;"');
    }

    public function test_an_open_thread_draws_the_composer_and_hides_the_notice(): void
    {
        $html = $this->page($this->thread(false));

        $this->assertStringContainsString('locked: false', $html);
        $this->assertStringContainsString('id="chatForm"', $html);
        $this->assertStringContainsString('id="lockedNotice"', $html, 'both are on the page: the state chooses');
        $this->assertFalse($this->hiddenAtStart($html, 'x-show="!locked"'), 'the composer shows');
        $this->assertTrue($this->hiddenAtStart($html, 'id="lockedNotice"'), 'the notice does not');
    }

    public function test_a_locked_thread_draws_the_notice_and_hides_the_composer(): void
    {
        $html = $this->page($this->thread(true));

        $this->assertStringContainsString('locked: true', $html);
        $this->assertTrue($this->hiddenAtStart($html, 'x-show="!locked"'), 'the composer is out of the way');
        $this->assertFalse($this->hiddenAtStart($html, 'id="lockedNotice"'), 'the notice shows');
        $this->assertStringNotContainsString('opacity-50 pointer-events-none', $html, 'a stale disabled look would stay on after an unlock');
    }

    public function test_the_lock_button_and_badge_follow_the_state(): void
    {
        $locked = $this->page($this->thread(true));
        $this->assertStringContainsString('title="ปลดล็อกกระทู้"', $locked);
        $this->assertStringContainsString('title="ล็อกกระทู้"', $locked, 'both buttons are drawn, one is hidden');

        $this->assertStringContainsString('x-show="!locked"', $this->tagOf($locked, 'title="ล็อกกระทู้"'), 'Alpine owns it from here on');
        $this->assertStringContainsString('x-show="locked"', $this->tagOf($locked, 'title="ปลดล็อกกระทู้"'));
        $this->assertTrue($this->hiddenAtStart($locked, 'title="ล็อกกระทู้"'));
        $this->assertFalse($this->hiddenAtStart($locked, 'title="ปลดล็อกกระทู้"'));

        $open = $this->page($this->thread(false));
        $this->assertFalse($this->hiddenAtStart($open, 'title="ล็อกกระทู้"'));
        $this->assertTrue($this->hiddenAtStart($open, 'title="ปลดล็อกกระทู้"'));
    }

    public function test_a_member_gets_no_lock_button_but_still_hears_the_lock(): void
    {
        $html = $this->page($this->thread(false), 'member');

        $this->assertStringNotContainsString('title="ล็อกกระทู้"', $html);
        $this->assertStringContainsString('locked: false', $html, 'the state is there for the badge and the composer');
        $this->assertStringContainsString('data-list-url', $html);
    }

    public function test_the_alpine_state_is_a_well_formed_attribute(): void
    {
        $thread = $this->thread(false);
        $html = $this->page($thread);

        preg_match('/<div id="chat-pane" x-data="(.*?)"\s+@keydown\.escape\.window/s', $html, $m);
        $this->assertNotEmpty($m, 'x-data ends where the next attribute starts: a stray double quote inside it would cut it short');
        $state = str_replace('\\/', '/', html_entity_decode($m[1]));   // @js writes a URL as http:\/\/host\/…
        $this->assertStringContainsString('async submitLock()', $state);
        $this->assertStringContainsString(route('chat.lock', $thread), $state);
        $this->assertStringContainsString(route('chat.unlock', $thread), $state);
    }
}

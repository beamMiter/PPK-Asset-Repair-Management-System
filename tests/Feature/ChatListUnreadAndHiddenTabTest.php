<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Two things the chat page's thread list lacked, both known to the widget: "ใหม่ N" beside a thread of mine with messages I have not read,
 * and a "ซ่อนไว้" list for the threads I hid from "กระทู้ที่มีส่วนร่วม" (before, they were only findable by scanning "ทั้งหมด").
 */
class ChatListUnreadAndHiddenTabTest extends TestCase
{
    use RefreshDatabase;

    private User $me;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([ChatMessageSent::class]);
        $this->me = User::factory()->create(['role' => 'it_support']);
        $this->other = User::factory()->create(['role' => 'it_support']);
    }

    private function thread(string $title, User $author): ChatThread
    {
        return ChatThread::create(['title' => $title, 'author_id' => $author->id, 'is_locked' => false]);
    }

    private function say(ChatThread $thread, User $who, string $body = 'ข้อความ'): ChatMessage
    {
        return ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => $who->id, 'body' => $body]);
    }

    /** written the way the page does it: the sender's read pointer moves to their own message */
    private function send(ChatThread $thread, User $who, string $body = 'ข้อความ'): void
    {
        $this->actingAs($who)->post(route('chat.messages.store', $thread), ['body' => $body])->assertRedirect();
    }

    private function page(array $query = []): string
    {
        return $this->actingAs($this->me)->get(route('chat.index', $query))->assertOk()->getContent();
    }

    // ---- ใหม่ N -------------------------------------------------------------------------------------------------

    public function test_a_thread_of_mine_with_messages_i_have_not_read_says_how_many(): void
    {
        $mine = $this->thread('ผมตั้ง', $this->me);
        $this->say($mine, $this->me);
        $this->say($mine, $this->other);
        $this->say($mine, $this->other);

        // I wrote first (my pointer moves to my own message through the controller; here: as the real send does)
        $this->actingAs($this->me)->post(route('chat.messages.store', $mine), ['body' => 'ผมเพิ่งพิมพ์']);
        $this->say($mine, $this->other);
        $this->say($mine, $this->other);

        $this->assertMatchesRegularExpression('/ใหม่ 2</', $this->page());
    }

    public function test_a_thread_i_have_no_part_in_shows_no_label_however_many_messages_it_has(): void
    {
        $theirs = $this->thread('ของคนอื่น', $this->other);
        foreach (range(1, 5) as $i) {
            $this->say($theirs, $this->other);
        }

        $this->assertStringNotContainsString('ใหม่ ', $this->page());
        $this->assertStringNotContainsString('ใหม่ ', $this->page(['scope' => 'all']));
    }

    public function test_opening_the_thread_reads_it_and_the_label_goes(): void
    {
        $mine = $this->thread('ผมตั้ง', $this->other);
        $this->send($mine, $this->me);
        $this->say($mine, $this->other);
        $this->say($mine, $this->other);

        $this->assertStringContainsString('ใหม่ 2', $this->page());

        $open = $this->page(['thread_id' => $mine->id]);
        $this->assertStringNotContainsString('ใหม่ 2', $open, 'the thread on screen is read');

        $this->assertStringNotContainsString('ใหม่ ', $this->page(), 'and it stays read on the next visit');
    }

    public function test_a_hidden_thread_carries_no_label_and_a_big_count_is_capped(): void
    {
        $hidden = $this->thread('ซ่อน', $this->other);
        $this->say($hidden, $this->me);
        $this->actingAs($this->me)->post(route('chat.hide', $hidden));
        $this->say($hidden, $this->other);

        $this->assertStringNotContainsString('ใหม่ ', $this->page());

        $busy = $this->thread('คึกคัก', $this->other);
        $this->say($busy, $this->me);
        foreach (range(1, 101) as $i) {
            $this->say($busy, $this->other);
        }
        $this->assertStringContainsString('ใหม่ 99+', $this->page());
    }

    public function test_the_label_is_counted_for_the_person_looking(): void
    {
        $shared = $this->thread('ร่วมกัน', $this->me);
        $this->send($shared, $this->me);
        $this->send($shared, $this->other);

        $this->assertStringContainsString('ใหม่ 1', $this->page(), 'I have not read what the other wrote');

        $forOther = $this->actingAs($this->other)->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('ใหม่ ', $forOther, 'the other wrote last: their own pointer is at the end');
    }

    // ---- ซ่อนไว้ ------------------------------------------------------------------------------------------------

    public function test_the_tab_is_not_there_until_something_is_hidden(): void
    {
        $t = $this->thread('ผมตั้ง', $this->me);
        $this->say($t, $this->me);

        $this->assertStringNotContainsString('ซ่อนไว้', $this->page());

        $this->actingAs($this->me)->post(route('chat.hide', $t));

        $html = $this->page();
        $this->assertMatchesRegularExpression('/ซ่อนไว้\s*<span[^>]*>1</', $html);
        $this->assertSame(['all' => 1, 'mine' => 0, 'hidden' => 1], $this->actingAs($this->me)->get(route('chat.index'))->viewData('counts'));
    }

    public function test_it_lists_only_the_threads_i_hid_and_they_can_be_shown_again_from_there(): void
    {
        $hidden = $this->thread('ซ่อนไว้แล้ว', $this->me);
        $this->say($hidden, $this->me);
        $visible = $this->thread('ยังเห็น', $this->me);
        $this->say($visible, $this->me);
        $otherHidden = $this->thread('คนอื่นซ่อน', $this->other);
        $this->say($otherHidden, $this->me);
        $this->say($otherHidden, $this->other);
        $this->actingAs($this->me)->post(route('chat.hide', $hidden));
        $this->actingAs($this->other)->post(route('chat.hide', $otherHidden));

        $titles = $this->actingAs($this->me)->get(route('chat.index', ['scope' => 'hidden']))->viewData('threads')->pluck('title')->all();

        $this->assertSame(['ซ่อนไว้แล้ว'], $titles, 'not the visible one, not what somebody else hid');

        $html = $this->page(['scope' => 'hidden', 'thread_id' => $hidden->id]);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('แสดงในกระทู้ที่มีส่วนร่วมอีกครั้ง', $html, 'the way back is in the header');
        $this->assertStringContainsString(e(route('chat.index', ['thread_id' => $hidden->id, 'page' => 1, 'scope' => 'hidden'])), $html, 'a thread opens inside this list');
        $this->assertStringContainsString('<input type="hidden" name="scope" value="hidden">', $html);
    }

    public function test_showing_a_thread_again_empties_the_list_and_the_tab_stays_while_it_is_open(): void
    {
        $t = $this->thread('ผมตั้ง', $this->me);
        $this->say($t, $this->me);
        $this->actingAs($this->me)->post(route('chat.hide', $t));
        $this->actingAs($this->me)->delete(route('chat.unhide', $t));

        $html = $this->page(['scope' => 'hidden']);

        $this->assertStringContainsString('ไม่มีกระทู้ที่ซ่อนไว้', $html);
        $this->assertStringContainsString('ซ่อนไว้', $html, 'the list you are in does not vanish under you');
        $this->assertStringNotContainsString('ซ่อนไว้', $this->page(), 'from another list it is gone: nothing is hidden');
    }

    public function test_the_api_lists_them_too(): void
    {
        $t = $this->thread('ซ่อน', $this->me);
        $this->say($t, $this->me);
        $this->actingAs($this->me)->post(route('chat.hide', $t));
        $keep = $this->thread('เห็น', $this->me);
        $this->say($keep, $this->me);

        Sanctum::actingAs($this->me);
        $titles = collect($this->getJson('/api/threads?scope=hidden')->assertOk()->json('data'))->pluck('title')->all();

        $this->assertSame(['ซ่อน'], $titles);
    }
}

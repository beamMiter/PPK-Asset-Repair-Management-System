<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The chat page can show only the threads a person took part in - started, or wrote in - and not just through the floating widget.
 * One definition serves the page, the API list and the widget (ChatThread::involving).
 */
class ChatMineScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $me;
    private User $other;
    private ChatThread $started;      // I started it
    private ChatThread $wroteIn;      // someone else did, and I wrote in it
    private ChatThread $notMine;      // I have no part in it
    private ChatThread $onlyRead;     // I opened it, others wrote

    protected function setUp(): void
    {
        parent::setUp();
        $this->me = User::factory()->create(['role' => 'it_support']);
        $this->other = User::factory()->create(['role' => 'it_support']);

        $this->started = ChatThread::create(['title' => 'ผมตั้งเอง', 'author_id' => $this->me->id, 'is_locked' => false]);
        $this->wroteIn = ChatThread::create(['title' => 'ผมไปตอบ', 'author_id' => $this->other->id, 'is_locked' => false]);
        $this->notMine = ChatThread::create(['title' => 'ไม่เกี่ยวกับผม', 'author_id' => $this->other->id, 'is_locked' => false]);
        $this->onlyRead = ChatThread::create(['title' => 'ผมแค่เปิดอ่าน', 'author_id' => $this->other->id, 'is_locked' => false]);

        $this->say($this->wroteIn, $this->me, 'ตอบแล้ว');
        $this->say($this->notMine, $this->other, 'คุยกันเอง');
        $this->say($this->onlyRead, $this->other, 'ข้อความ');
    }

    private function say(ChatThread $thread, User $who, string $body): void
    {
        ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => $who->id, 'body' => $body]);
    }

    private function titles(array $query = [], ?User $as = null): array
    {
        $page = $this->actingAs($as ?? $this->me)->get(route('chat.index', $query))->assertOk();

        return $page->viewData('threads')->pluck('title')->sort()->values()->all();
    }

    // ---- the page ----------------------------------------------------------------------------------------------

    public function test_by_default_every_thread_is_listed(): void
    {
        $this->assertEqualsCanonicalizing(['ผมตั้งเอง', 'ผมไปตอบ', 'ไม่เกี่ยวกับผม', 'ผมแค่เปิดอ่าน'], $this->titles());
    }

    public function test_mine_is_the_threads_i_started_or_wrote_in(): void
    {
        $this->assertEqualsCanonicalizing(['ผมตั้งเอง', 'ผมไปตอบ'], $this->titles(['scope' => 'mine']));
    }

    public function test_reading_a_thread_does_not_make_it_mine(): void
    {
        $this->actingAs($this->me)->get(route('chat.index', ['thread_id' => $this->onlyRead->id]))->assertOk();

        $this->assertNotContains('ผมแค่เปิดอ่าน', $this->titles(['scope' => 'mine']));
    }

    public function test_writing_in_a_thread_makes_it_mine(): void
    {
        $this->assertNotContains('ไม่เกี่ยวกับผม', $this->titles(['scope' => 'mine']));

        $this->actingAs($this->me)->post(route('chat.messages.store', $this->notMine), ['body' => 'ขอร่วมด้วย']);

        $this->assertContains('ไม่เกี่ยวกับผม', $this->titles(['scope' => 'mine']));
    }

    public function test_it_is_each_persons_own_list(): void
    {
        $this->assertEqualsCanonicalizing(['ไม่เกี่ยวกับผม', 'ผมแค่เปิดอ่าน', 'ผมไปตอบ'], $this->titles(['scope' => 'mine'], $this->other));
    }

    public function test_the_search_narrows_the_list_it_is_in(): void
    {
        $this->assertSame(['ผมไปตอบ'], $this->titles(['scope' => 'mine', 'q' => 'ตอบ']));
        $this->assertSame([], $this->titles(['scope' => 'mine', 'q' => 'ไม่เกี่ยว']), 'a thread that is not mine stays out of it');
        $this->assertSame(['ไม่เกี่ยวกับผม'], $this->titles(['q' => 'ไม่เกี่ยว']));
    }

    public function test_any_other_scope_is_the_full_list(): void
    {
        $this->assertCount(4, $this->titles(['scope' => 'everything']));
        $this->assertCount(4, $this->titles(['scope' => ['x']]));
    }

    public function test_the_tabs_show_how_many_each_list_holds_and_which_is_open(): void
    {
        $all = $this->actingAs($this->me)->get(route('chat.index'))->assertOk();
        $this->assertSame(['all' => 4, 'mine' => 2], $all->viewData('counts'));
        $html = $all->getContent();
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>\s*ทั้งหมด\s*<span[^>]*>4</', $html);
        $this->assertMatchesRegularExpression('/กระทู้ที่มีส่วนร่วม\s*<span[^>]*>2</', $html);

        $mine = $this->actingAs($this->me)->get(route('chat.index', ['scope' => 'mine']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>\s*กระทู้ที่มีส่วนร่วม\s*<span/', $mine);
        $this->assertSame(['all' => 4, 'mine' => 2], $this->actingAs($this->me)->get(route('chat.index', ['scope' => 'mine', 'q' => 'ตอบ']))->viewData('counts'), 'the counts are not narrowed by a search');
    }

    public function test_opening_a_thread_from_my_list_stays_in_my_list(): void
    {
        $mine = $this->actingAs($this->me)->get(route('chat.index', ['scope' => 'mine']))->assertOk()->getContent();

        $this->assertStringContainsString(e(route('chat.index', ['thread_id' => $this->started->id, 'page' => 1, 'scope' => 'mine'])), $mine);
        $this->assertStringContainsString('<input type="hidden" name="scope" value="mine">', $mine, 'a search keeps the list too');

        $all = $this->actingAs($this->me)->get(route('chat.index'))->assertOk()->getContent();
        $this->assertStringContainsString(e(route('chat.index', ['thread_id' => $this->started->id, 'page' => 1])), $all, 'from the full list a thread opens in the full list');
        $this->assertStringNotContainsString('<input type="hidden" name="scope"', $all);
    }

    public function test_an_empty_mine_list_says_so(): void
    {
        $stranger = User::factory()->create(['role' => 'it_support']);

        $this->actingAs($stranger)->get(route('chat.index', ['scope' => 'mine']))->assertOk()
            ->assertSee('คุณยังไม่ได้ตั้งหรือตอบกระทู้ใดเลย');
        $this->actingAs($stranger)->get(route('chat.index', ['scope' => 'mine', 'q' => 'ไม่มีแน่นอน']))->assertOk()
            ->assertSee('ไม่พบข้อมูลกระทู้')->assertDontSee('คุณยังไม่ได้ตั้งหรือตอบกระทู้ใดเลย');
    }

    public function test_paging_keeps_the_list(): void
    {
        foreach (range(1, 16) as $i) {
            ChatThread::create(['title' => "ของผม {$i}", 'author_id' => $this->me->id, 'is_locked' => false]);
        }

        $page = $this->actingAs($this->me)->get(route('chat.index', ['scope' => 'mine']))->assertOk();

        $this->assertStringContainsString('scope=mine', (string) $page->viewData('threads')->nextPageUrl());
    }

    // ---- the API and the widget agree with the page ----------------------------------------------------------------

    public function test_the_api_list_takes_the_same_scope(): void
    {
        Sanctum::actingAs($this->me);

        $titles = fn (string $query) => collect($this->getJson('/api/threads' . $query)->assertOk()->json('data'))->pluck('title')->sort()->values()->all();

        $this->assertCount(4, $titles(''));
        $this->assertEqualsCanonicalizing(['ผมตั้งเอง', 'ผมไปตอบ'], $titles('?scope=mine'));
    }

    public function test_the_widget_lists_the_same_set(): void
    {
        $ids = collect($this->actingAs($this->me)->getJson(route('chat.my_updates'))->assertOk()->json())->pluck('id')->sort()->values()->all();

        $this->assertSame(collect([$this->started->id, $this->wroteIn->id])->sort()->values()->all(), $ids);
    }
}

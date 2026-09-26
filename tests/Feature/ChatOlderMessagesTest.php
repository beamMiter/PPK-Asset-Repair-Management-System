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
 * The page drew the latest messages and offered no way to the ones before: a longer thread lost its beginning. Now opening a thread draws only
 * the latest 30 (a light first load), and scrolling to the top
 * (or the button) loads the batch before the first one drawn - keyed by message id, as a messenger does, not by page number, so a message
 * that arrives meanwhile cannot shift what is loaded. A deleted message comes back as a placeholder with no words.
 */
class ChatOlderMessagesTest extends TestCase
{
    use RefreshDatabase;

    private User $me;
    private User $other;
    private ChatThread $thread;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([ChatMessageSent::class]);
        $this->me = User::factory()->create(['role' => 'member']);
        $this->other = User::factory()->create(['role' => 'member', 'name' => 'วรรณา ใจดี']);
        $this->thread = ChatThread::create(['title' => 'ยาวมาก', 'author_id' => $this->me->id, 'is_locked' => false]);
    }

    /** @return \Illuminate\Support\Collection<int,ChatMessage> oldest first */
    private function messages(int $n): \Illuminate\Support\Collection
    {
        return collect(range(1, $n))->map(fn ($i) => ChatMessage::create([
            'chat_thread_id' => $this->thread->id, 'user_id' => $i % 2 ? $this->me->id : $this->other->id, 'body' => "ข้อความที่ {$i}",
        ]));
    }

    private function older(int $beforeId, array $query = [])
    {
        return $this->actingAs($this->me)->getJson(route('chat.messages', ['thread' => $this->thread] + ['before_id' => $beforeId] + $query));
    }

    public function test_opening_a_thread_draws_only_the_latest_30_and_says_there_are_earlier_ones(): void
    {
        $all = $this->messages(120);

        $page = $this->actingAs($this->me)->get(route('chat.index', ['thread_id' => $this->thread->id]))->assertOk();
        $html = $page->getContent();

        $this->assertCount(30, $page->viewData('messages'), 'a light first load');
        $this->assertStringContainsString('data-first-id="' . $all[90]->id . '"', $html, 'the cursor is the oldest one drawn');
        $this->assertStringContainsString('data-has-more="1"', $html);
        $this->assertDoesNotMatchRegularExpression('/id="loadEarlierWrap" class="[^"]*\bhidden\b/', $html, 'the button is there');
        $this->assertStringContainsString('ข้อความที่ 120', $html);
        $this->assertStringContainsString('ข้อความที่ 91<', $html, 'the oldest one drawn');
        $this->assertStringNotContainsString('ข้อความที่ 90<', $html);
    }

    public function test_how_many_are_drawn_on_opening_is_a_setting(): void
    {
        config(['chat.initial_messages' => 5]);
        $this->messages(12);

        $page = $this->actingAs($this->me)->get(route('chat.index', ['thread_id' => $this->thread->id]))->assertOk();

        $this->assertCount(5, $page->viewData('messages'));
        $this->assertStringContainsString('data-has-more="1"', $page->getContent());
    }

    public function test_a_thread_of_exactly_30_has_nothing_earlier(): void
    {
        $this->messages(30);

        $this->assertStringContainsString('data-has-more="0"', $this->actingAs($this->me)->get(route('chat.index', ['thread_id' => $this->thread->id]))->getContent());
    }

    public function test_a_short_thread_has_nothing_earlier_and_the_button_is_hidden(): void
    {
        $this->messages(10);

        $html = $this->actingAs($this->me)->get(route('chat.index', ['thread_id' => $this->thread->id]))->assertOk()->getContent();

        $this->assertStringContainsString('data-has-more="0"', $html);
        $this->assertMatchesRegularExpression('/id="loadEarlierWrap" class="[^"]*\bhidden\b/', $html);
    }

    public function test_the_batch_before_a_message_comes_oldest_first_with_the_cursor_moving_back(): void
    {
        $all = $this->messages(120);

        $first = $this->older($all[90]->id)->assertOk()->assertHeader('X-Has-More', '1');
        $ids = collect($first->json())->pluck('id')->all();
        $this->assertSame($all->slice(60, 30)->pluck('id')->values()->all(), $ids, '30 messages, oldest first');
        $this->assertSame('ข้อความที่ 61', $first->json('0.body'));

        $second = $this->older($ids[0])->assertOk()->assertHeader('X-Has-More', '1');
        $this->assertSame($all->slice(30, 30)->pluck('id')->values()->all(), collect($second->json())->pluck('id')->all());

        $last = $this->older(collect($second->json())->first()['id'])->assertOk()->assertHeader('X-Has-More', '0');
        $this->assertSame($all->slice(0, 30)->pluck('id')->values()->all(), collect($last->json())->pluck('id')->all(), 'the beginning, and it says so');
    }

    public function test_a_message_that_arrives_meanwhile_cannot_shift_what_is_loaded(): void
    {
        $all = $this->messages(60);
        $cursor = $all[10]->id;
        $before = collect($this->older($cursor)->json())->pluck('id')->all();

        $this->messages(5);   // five newer ones arrive

        $this->assertSame($before, collect($this->older($cursor)->json())->pluck('id')->all());
    }

    public function test_the_size_of_a_batch_can_be_chosen_within_bounds(): void
    {
        $all = $this->messages(20);
        $cursor = $all[19]->id;

        $this->assertCount(5, $this->older($cursor, ['limit' => 5])->json());
        $this->assertCount(19, $this->older($cursor, ['limit' => 500])->json(), 'capped at 100, and there are only 19 before it');
        $this->assertCount(1, $this->older($cursor, ['limit' => 0])->json(), 'at least one');
    }

    public function test_a_deleted_message_comes_back_as_a_placeholder_without_its_words(): void
    {
        $all = $this->messages(5);
        $all[1]->delete();

        $rows = collect($this->older($all[4]->id)->json());

        $gone = $rows->firstWhere('id', $all[1]->id);
        $this->assertTrue($gone['deleted']);
        $this->assertNull($gone['body']);
        $this->assertStringNotContainsString('ข้อความที่ 2', json_encode($gone, JSON_UNESCAPED_UNICODE));
        $this->assertFalse($rows->firstWhere('id', $all[0]->id)['deleted']);
        $this->assertSame('ข้อความที่ 1', $rows->firstWhere('id', $all[0]->id)['body']);
    }

    public function test_each_message_carries_who_wrote_it_and_when_as_an_unambiguous_time(): void
    {
        $all = $this->messages(3);

        $row = $this->older($all[2]->id)->json('1');

        $this->assertSame('วรรณา ใจดี', $row['user']['name']);
        $this->assertNotNull($row['user']['avatar_thumb_url']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z$/', $row['created_at'], 'ISO 8601, UTC: the page shows it on the Thai clock');
    }

    public function test_without_before_id_it_is_still_the_poll(): void
    {
        $all = $this->messages(3);

        $poll = $this->actingAs($this->me)->getJson(route('chat.messages', ['thread' => $this->thread, 'after_id' => $all[0]->id]))->assertOk();

        $this->assertSame([$all[1]->id, $all[2]->id], collect($poll->json())->pluck('id')->all());
        $poll->assertHeaderMissing('X-Has-More');
    }

    public function test_a_guest_and_a_missing_thread_are_turned_away(): void
    {
        $this->getJson(route('chat.messages', ['thread' => $this->thread, 'before_id' => 5]))->assertUnauthorized();
        $this->actingAs($this->me)->getJson('/chat/threads/999999/messages?before_id=5')->assertNotFound();
    }

    public function test_the_api_pages_backwards_too(): void
    {
        $all = $this->messages(40);
        Sanctum::actingAs($this->me);

        $res = $this->getJson("/api/threads/{$this->thread->id}/messages?before_id={$all[35]->id}&limit=10")->assertOk();

        $this->assertSame($all->slice(25, 10)->pluck('id')->values()->all(), collect($res->json('data'))->pluck('id')->all());
        $res->assertJsonPath('meta.has_more', true);
        $this->getJson("/api/threads/{$this->thread->id}/messages?before_id={$all[5]->id}&limit=10")->assertJsonPath('meta.has_more', false);
    }

    public function test_the_message_list_is_a_log_region_for_screen_readers(): void
    {
        $this->messages(2);

        $html = $this->actingAs($this->me)->get(route('chat.index', ['thread_id' => $this->thread->id]))->getContent();

        $this->assertMatchesRegularExpression('/id="chatBox"[^>]*role="log"[^>]*aria-live="polite"[^>]*aria-relevant="additions text"/s', $html, 'a new message is announced');
        $this->assertStringContainsString('id="lockedNotice" role="status"', $html, 'and a lock is announced too');
    }
}

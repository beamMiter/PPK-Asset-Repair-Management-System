<?php

namespace Tests\Feature\Chat;

use App\Events\Chat\ChatMessageSent;
use App\Models\ChatThread;
use App\Models\User;
use App\Services\ChatQuota;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Two limits on the chat, both for a hospital's own staff. What a person SENDS is limited against a flood (a burst is stopped in seconds,
 * a steady stream over minutes); how many new threads a person may START is limited to 5 a Thai day, and the page says how many are left.
 */
class ChatFloodAndQuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([ChatMessageSent::class]);
        config(['chat.message_burst_max' => 4, 'chat.message_burst_seconds' => 10, 'chat.message_sustained_max' => 6, 'chat.message_sustained_seconds' => 300]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function thread(User $author): ChatThread
    {
        return ChatThread::create(['title' => 'เครื่องพิมพ์ชั้น 2', 'author_id' => $author->id, 'is_locked' => false]);
    }

    private function send(User $who, ChatThread $thread, string $body = 'x')
    {
        return $this->actingAs($who)->postJson(route('chat.messages.store', $thread), ['body' => $body]);
    }

    // ---- messages: a flood ------------------------------------------------------------------------------------------

    public function test_a_burst_is_stopped_with_the_wait_and_the_words_are_not_saved(): void
    {
        $me = User::factory()->create(['role' => 'member']);
        $thread = $this->thread($me);

        foreach (range(1, 4) as $i) {
            $this->send($me, $thread, "ข้อความ {$i}")->assertCreated();
        }

        $res = $this->send($me, $thread, 'ข้อความที่ห้า')->assertStatus(429);
        $this->assertGreaterThan(0, (int) $res->headers->get('Retry-After'));
        $this->assertSame(4, $thread->messages()->count(), 'the refused one was not saved');
    }

    public function test_it_is_each_persons_own_allowance_and_it_comes_back_after_the_burst_window(): void
    {
        $me = User::factory()->create(['role' => 'member']);
        $other = User::factory()->create(['role' => 'member']);
        $thread = $this->thread($me);

        foreach (range(1, 4) as $i) {
            $this->send($me, $thread)->assertCreated();
        }
        $this->send($me, $thread)->assertStatus(429);
        $this->send($other, $thread)->assertCreated();

        $this->travel(11)->seconds();

        $this->send($me, $thread)->assertCreated();
    }

    public function test_a_steady_stream_is_stopped_too_after_the_sustained_limit(): void
    {
        $me = User::factory()->create(['role' => 'member']);
        $thread = $this->thread($me);

        foreach (range(1, 6) as $i) {   // 3 + 3, each burst allowed on its own
            $this->send($me, $thread)->assertCreated();
            if ($i === 3) {
                $this->travel(11)->seconds();
            }
        }
        $this->travel(11)->seconds();

        $this->send($me, $thread)->assertStatus(429);

        $this->travel(301)->seconds();
        $this->send($me, $thread)->assertCreated();
    }

    public function test_a_plain_form_post_is_sent_back_with_the_wait_in_a_toast(): void
    {
        $me = User::factory()->create(['role' => 'member']);
        $thread = $this->thread($me);
        foreach (range(1, 4) as $i) {
            $this->send($me, $thread)->assertCreated();
        }

        $this->actingAs($me)->from('/chat')->post(route('chat.messages.store', $thread), ['body' => 'x'])
            ->assertRedirect('/chat')
            ->assertSessionHas('toast.type', 'warning');
    }

    public function test_the_api_is_limited_the_same_way(): void
    {
        $me = User::factory()->create(['role' => 'member']);
        $thread = $this->thread($me);
        Sanctum::actingAs($me);

        foreach (range(1, 4) as $i) {
            $this->postJson("/api/threads/{$thread->id}/messages", ['body' => 'x'])->assertCreated();
        }
        $this->postJson("/api/threads/{$thread->id}/messages", ['body' => 'x'])->assertStatus(429);
    }

    // ---- threads: so many a day ---------------------------------------------------------------------------------------

    private function start(User $who, string $title = 'หัวข้อใหม่')
    {
        return $this->actingAs($who)->from('/chat')->post(route('chat.store'), ['title' => $title]);
    }

    public function test_five_new_threads_a_day_and_the_sixth_is_refused_in_thai(): void
    {
        config(['chat.thread_burst_max' => 100]);
        $me = User::factory()->create(['role' => 'member']);

        foreach (range(1, 5) as $i) {
            $this->start($me, "หัวข้อ {$i}")->assertSessionHas('toast.message', 'สร้างกระทู้ใหม่เรียบร้อยแล้ว');
        }
        $this->start($me, 'หัวข้อที่หก')->assertRedirect('/chat')->assertSessionHas('toast.type', 'warning');

        $this->assertSame('วันนี้คุณตั้งกระทู้ครบ 5 ครั้งแล้ว ตั้งกระทู้ใหม่ได้ตั้งแต่ 00:00 น. ของพรุ่งนี้', session('toast.message'));
        $this->assertSame(5, ChatThread::where('author_id', $me->id)->count());
    }

    public function test_a_deleted_thread_still_counts_and_each_person_has_their_own(): void
    {
        config(['chat.thread_burst_max' => 100]);
        $me = User::factory()->create(['role' => 'member']);
        $other = User::factory()->create(['role' => 'member']);

        foreach (range(1, 5) as $i) {
            $this->start($me);
        }
        ChatThread::where('author_id', $me->id)->get()->each->delete();

        $this->start($me)->assertSessionHas('toast.type', 'warning');
        $this->assertSame(0, ChatThread::where('author_id', $me->id)->count(), 'nothing new was made');
        $this->start($other);
        $this->assertSame(1, ChatThread::where('author_id', $other->id)->count());
    }

    public function test_admins_have_no_daily_limit(): void
    {
        config(['chat.thread_burst_max' => 100]);
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (range(1, 8) as $i) {
            $this->start($admin)->assertSessionHas('toast.message', 'สร้างกระทู้ใหม่เรียบร้อยแล้ว');
        }
        $this->assertSame(8, ChatThread::where('author_id', $admin->id)->count());
        $this->assertTrue(ChatQuota::for($admin)['unlimited']);
    }

    public function test_a_double_click_that_makes_the_same_thread_twice_is_stopped_by_the_short_limit(): void
    {
        config(['chat.thread_burst_max' => 2, 'chat.thread_burst_seconds' => 60]);
        $me = User::factory()->create(['role' => 'member']);

        $this->start($me)->assertSessionHas('toast.message', 'สร้างกระทู้ใหม่เรียบร้อยแล้ว');
        $this->start($me)->assertSessionHas('toast.message', 'สร้างกระทู้ใหม่เรียบร้อยแล้ว');
        $this->start($me)->assertSessionHas('toast.type', 'warning');   // "ส่งคำขอถี่เกินไป …"
        $this->assertSame(2, ChatThread::where('author_id', $me->id)->count());
    }

    public function test_the_day_is_the_thai_day_whatever_the_servers_clock_is(): void
    {
        config(['chat.thread_burst_max' => 100]);
        $original = config('app.timezone');
        config(['app.timezone' => 'UTC']);
        date_default_timezone_set('UTC');

        try {
            $me = User::factory()->create(['role' => 'member']);

            // 23:30 in Thailand = 16:30 UTC: five threads, and the day is full
            Carbon::setTestNow(Carbon::create(2026, 9, 26, 16, 30, 0, 'UTC'));
            foreach (range(1, 5) as $i) {
                $this->start($me);
            }
            $this->start($me)->assertSessionHas('toast.type', 'warning');
            $this->assertSame(0, ChatQuota::for($me)['remaining']);

            // 00:30 in Thailand the next day = 17:30 UTC, still the same UTC date: a new Thai day
            Carbon::setTestNow(Carbon::create(2026, 9, 26, 17, 30, 0, 'UTC'));
            $this->assertSame(5, ChatQuota::for($me)['remaining'], 'the count starts again at 00:00 น.');
            $this->start($me);
            $this->assertSame(6, ChatThread::where('author_id', $me->id)->count());
            $this->assertSame(4, ChatQuota::for($me)['remaining']);
        } finally {
            config(['app.timezone' => $original]);
            date_default_timezone_set($original);
        }
    }

    public function test_the_api_says_429_with_the_wait_and_how_many_are_left(): void
    {
        config(['chat.thread_burst_max' => 100]);
        $me = User::factory()->create(['role' => 'member']);
        Sanctum::actingAs($me);

        foreach (range(1, 5) as $i) {
            $this->postJson('/api/threads', ['title' => "หัวข้อ {$i}"])->assertCreated();
        }
        $res = $this->postJson('/api/threads', ['title' => 'หัวข้อที่หก'])->assertStatus(429)
            ->assertJsonPath('code', 'chat_thread_daily_limit')
            ->assertJsonPath('quota.remaining', 0)
            ->assertJsonPath('quota.limit', 5);
        $this->assertGreaterThan(0, (int) $res->headers->get('Retry-After'));

        $this->getJson('/api/threads')->assertOk()->assertJsonPath('meta.thread_quota.used', 5);
    }

    // ---- what the page says -------------------------------------------------------------------------------------------

    private function page(User $who): string
    {
        return $this->actingAs($who)->get(route('chat.index'))->assertOk()->getContent();
    }

    /** The counter on the board itself (not the create dialog): its sentence, the "3/5", the class list of the fraction, and how many dots are filled. */
    private function quotaNote(User $who): array
    {
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $this->page($who));
        libxml_clear_errors();
        $note = $dom->getElementById('threadQuotaNote');
        $count = $dom->getElementById('threadQuotaCount');
        $this->assertNotNull($note, 'the board carries a counter of the threads of the day');
        $this->assertNotNull($count);

        $dots = [];
        foreach ((new \DOMXPath($dom))->query('//*[@id="threadQuotaNote"]//span[contains(@class, "rounded-full")]') as $dot) {
            $dots[] = str_contains($dot->getAttribute('class'), 'bg-slate-200') ? 0 : 1;
        }

        return [preg_replace('/\s+/u', ' ', trim($note->textContent)), preg_replace('/\s+/u', '', $count->textContent), $count->getAttribute('class'), $dots];
    }

    public function test_the_board_shows_how_many_of_todays_threads_are_left_as_a_fraction(): void
    {
        config(['chat.thread_burst_max' => 100]);
        $me = User::factory()->create(['role' => 'member']);

        [$text, $count, $class, $dots] = $this->quotaNote($me);
        $this->assertStringContainsString('จำนวนการตั้งกระทู้ของคุณวันนี้คงเหลือ', $text);
        $this->assertSame('5/5', $count);
        $this->assertSame([1, 1, 1, 1, 1], $dots, 'one dot a thread, all still there');
        $this->assertStringNotContainsString('amber', $class, 'plenty left: no warning colour');
        $this->assertStringNotContainsString('rose', $class);

        $this->start($me);
        $this->start($me);
        [, $count, , $dots] = $this->quotaNote($me);
        $this->assertSame('3/5', $count);
        $this->assertSame([1, 1, 1, 0, 0], $dots, 'two used');
        $this->assertStringContainsString('วันนี้ตั้งกระทู้ได้อีก 3 จาก 5 ครั้ง', $this->page($me), 'the dialog still says it in words, with when it starts again');
    }

    public function test_the_fraction_is_bold_but_the_size_of_the_words_beside_it(): void
    {
        $html = $this->page(User::factory()->create(['role' => 'member']));
        $this->assertSame(1, preg_match('/id="threadQuotaNote"[^>]*class="[^"]*(text-\[[\d.]+px\])/', $html, $box), 'the size is set once, on the counter');

        [, , $class] = $this->quotaNote(User::factory()->create(['role' => 'member']));
        $this->assertStringContainsString('font-bold', $class);
        $this->assertDoesNotMatchRegularExpression('/text-\[\d/', str_replace('text-[#', '', $class), 'no size of its own: it takes the words\' size');
        $this->assertSame('text-[12.5px]', $box[1]);
    }

    public function test_the_counter_turns_amber_for_the_last_one(): void
    {
        config(['chat.thread_burst_max' => 100]);
        $me = User::factory()->create(['role' => 'member']);
        foreach (range(1, 4) as $i) {
            $this->start($me);
        }

        [, $count, $class, $dots] = $this->quotaNote($me);

        $this->assertSame('1/5', $count);
        $this->assertSame([1, 0, 0, 0, 0], $dots);
        $this->assertStringContainsString('text-amber-600', $class);
    }

    public function test_the_number_on_the_board_follows_the_setting(): void
    {
        config(['chat.threads_per_day' => 3]);

        [, $count, , $dots] = $this->quotaNote(User::factory()->create(['role' => 'member']));
        $this->assertSame('3/3', $count);
        $this->assertSame([1, 1, 1], $dots);

        config(['chat.threads_per_day' => 30]);
        [, $count, , $dots] = $this->quotaNote(User::factory()->create(['role' => 'member']));
        $this->assertSame('30/30', $count);
        $this->assertSame([], $dots, 'thirty dots would be a bar of noise: the fraction alone');
    }

    public function test_when_none_is_left_the_button_is_off_and_the_counter_says_when_it_starts_again(): void
    {
        config(['chat.thread_burst_max' => 100]);
        $me = User::factory()->create(['role' => 'member']);
        foreach (range(1, 5) as $i) {
            $this->start($me);
        }

        [$text, $count, $class, $dots] = $this->quotaNote($me);

        $this->assertSame('0/5', $count);
        $this->assertSame([0, 0, 0, 0, 0], $dots);
        $this->assertStringContainsString('ตั้งกระทู้ใหม่ได้ตั้งแต่ 00:00 น. ของพรุ่งนี้', $text);
        $this->assertStringContainsString('text-rose-600', $class);
        $this->assertMatchesRegularExpression('/<button[^>]*disabled[^>]*title="วันนี้คุณตั้งกระทู้ครบ 5 ครั้งแล้ว/s', $this->page($me));
    }

    public function test_an_admin_sees_the_counter_too_and_it_says_no_limit(): void
    {
        [$text, $count, $class, $dots] = $this->quotaNote(User::factory()->create(['role' => 'admin']));

        $this->assertStringContainsString('จำนวนการตั้งกระทู้ของคุณวันนี้คงเหลือ', $text);
        $this->assertSame('ไม่จำกัด', $count);
        $this->assertSame([], $dots, 'nothing to count down');
        $this->assertStringNotContainsString('amber', $class);
    }

    public function test_the_counter_sits_in_the_list_header_above_the_search(): void
    {
        $html = $this->page(User::factory()->create(['role' => 'member']));

        $this->assertLessThan(strpos($html, 'placeholder="ค้นหากระทู้..."'), strpos($html, 'id="threadQuotaNote"'));
        $this->assertLessThan(strpos($html, 'id="threadQuotaNote"'), strpos($html, 'กระดานสนทนา</h1>'));
    }
}

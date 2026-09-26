<?php

namespace Tests\Feature\Ui;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * The chat said "You", "Send a message", "My Topics", "Go All topics", "Monday 3:45pm" among Thai. Its words are Thai now; a message's
 * time is the Thai weekday and a 24-hour clock, on the page and in the message that arrives live alike. A technical term stays English
 * on purpose (the owner's call): "Locked" on the badges, "Emoji" and its groups (Smileys, Hands & Hearts, Tasks & Objects), "Live Chat", "refresh".
 */
class ChatIsThaiTest extends TestCase
{
    use RefreshDatabase;

    private const ENGLISH = ['>You<', 'Unknown user', 'placeholder="Send a message"', 'My Topics', 'All topics', 'Send a message to start'];

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([ChatMessageSent::class]);
    }

    private function threadWithTalk(bool $locked): array
    {
        $me = User::factory()->create(['role' => 'it_support']);
        $other = User::factory()->create(['role' => 'it_support', 'name' => 'วรรณา ใจดี']);
        $thread = ChatThread::create(['title' => 'เครื่องพิมพ์ชั้น 2', 'author_id' => $other->id, 'is_locked' => $locked]);
        $at = now()->setDate(2026, 9, 26)->setTime(15, 45);
        foreach ([[$me, 'ของฉัน'], [$other, 'ของเขา']] as [$who, $body]) {
            $m = new ChatMessage(['chat_thread_id' => $thread->id, 'user_id' => $who->id, 'body' => $body]);
            $m->created_at = $at;
            $m->updated_at = $at;
            $m->save();
        }

        return [$me, $thread];
    }

    public function test_a_thread_reads_in_thai(): void
    {
        [$me, $thread] = $this->threadWithTalk(false);
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::create(2026, 9, 28, 10, 0, 0, 'Asia/Bangkok'));   // two days after the messages

        $html = $this->actingAs($me)->get(route('chat.index', ['thread_id' => $thread->id]))->assertOk()->getContent();
        \Illuminate\Support\Carbon::setTestNow();

        foreach (['>คุณ<', 'placeholder="พิมพ์ข้อความ..."', 'title="Emoji"', "'Smileys'", "'Hands & Hearts'", "'Tasks & Objects'"] as $thai) {
            $this->assertStringContainsString($thai, $html, $thai);
        }
        $this->assertStringContainsString('วันเสาร์ 15:45', $html, 'the weekday and the 24-hour clock, in Thai');
        $this->assertDoesNotMatchRegularExpression('/(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday) \d{1,2}:\d{2}(am|pm)/', $html);
        foreach (self::ENGLISH as $english) {
            $this->assertStringNotContainsString($english, $html, $english);
        }
    }

    /** "Lock" is a technical term the team uses as it is: the badges keep saying Locked (the sentences around them are Thai). */
    public function test_the_locked_badges_keep_the_technical_term(): void
    {
        [$me, $thread] = $this->threadWithTalk(true);

        $html = $this->actingAs($me)->get(route('chat.index', ['thread_id' => $thread->id]))->assertOk()->getContent();

        $this->assertGreaterThanOrEqual(2, substr_count($html, '>Locked<'), 'the badge in the list and the one over the thread');
        $this->assertStringNotContainsString('>ล็อกแล้ว<', $html);
        $this->assertStringContainsString('กระทู้นี้ถูกล็อก ไม่สามารถส่งข้อความใหม่ได้', $html, 'the notice is a sentence: Thai');
    }

    public function test_an_empty_thread_and_no_thread_chosen_read_in_thai(): void
    {
        $me = User::factory()->create(['role' => 'it_support']);
        $thread = ChatThread::create(['title' => 'ว่าง', 'author_id' => $me->id, 'is_locked' => false]);

        $empty = $this->actingAs($me)->get(route('chat.index', ['thread_id' => $thread->id]))->assertOk()->getContent();
        $this->assertStringContainsString('เริ่มการสนทนา', $empty);
        $this->assertStringContainsString('ส่งข้อความเพื่อเริ่มการสนทนา', $empty);
        $this->assertStringNotContainsString('Send a message to start', $empty);

        $this->actingAs($me)->get(route('chat.index'))->assertOk()->assertSee('ยินดีต้อนรับสู่กระดานสนทนา');
    }

    public function test_the_drawer_reads_in_thai(): void
    {
        $me = User::factory()->create(['role' => 'it_support']);

        $html = $this->actingAs($me)->get(route('maintenance.requests.index'))->assertOk()->getContent();

        $this->assertStringContainsString('กระทู้ที่มีส่วนร่วม', $html);
        $this->assertStringContainsString('ไปที่กระทู้ทั้งหมด', $html);
        $this->assertStringNotContainsString('My Topics', $html);
        $this->assertStringNotContainsString('All topics', $html);
    }

    public function test_a_locked_thread_refuses_a_message_in_thai(): void
    {
        [$me, $thread] = $this->threadWithTalk(true);

        $this->actingAs($me)->postJson(route('chat.messages.store', $thread), ['body' => 'x'])
            ->assertForbidden()
            ->assertJsonPath('message', 'กระทู้นี้ถูกล็อก ไม่สามารถส่งข้อความได้');
    }

    public function test_the_live_message_script_has_no_english_words_left(): void
    {
        $js = file_get_contents(resource_path('js/chat/page.js'));

        $this->assertStringNotContainsString("'You'", $js);
        $this->assertStringNotContainsString("'Unknown'", $js);
        $this->assertStringNotContainsString("'en-US'", $js);
        $this->assertStringContainsString('chatTime(m.created_at', $js, 'the time the server stamped, on the Thai clock');
        $this->assertStringNotContainsString('toLocaleString', $js);
        $time = file_get_contents(resource_path('js/chat/time.js'));
        $this->assertStringContainsString("const TZ = 'Asia/Bangkok'", $time);
        $this->assertStringContainsString("hourCycle: 'h23'", $time);
    }
}

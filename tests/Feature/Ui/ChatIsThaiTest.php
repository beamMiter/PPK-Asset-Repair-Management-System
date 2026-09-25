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
 * The chat said "You", "Locked", "Send a message", "Emoji", "My Topics", "Go All topics", "Monday 3:45pm" among Thai. Its words are Thai now
 * (a technical term may stay English, such as "Live Chat"); a message's time is the Thai weekday and a 24-hour clock, on the page and in
 * the message that arrives live alike.
 */
class ChatIsThaiTest extends TestCase
{
    use RefreshDatabase;

    private const ENGLISH = ['>You<', '>Locked<', 'Unknown user', 'placeholder="Send a message"', 'title="Emoji"', 'My Topics', 'All topics', 'Smileys', 'Hands &amp; Hearts', 'Tasks &amp; Objects', 'Send a message to start'];

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

        $html = $this->actingAs($me)->get(route('chat.index', ['thread_id' => $thread->id]))->assertOk()->getContent();

        foreach (['>คุณ<', 'placeholder="พิมพ์ข้อความ..."', 'title="อีโมจิ"', "'หน้ายิ้ม'", "'มือและหัวใจ'", "'งานและสิ่งของ'"] as $thai) {
            $this->assertStringContainsString($thai, $html, $thai);
        }
        $this->assertStringContainsString('วันเสาร์ 15:45', $html, 'the weekday and the 24-hour clock, in Thai');
        $this->assertDoesNotMatchRegularExpression('/(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday) \d{1,2}:\d{2}(am|pm)/', $html);
        foreach (self::ENGLISH as $english) {
            $this->assertStringNotContainsString($english, $html, $english);
        }
    }

    public function test_a_locked_thread_and_the_list_say_locked_in_thai(): void
    {
        [$me, $thread] = $this->threadWithTalk(true);

        $html = $this->actingAs($me)->get(route('chat.index', ['thread_id' => $thread->id]))->assertOk()->getContent();

        $this->assertGreaterThanOrEqual(2, substr_count($html, 'ล็อกแล้ว'), 'the badge in the list and the one over the thread');
        $this->assertStringNotContainsString('>Locked<', $html);
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

        $this->assertStringContainsString('กระทู้ของฉัน', $html);
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
        $this->assertStringContainsString("toLocaleString('th-TH', TIME_FORMAT)", $js);
        $this->assertStringContainsString("hourCycle: 'h23'", $js);
    }
}

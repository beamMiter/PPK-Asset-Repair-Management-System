<?php

namespace Tests\Feature\Chat;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The chat widget on every page asks /chat/my-updates every 30 seconds, for every signed-in person. For each thread that the person has a read
 * pointer in, the unread count was a query of its own, so one poll cost up to 15 (web) or 30 (API) more queries than it needed to, and the cost
 * grew with the threads. The counts now come from one query for the whole list (the one the chat page's list already uses).
 */
class ChatWidgetQueryCountTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->me = User::factory()->create(['role' => 'it_support']);
        $this->other = User::factory()->create(['role' => 'it_support']);
    }

    private function say(ChatThread $thread, string $body, ?User $who = null): ChatMessage
    {
        return ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => ($who ?? $this->other)->id, 'body' => $body]);
    }

    private function pointer(ChatThread $thread, ?int $lastRead): void
    {
        DB::table('chat_thread_reads')->insert([
            'user_id' => $this->me->id, 'chat_thread_id' => $thread->id, 'last_read_message_id' => $lastRead,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Threads I started, each with three messages from somebody else and my read pointer after the first one: two unread in each. */
    private function threads(int $howMany): void
    {
        for ($i = 0; $i < $howMany; $i++) {
            $thread = ChatThread::create(['title' => "กระทู้ {$i}", 'author_id' => $this->me->id, 'is_locked' => false]);
            $first = $this->say($thread, 'หนึ่ง');
            $this->say($thread, 'สอง');
            $this->say($thread, 'สาม');
            $this->pointer($thread, $first->id);
        }
    }

    private function ask(string $road)
    {
        if ($road === 'api' || $road === 'list') {
            Sanctum::actingAs($this->me);

            return $this->getJson($road === 'api' ? '/api/chat/my-updates' : '/api/threads')->assertOk();
        }

        return $this->actingAs($this->me)->getJson(route('chat.my_updates'))->assertOk();
    }

    private function queries(string $road): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->ask($road);
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /** @return array<string,array{0:string}> */
    public static function roads(): array
    {
        return ['the web widget' => ['web'], 'the API widget' => ['api'], 'the API list' => ['list']];
    }

    #[DataProvider('roads')]
    public function test_a_poll_costs_the_same_queries_however_many_threads_there_are(string $road): void
    {
        $this->threads(2);
        $few = $this->queries($road);

        $this->threads(12);
        $many = $this->queries($road);

        $this->assertSame($few, $many, "{$few} queries with 2 threads, {$many} with 14: the cost grows with the threads");
    }

    #[DataProvider('roads')]
    public function test_the_counts_and_last_messages_are_still_right(string $road): void
    {
        $this->threads(1);                                                      // a pointer after the first of three: two unread

        $unopened = ChatThread::create(['title' => 'ยังไม่เปิด', 'author_id' => $this->me->id, 'is_locked' => false]);
        $this->say($unopened, 'a');
        $this->say($unopened, 'b');                                             // no pointer at all: everything is unread

        $allRead = ChatThread::create(['title' => 'อ่านหมดแล้ว', 'author_id' => $this->me->id, 'is_locked' => false]);
        $this->say($allRead, 'x');
        $this->pointer($allRead, $this->say($allRead, 'y')->id);                // the pointer is on the last message: none unread

        $withDeleted = ChatThread::create(['title' => 'มีข้อความที่ลบ', 'author_id' => $this->me->id, 'is_locked' => false]);
        $first = $this->say($withDeleted, 'เดิม');
        $this->say($withDeleted, 'ถูกลบ')->delete();                            // a deleted message is not unread
        $this->say($withDeleted, 'ล่าสุด');
        $this->pointer($withDeleted, $first->id);

        $json = $this->ask($road)->json();
        $byTitle = collect($json['data'] ?? $json)->keyBy('title');
        $unread = fn (string $title) => $byTitle[$title]['unread'] ?? $byTitle[$title]['unread_count'];   // the list names it unread_count
        $last = fn (string $title) => $byTitle[$title]['last_body'] ?? $byTitle[$title]['latest_message']['body'];

        $this->assertSame(2, $unread('กระทู้ 0'));
        $this->assertSame('สาม', $last('กระทู้ 0'));
        $this->assertSame(2, $unread('ยังไม่เปิด'));
        $this->assertSame(0, $unread('อ่านหมดแล้ว'));
        $this->assertSame(1, $unread('มีข้อความที่ลบ'));
        $this->assertSame('ล่าสุด', $last('มีข้อความที่ลบ'));
    }
}

<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A person can hide a thread from their own "กระทู้ที่มีส่วนร่วม" (the chat page's tab and the floating widget) without deleting it for
 * anyone. Hidden: read to the last message, out of the tab, its count, the widget and the badge; still in "ทั้งหมด" and readable. It
 * comes back when they write in the thread, or ask for it. And a locked thread they have read to the end leaves the WIDGET (only):
 * nobody can add to it, so there is nothing to catch up on.
 */
class ChatHideThreadTest extends TestCase
{
    use RefreshDatabase;

    private User $me;
    private User $other;
    private ChatThread $thread;      // I wrote in it; someone else wrote after me

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([ChatMessageSent::class]);

        $this->me = User::factory()->create(['role' => 'it_support']);
        $this->other = User::factory()->create(['role' => 'it_support']);
        $this->thread = ChatThread::create(['title' => 'เครื่องพิมพ์ชั้น 2', 'author_id' => $this->other->id, 'is_locked' => false]);
        $this->say($this->thread, $this->me, 'ผมตอบไว้');
        $this->say($this->thread, $this->other, 'ขอบคุณครับ');
    }

    private function say(ChatThread $thread, User $who, string $body): ChatMessage
    {
        return ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => $who->id, 'body' => $body]);
    }

    private function row(User $user, ?ChatThread $thread = null): ?object
    {
        return DB::table('chat_thread_reads')->where('user_id', $user->id)->where('chat_thread_id', ($thread ?? $this->thread)->id)->first();
    }

    private function inTab(?User $as = null): array
    {
        return $this->actingAs($as ?? $this->me)->get(route('chat.index', ['scope' => 'mine']))->viewData('threads')->pluck('id')->all();
    }

    private function inWidget(?User $as = null): array
    {
        return collect($this->actingAs($as ?? $this->me)->getJson(route('chat.my_updates'))->assertOk()->json())->pluck('id')->all();
    }

    // ---- hiding -------------------------------------------------------------------------------------------------

    public function test_hiding_takes_it_out_of_the_tab_its_count_and_the_widget_and_reads_it_to_the_end(): void
    {
        $this->assertContains($this->thread->id, $this->inTab());
        $this->assertContains($this->thread->id, $this->inWidget());

        $this->actingAs($this->me)->from(route('chat.index'))->post(route('chat.hide', $this->thread))
            ->assertRedirect(route('chat.index'))
            ->assertSessionHas('toast.type', 'success');

        $row = $this->row($this->me);
        $this->assertNotNull($row->hidden_at);
        $this->assertSame((int) $this->thread->messages()->max('id'), (int) $row->last_read_message_id, 'read to the last message: it stops counting');
        $this->assertNotContains($this->thread->id, $this->inTab());
        $this->assertNotContains($this->thread->id, $this->inWidget());
        $this->assertSame(0, $this->actingAs($this->me)->get(route('chat.index', ['scope' => 'mine']))->viewData('counts')['mine']);
    }

    public function test_it_answers_json_for_the_widget(): void
    {
        $this->actingAs($this->me)->postJson(route('chat.hide', $this->thread))
            ->assertOk()->assertJsonPath('hidden', true);

        $this->assertNotNull($this->row($this->me)->hidden_at);
    }

    public function test_it_is_still_in_all_and_still_opens_and_reads(): void
    {
        $this->actingAs($this->me)->post(route('chat.hide', $this->thread));

        $all = $this->actingAs($this->me)->get(route('chat.index'))->viewData('threads')->pluck('id')->all();
        $this->assertContains($this->thread->id, $all);
        $this->actingAs($this->me)->get(route('chat.index', ['thread_id' => $this->thread->id]))->assertOk()->assertSee('ขอบคุณครับ');
    }

    public function test_only_the_person_who_hid_it_stops_seeing_it(): void
    {
        $this->actingAs($this->me)->post(route('chat.hide', $this->thread));

        $this->assertContains($this->thread->id, $this->inTab($this->other));
        $this->assertContains($this->thread->id, $this->inWidget($this->other));
        $this->assertNull($this->row($this->other), 'nothing was written for anybody else');
    }

    public function test_the_author_can_hide_a_thread_they_started_too(): void
    {
        $this->actingAs($this->other)->postJson(route('chat.hide', $this->thread))->assertOk();

        $this->assertNotContains($this->thread->id, $this->inWidget($this->other));
    }

    public function test_a_person_with_no_part_in_it_has_nothing_to_hide(): void
    {
        $stranger = User::factory()->create(['role' => 'it_support']);

        $this->actingAs($stranger)->postJson(route('chat.hide', $this->thread))->assertStatus(422)->assertJsonPath('hidden', false);
        $this->actingAs($stranger)->from(route('chat.index'))->post(route('chat.hide', $this->thread))->assertSessionHas('toast.type', 'warning');

        $this->assertNull($this->row($stranger), 'no junk row');
    }

    public function test_hiding_twice_is_harmless(): void
    {
        $this->actingAs($this->me)->postJson(route('chat.hide', $this->thread))->assertOk();
        $this->actingAs($this->me)->postJson(route('chat.hide', $this->thread))->assertOk();

        $this->assertSame(1, DB::table('chat_thread_reads')->where('user_id', $this->me->id)->where('chat_thread_id', $this->thread->id)->count());
    }

    public function test_a_guest_cannot_hide(): void
    {
        $this->postJson(route('chat.hide', $this->thread))->assertUnauthorized();
    }

    // ---- coming back --------------------------------------------------------------------------------------------

    public function test_reading_it_does_not_bring_it_back_but_writing_in_it_does(): void
    {
        $this->actingAs($this->me)->post(route('chat.hide', $this->thread));

        $this->actingAs($this->me)->get(route('chat.index', ['thread_id' => $this->thread->id]))->assertOk();
        $this->assertNotNull($this->row($this->me)->hidden_at, 'opening it is only reading');
        $this->assertNotContains($this->thread->id, $this->inTab());

        $this->actingAs($this->me)->post(route('chat.messages.store', $this->thread), ['body' => 'ขอเพิ่มเติมครับ']);

        $this->assertNull($this->row($this->me)->hidden_at);
        $this->assertContains($this->thread->id, $this->inTab());
        $this->assertContains($this->thread->id, $this->inWidget());
    }

    public function test_someone_else_writing_does_not_bring_it_back_and_does_not_ring_the_badge(): void
    {
        $this->actingAs($this->me)->post(route('chat.hide', $this->thread));

        $this->say($this->thread, $this->other, 'มีอะไรใหม่');

        $this->assertNotContains($this->thread->id, $this->inWidget(), 'a hidden thread is not in the widget, so it adds nothing to the badge');
        $this->assertNotNull($this->row($this->me)->hidden_at);
    }

    public function test_asking_for_it_back_shows_it_again(): void
    {
        $this->actingAs($this->me)->post(route('chat.hide', $this->thread));

        $this->actingAs($this->me)->from(route('chat.index'))->delete(route('chat.unhide', $this->thread))
            ->assertRedirect(route('chat.index'))->assertSessionHas('toast.type', 'success');

        $this->assertNull($this->row($this->me)->hidden_at);
        $this->assertContains($this->thread->id, $this->inTab());
        $this->assertContains($this->thread->id, $this->inWidget());
        $this->actingAs($this->me)->deleteJson(route('chat.unhide', $this->thread))->assertOk()->assertJsonPath('hidden', false);
    }

    public function test_unlocking_a_thread_does_not_bring_back_what_a_person_hid(): void
    {
        $this->thread->update(['is_locked' => true]);
        $this->actingAs($this->me)->post(route('chat.hide', $this->thread));
        $this->thread->update(['is_locked' => false]);

        $this->assertNotContains($this->thread->id, $this->inWidget());
    }

    // ---- locked and read ----------------------------------------------------------------------------------------

    public function test_a_locked_thread_read_to_the_end_leaves_the_widget_but_stays_in_the_tab(): void
    {
        $this->thread->update(['is_locked' => true]);

        // I have not read the last message yet: it is still worth a look
        $this->assertContains($this->thread->id, $this->inWidget());

        $this->actingAs($this->me)->get(route('chat.index', ['thread_id' => $this->thread->id]))->assertOk();   // reading it

        $this->assertNotContains($this->thread->id, $this->inWidget());
        $this->assertContains($this->thread->id, $this->inTab(), 'the page still lists it');
    }

    public function test_an_unlocked_thread_stays_in_the_widget_when_read_and_a_reopened_one_returns(): void
    {
        $this->actingAs($this->me)->get(route('chat.index', ['thread_id' => $this->thread->id]))->assertOk();
        $this->assertContains($this->thread->id, $this->inWidget(), 'read but open');

        $this->thread->update(['is_locked' => true]);
        $this->assertNotContains($this->thread->id, $this->inWidget());

        $this->thread->update(['is_locked' => false]);
        $this->assertContains($this->thread->id, $this->inWidget(), 'reopened');
    }

    public function test_a_locked_thread_with_a_message_i_have_not_read_stays_until_i_read_it(): void
    {
        $this->actingAs($this->me)->get(route('chat.index', ['thread_id' => $this->thread->id]));
        $this->thread->update(['is_locked' => true]);
        $this->assertNotContains($this->thread->id, $this->inWidget());

        $this->say($this->thread, $this->other, 'ข้อความสุดท้ายก่อนล็อก');   // written, then locked

        $this->assertContains($this->thread->id, $this->inWidget());
    }

    // ---- what the page and the widget carry ---------------------------------------------------------------------

    public function test_the_widget_rows_carry_the_hide_url(): void
    {
        $items = $this->actingAs($this->me)->getJson(route('chat.my_updates'))->assertOk()->json();

        $this->assertSame(route('chat.hide', $this->thread), $items[0]['hide_url']);
    }

    public function test_the_header_offers_hide_to_a_participant_show_again_to_who_hid_it_and_nothing_to_a_stranger(): void
    {
        $open = fn (User $u) => $this->actingAs($u)->get(route('chat.index', ['thread_id' => $this->thread->id]))->assertOk()->getContent();

        $participant = $open($this->me);
        $this->assertStringContainsString('ซ่อนจากกระทู้ที่มีส่วนร่วม', $participant);
        $this->assertStringContainsString('action="' . route('chat.hide', $this->thread) . '"', $participant);
        $this->assertStringNotContainsString('แสดงในกระทู้ที่มีส่วนร่วมอีกครั้ง', $participant);

        $this->actingAs($this->me)->post(route('chat.hide', $this->thread));
        $hidden = $open($this->me);
        $this->assertStringContainsString('แสดงในกระทู้ที่มีส่วนร่วมอีกครั้ง', $hidden);
        $this->assertStringContainsString('action="' . route('chat.unhide', $this->thread) . '"', $hidden);
        $this->assertStringNotContainsString('title="ซ่อนจากกระทู้ที่มีส่วนร่วม"', $hidden);

        $stranger = $open(User::factory()->create(['role' => 'it_support']));
        $this->assertStringNotContainsString('ซ่อนจากกระทู้ที่มีส่วนร่วม', $stranger);
        $this->assertStringNotContainsString('แสดงในกระทู้ที่มีส่วนร่วมอีกครั้ง', $stranger);
    }

    // ---- the API ------------------------------------------------------------------------------------------------

    public function test_the_api_hides_and_shows_again_and_says_which_threads_are_hidden(): void
    {
        Sanctum::actingAs($this->me);

        $this->postJson("/api/threads/{$this->thread->id}/hide")->assertOk()->assertJsonPath('hidden', true);

        $index = collect($this->getJson('/api/threads')->assertOk()->json('data'))->firstWhere('id', $this->thread->id);
        $this->assertTrue($index['hidden_by_me']);
        $this->assertNotContains($this->thread->id, collect($this->getJson('/api/threads?scope=mine')->json('data'))->pluck('id')->all());
        $this->assertNotContains($this->thread->id, collect($this->getJson('/api/chat/my-updates')->json())->pluck('id')->all());

        $this->deleteJson("/api/threads/{$this->thread->id}/hide")->assertOk()->assertJsonPath('hidden', false);

        $this->assertFalse(collect($this->getJson('/api/threads')->json('data'))->firstWhere('id', $this->thread->id)['hidden_by_me']);
        $this->assertContains($this->thread->id, collect($this->getJson('/api/threads?scope=mine')->json('data'))->pluck('id')->all());
    }

    public function test_the_api_refuses_a_stranger_and_writing_through_it_brings_the_thread_back(): void
    {
        $stranger = User::factory()->create(['role' => 'it_support']);
        Sanctum::actingAs($stranger);
        $this->postJson("/api/threads/{$this->thread->id}/hide")->assertStatus(422);

        Sanctum::actingAs($this->me);
        $this->postJson("/api/threads/{$this->thread->id}/hide")->assertOk();
        $this->postJson("/api/threads/{$this->thread->id}/messages", ['body' => 'กลับมาแล้ว'])->assertCreated();

        $this->assertNull($this->row($this->me)->hidden_at);
    }
}

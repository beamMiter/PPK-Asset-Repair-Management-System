<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Deleting a thread (it is hidden from everyone) is for the person who started it and for an admin. Everybody else who took part
 * has the other way out: hide it from their own list (ChatHideThreadTest) - which is enough for them.
 */
class ChatDeleteThreadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([ChatMessageSent::class]);
    }

    /** a thread started by $author, written in by $participant too */
    private function thread(User $author, ?User $participant = null): ChatThread
    {
        $thread = ChatThread::create(['title' => 'เครื่องพิมพ์ชั้น 2', 'author_id' => $author->id, 'is_locked' => false]);
        ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => $author->id, 'body' => 'เริ่ม']);
        if ($participant) {
            ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => $participant->id, 'body' => 'ตอบ']);
        }

        return $thread;
    }

    public function test_whoever_started_a_thread_can_delete_it_whatever_their_role(): void
    {
        foreach (['member', 'it_support', 'technician'] as $role) {
            $author = User::factory()->create(['role' => $role]);
            $thread = $this->thread($author, User::factory()->create(['role' => 'it_support']));

            $this->actingAs($author)->delete(route('chat.destroy', $thread))
                ->assertRedirect(route('chat.index'))
                ->assertSessionHas('toast.type', 'success');

            $this->assertSoftDeleted('chat_threads', ['id' => $thread->id]);
        }
    }

    public function test_an_admin_can_delete_any_thread(): void
    {
        $thread = $this->thread(User::factory()->create(['role' => 'member']));

        $this->actingAs(User::factory()->create(['role' => 'admin']))->delete(route('chat.destroy', $thread))->assertRedirect(route('chat.index'));

        $this->assertSoftDeleted('chat_threads', ['id' => $thread->id]);
    }

    public function test_somebody_who_only_took_part_cannot_delete_it_and_is_told_in_thai(): void
    {
        foreach (['member', 'it_support', 'supervisor'] as $role) {
            $participant = User::factory()->create(['role' => $role]);
            $thread = $this->thread(User::factory()->create(['role' => 'member']), $participant);

            $this->actingAs($participant)->from('/x')->delete(route('chat.destroy', $thread))->assertRedirect('/x');

            $this->assertSame('error', session('toast.type'), $role);
            $this->assertSame('เฉพาะเจ้าของกระทู้และผู้ดูแลระบบเท่านั้นที่ลบกระทู้ได้', session('toast.message'));
            $this->assertNotSoftDeleted('chat_threads', ['id' => $thread->id]);
        }
    }

    public function test_the_rule_is_one_method(): void
    {
        $author = User::factory()->create(['role' => 'member']);
        $thread = $this->thread($author);

        $this->assertTrue($thread->canBeDeletedBy($author));
        $this->assertTrue($thread->canBeDeletedBy(User::factory()->create(['role' => 'admin'])));
        $this->assertFalse($thread->canBeDeletedBy(User::factory()->create(['role' => 'it_support'])));
        $this->assertFalse($thread->canBeDeletedBy(null));
    }

    // ---- what the page offers -----------------------------------------------------------------------------------

    private function header(User $viewer, ChatThread $thread): string
    {
        return $this->actingAs($viewer)->get(route('chat.index', ['thread_id' => $thread->id]))->assertOk()->getContent();
    }

    public function test_the_delete_button_and_its_dialog_are_for_the_owner_and_admins_only(): void
    {
        $author = User::factory()->create(['role' => 'member']);
        $participant = User::factory()->create(['role' => 'it_support']);
        $thread = $this->thread($author, $participant);

        foreach ([$author, User::factory()->create(['role' => 'admin'])] as $who) {
            $html = $this->header($who, $thread);
            $this->assertStringContainsString('title="ลบกระทู้"', $html);
            $this->assertStringContainsString('id="hidden-delete-thread"', $html);
            $this->assertStringContainsString('ยืนยันการลบกระทู้', $html);
        }

        $html = $this->header($participant, $thread);
        $this->assertStringNotContainsString('title="ลบกระทู้"', $html);
        $this->assertStringNotContainsString('id="hidden-delete-thread"', $html);
        $this->assertStringNotContainsString('ยืนยันการลบกระทู้', $html);
    }

    public function test_a_participant_who_is_not_the_owner_is_offered_hide_instead(): void
    {
        $participant = User::factory()->create(['role' => 'it_support']);
        $thread = $this->thread(User::factory()->create(['role' => 'member']), $participant);

        $html = $this->header($participant, $thread);

        $this->assertStringContainsString('title="ซ่อนจากกระทู้ที่มีส่วนร่วม"', $html);
        $this->assertStringNotContainsString('title="ลบกระทู้"', $html);
    }

    public function test_an_owner_who_is_a_plain_member_still_gets_the_button_and_no_lock(): void
    {
        $author = User::factory()->create(['role' => 'member']);
        $thread = $this->thread($author);

        $html = $this->header($author, $thread);

        $this->assertStringContainsString('title="ลบกระทู้"', $html);
        $this->assertStringNotContainsString('title="ล็อกกระทู้"', $html, 'deleting is not locking: a member still cannot lock');
    }
}

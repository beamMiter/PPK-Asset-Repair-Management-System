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
 * Who may lock (and unlock) a chat thread: whoever started it - a plain member too, since it is theirs - and any signed-in staff member,
 * admins among them. Somebody who only took part in a thread another person started may not.
 */
class ChatLockPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([ChatMessageSent::class]);
    }

    private function thread(User $author, bool $locked = false): ChatThread
    {
        $thread = ChatThread::create(['title' => 'เครื่องพิมพ์ชั้น 2', 'author_id' => $author->id, 'is_locked' => $locked]);
        ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => $author->id, 'body' => 'เริ่ม']);

        return $thread;
    }

    private function locked(ChatThread $thread): bool
    {
        return (bool) $thread->fresh()->is_locked;
    }

    public function test_the_person_who_started_a_thread_can_lock_and_unlock_it_whatever_their_role(): void
    {
        foreach (['member', 'it_support', 'technician', 'supervisor', 'admin'] as $role) {
            $owner = User::factory()->create(['role' => $role]);
            $thread = $this->thread($owner);

            $this->actingAs($owner)->post(route('chat.lock', $thread))->assertRedirect()->assertSessionHas('toast.type', 'success');
            $this->assertTrue($this->locked($thread), "$role locks");

            $this->actingAs($owner)->post(route('chat.unlock', $thread))->assertRedirect()->assertSessionHas('toast.type', 'success');
            $this->assertFalse($this->locked($thread), "$role unlocks");
        }
    }

    public function test_an_admin_can_lock_and_unlock_somebody_elses_thread(): void
    {
        $thread = $this->thread(User::factory()->create(['role' => 'member']));
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('chat.lock', $thread))->assertRedirect();
        $this->assertTrue($this->locked($thread));

        $this->actingAs($admin)->post(route('chat.unlock', $thread))->assertRedirect();
        $this->assertFalse($this->locked($thread));
    }

    public function test_staff_who_did_not_start_it_still_can_as_before(): void
    {
        $thread = $this->thread(User::factory()->create(['role' => 'member']));

        $this->actingAs(User::factory()->create(['role' => 'it_support']))->post(route('chat.lock', $thread))->assertRedirect();

        $this->assertTrue($this->locked($thread));
    }

    public function test_a_member_who_only_took_part_cannot_lock_or_unlock_it(): void
    {
        $owner = User::factory()->create(['role' => 'it_support']);
        $member = User::factory()->create(['role' => 'member']);
        $open = $this->thread($owner);
        ChatMessage::create(['chat_thread_id' => $open->id, 'user_id' => $member->id, 'body' => 'ตอบ']);
        $shut = $this->thread($owner, true);

        $this->actingAs($member)->post(route('chat.lock', $open))->assertForbidden();
        $this->actingAs($member)->post(route('chat.unlock', $shut))->assertForbidden();

        $this->assertFalse($this->locked($open));
        $this->assertTrue($this->locked($shut));
    }

    public function test_the_api_follows_the_same_rule(): void
    {
        $owner = User::factory()->create(['role' => 'member']);
        $thread = $this->thread($owner);

        Sanctum::actingAs($owner);
        $this->postJson("/api/threads/{$thread->id}/lock")->assertOk()->assertJsonPath('is_locked', true);
        $this->postJson("/api/threads/{$thread->id}/unlock")->assertOk()->assertJsonPath('is_locked', false);

        Sanctum::actingAs(User::factory()->create(['role' => 'member']));
        $this->postJson("/api/threads/{$thread->id}/lock")->assertForbidden();
        $this->assertFalse($this->locked($thread));
    }

    public function test_the_rule_is_one_method(): void
    {
        $owner = User::factory()->create(['role' => 'member']);
        $thread = $this->thread($owner);

        $this->assertTrue($thread->canBeLockedBy($owner));
        $this->assertTrue($thread->canBeLockedBy(User::factory()->create(['role' => 'admin'])));
        $this->assertTrue($thread->canBeLockedBy(User::factory()->create(['role' => 'technician'])));
        $this->assertFalse($thread->canBeLockedBy(User::factory()->create(['role' => 'member'])));
        $this->assertFalse($thread->canBeLockedBy(null));
    }

    // ---- what the page offers -----------------------------------------------------------------------------------

    private function html(User $viewer, ChatThread $thread): string
    {
        return $this->actingAs($viewer)->get(route('chat.index', ['thread_id' => $thread->id]))->assertOk()->getContent();
    }

    public function test_the_lock_button_is_shown_to_the_owner_and_to_staff_but_not_to_a_member_who_only_took_part(): void
    {
        $owner = User::factory()->create(['role' => 'member']);
        $thread = $this->thread($owner);
        $member = User::factory()->create(['role' => 'member']);
        ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => $member->id, 'body' => 'ตอบ']);

        foreach ([$owner, User::factory()->create(['role' => 'admin']), User::factory()->create(['role' => 'it_support'])] as $who) {
            $this->assertStringContainsString('title="ล็อกกระทู้"', $this->html($who, $thread), $who->role);
        }
        $this->assertStringNotContainsString('title="ล็อกกระทู้"', $this->html($member, $thread));
        $this->assertStringNotContainsString('title="ปลดล็อกกระทู้"', $this->html($member, $thread));
    }

    public function test_the_owner_of_a_locked_thread_is_offered_to_unlock_it(): void
    {
        $owner = User::factory()->create(['role' => 'member']);
        $thread = $this->thread($owner, true);

        $html = $this->html($owner, $thread);

        $this->assertStringContainsString('title="ปลดล็อกกระทู้"', $html);
        $this->assertStringContainsString('action="' . route('chat.unlock', $thread) . '"', $html);
    }

    public function test_a_locked_thread_still_refuses_messages_from_its_owner_until_it_is_reopened(): void
    {
        $owner = User::factory()->create(['role' => 'member']);
        $thread = $this->thread($owner, true);

        $this->actingAs($owner)->postJson(route('chat.messages.store', $thread), ['body' => 'x'])->assertForbidden();

        $this->actingAs($owner)->post(route('chat.unlock', $thread));
        $this->actingAs($owner)->post(route('chat.messages.store', $thread), ['body' => 'เปิดแล้ว'])->assertRedirect();
        $this->assertSame(2, $thread->messages()->count());
    }
}

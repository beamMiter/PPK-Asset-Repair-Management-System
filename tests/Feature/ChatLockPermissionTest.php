<?php

namespace Tests\Feature;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Locking and unlocking a chat thread is moderation: admins, supervisors, IT support and technicians may, whoever started the thread;
 * a plain member may not - not another person's thread and not their own (they may delete it, or hide it: ChatDeleteThreadTest,
 * ChatHideThreadTest). A member could otherwise unlock a thread that staff had locked.
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

    /** @return array<string,array{0:string}> */
    public static function staffRoles(): array
    {
        return ['admin' => ['admin'], 'supervisor' => ['supervisor'], 'it_support' => ['it_support'], 'technician' => ['technician']];
    }

    #[DataProvider('staffRoles')]
    public function test_staff_lock_and_unlock_any_thread_including_a_members(string $role): void
    {
        $thread = $this->thread(User::factory()->create(['role' => 'member']));
        $staff = User::factory()->create(['role' => $role]);

        $this->actingAs($staff)->post(route('chat.lock', $thread))->assertRedirect()->assertSessionHas('toast.type', 'success');
        $this->assertTrue($this->locked($thread));

        $this->actingAs($staff)->post(route('chat.unlock', $thread))->assertRedirect()->assertSessionHas('toast.type', 'success');
        $this->assertFalse($this->locked($thread));
    }

    public function test_a_plain_member_cannot_lock_or_unlock_not_even_the_thread_they_started(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $mine = $this->thread($member);
        $staffs = $this->thread(User::factory()->create(['role' => 'it_support']), true);   // locked by staff, say

        $this->actingAs($member)->post(route('chat.lock', $mine))->assertForbidden();
        $this->actingAs($member)->post(route('chat.unlock', $staffs))->assertForbidden();

        $this->assertFalse($this->locked($mine));
        $this->assertTrue($this->locked($staffs), 'a member cannot overrule a moderator');
    }

    public function test_the_api_follows_the_same_rule(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $thread = $this->thread($member);

        Sanctum::actingAs($member);
        $this->postJson("/api/threads/{$thread->id}/lock")->assertForbidden();
        $this->assertFalse($this->locked($thread));

        Sanctum::actingAs(User::factory()->create(['role' => 'technician']));
        $this->postJson("/api/threads/{$thread->id}/lock")->assertOk()->assertJsonPath('is_locked', true);
        $this->postJson("/api/threads/{$thread->id}/unlock")->assertOk()->assertJsonPath('is_locked', false);
    }

    public function test_the_rule_is_one_method(): void
    {
        $thread = $this->thread(User::factory()->create(['role' => 'member']));

        foreach (['admin', 'supervisor', 'it_support', 'technician'] as $role) {
            $this->assertTrue($thread->canBeLockedBy(User::factory()->create(['role' => $role])), $role);
        }
        $this->assertFalse($thread->canBeLockedBy(User::factory()->create(['role' => 'member'])));
        $this->assertFalse($thread->canBeLockedBy($thread->author), 'the author is a member');
        $this->assertFalse($thread->canBeLockedBy(null));
    }

    // ---- what the page offers -----------------------------------------------------------------------------------

    private function html(User $viewer, ChatThread $thread): string
    {
        return $this->actingAs($viewer)->get(route('chat.index', ['thread_id' => $thread->id]))->assertOk()->getContent();
    }

    public function test_the_lock_button_is_for_staff_only_and_a_member_owner_gets_delete_instead(): void
    {
        $owner = User::factory()->create(['role' => 'member']);
        $thread = $this->thread($owner);

        foreach (['admin', 'supervisor', 'it_support', 'technician'] as $role) {
            $this->assertStringContainsString('title="ล็อกกระทู้"', $this->html(User::factory()->create(['role' => $role]), $thread), $role);
        }

        $mine = $this->html($owner, $thread);
        $this->assertStringNotContainsString('title="ล็อกกระทู้"', $mine);
        $this->assertStringNotContainsString('title="ปลดล็อกกระทู้"', $mine);
        $this->assertStringContainsString('title="ลบกระทู้"', $mine, 'the owner can still delete it');
    }

    public function test_a_locked_thread_refuses_messages_from_its_member_owner_until_staff_reopen_it(): void
    {
        $owner = User::factory()->create(['role' => 'member']);
        $thread = $this->thread($owner, true);

        $this->actingAs($owner)->postJson(route('chat.messages.store', $thread), ['body' => 'x'])->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']))->post(route('chat.unlock', $thread));
        $this->actingAs($owner)->post(route('chat.messages.store', $thread), ['body' => 'เปิดแล้ว'])->assertRedirect();
        $this->assertSame(2, $thread->messages()->count());
    }
}

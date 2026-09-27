<?php

namespace Tests\Feature\Ui;

use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Every dialog closes with <x-ui.button variant="ghost" size="icon" icon="close" aria-label="ปิด">. Five were still a hand-written
 * <button> with a grey icon - the chat's create / lock / delete dialogs, the profile picture cropper and the SLA print dialog - so a change
 * to the close button reached every dialog but these.
 */
class DialogCloseButtonsTest extends TestCase
{
    use RefreshDatabase;

    /** the sorted class list of the tag that carries $needle */
    private function classesOf(string $html, string $needle): array
    {
        $at = strpos($html, $needle);
        $this->assertNotFalse($at, "not found: {$needle}");
        $start = strrpos(substr($html, 0, $at), '<');
        $tag = substr($html, $start, strpos($html, '>', $at) - $start);
        $this->assertStringStartsWith('<button', $tag);
        $this->assertStringContainsString('aria-label="ปิด"', $tag, 'an icon-only button has a name');
        preg_match('/class="([^"]*)"/', $tag, $m);
        $classes = preg_split('/\s+/', trim($m[1] ?? ''));
        sort($classes);

        return $classes;
    }

    private function reference(): array
    {
        return $this->classesOf(Blade::render('<x-ui.button variant="ghost" size="icon" icon="close" aria-label="ปิด" />'), 'aria-label="ปิด"');
    }

    public function test_the_chat_dialogs_close_with_the_standard_button(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);   // may lock and delete, so all three dialogs are drawn
        $thread = ChatThread::create(['title' => 'x', 'author_id' => $admin->id, 'is_locked' => false]);

        $html = $this->actingAs($admin)->get(route('chat.index', ['thread_id' => $thread->id]))->assertOk()->getContent();

        foreach (['showCreateModal', 'showLockModal', 'showDeleteModal'] as $dialog) {
            $this->assertSame($this->reference(), $this->classesOf($html, "@click=\"{$dialog} = false\""), $dialog);
        }
    }

    public function test_the_profile_picture_cropper_closes_with_it(): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => 'member']))->get(route('profile.edit'))->assertOk()->getContent();

        $this->assertSame($this->reference(), $this->classesOf($html, 'id="cropper-close"'));
    }

    public function test_the_sla_print_dialog_closes_with_it(): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('maintenance.sla.index'))->assertOk()->getContent();

        $this->assertSame($this->reference(), $this->classesOf($html, '@click="showSignModal = false"'));
    }

    public function test_none_of_the_five_is_a_hand_written_button_any_more(): void
    {
        foreach (['chat/index', 'profile/edit', 'maintenance/sla/index'] as $view) {
            $source = file_get_contents(resource_path("views/{$view}.blade.php"));

            $this->assertDoesNotMatchRegularExpression(
                '/<button[^>]*>\s*<span class="material-symbols-outlined[^"]*">close<\/span>\s*<\/button>/',
                $source,
                "{$view}: a bare <button> holding only the close icon",
            );
        }
    }
}

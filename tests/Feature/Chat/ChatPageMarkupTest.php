<?php

namespace Tests\Feature\Chat;

use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The chat page's Alpine state is one long `x-data="{ ... }"` attribute. A double quote inside it - even in a comment - ends the attribute, and
 * the rest of the script is drawn as text at the top of the page (seen once: the emoji lists and `this.lockChanged = true;` printed above the
 * thread list). The other tests read substrings of the HTML and could not see it, so this one parses the page the way a browser does.
 */
class ChatPageMarkupTest extends TestCase
{
    use RefreshDatabase;

    private function dom(string $html): \DOMDocument
    {
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();

        return $dom;
    }

    private function pageFor(User $viewer, ?ChatThread $thread = null): \DOMDocument
    {
        $url = route('chat.index', $thread ? ['thread_id' => $thread->id] : []);

        return $this->dom($this->actingAs($viewer)->get($url)->assertOk()->getContent());
    }

    private function assertScriptStaysInItsAttribute(\DOMDocument $dom): void
    {
        $pane = $dom->getElementById('chat-pane');
        $this->assertNotNull($pane, 'the chat pane');

        $state = $pane->getAttribute('x-data');
        foreach (['showCreateModal', 'submitLock()', 'submitDelete()', 'insertEmoji(emoji)', 'lockChanged', 'curatedEmojis'] as $piece) {
            $this->assertStringContainsString($piece, $state, "x-data lost \"{$piece}\": the attribute ended early");
        }
        $this->assertStringEndsWith('}', rtrim($state), 'x-data is the whole object');

        // ...and none of it is visible text
        $visible = '';
        foreach ((new \DOMXPath($dom))->query('//body//text()[not(ancestor::script) and not(ancestor::style)]') as $node) {
            $visible .= $node->nodeValue . ' ';
        }
        foreach (['this.lockChanged', 'curatedEmojis', 'submitDelete()', 'showEmojiPicker', 'insertEmoji'] as $code) {
            $this->assertStringNotContainsString($code, $visible, "\"{$code}\" is printed on the page");
        }
    }

    public function test_the_list_page_keeps_its_script_inside_the_attribute(): void
    {
        $this->assertScriptStaysInItsAttribute($this->pageFor(User::factory()->create(['role' => 'member'])));
    }

    public function test_an_open_thread_does_too_for_a_member_a_moderator_and_a_locked_one(): void
    {
        $author = User::factory()->create(['role' => 'member']);
        $open = ChatThread::create(['title' => 'เปิด', 'author_id' => $author->id, 'is_locked' => false]);
        $locked = ChatThread::create(['title' => 'ล็อก', 'author_id' => $author->id, 'is_locked' => true]);

        foreach ([$author, User::factory()->create(['role' => 'admin']), User::factory()->create(['role' => 'it_support'])] as $viewer) {
            $this->assertScriptStaysInItsAttribute($this->pageFor($viewer, $open));
            $this->assertScriptStaysInItsAttribute($this->pageFor($viewer, $locked));
        }
    }
}

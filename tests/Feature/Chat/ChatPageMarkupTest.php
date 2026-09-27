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

    /**
     * The floating widget used to be on every page, /chat included. Its drawer list and the page's own message list were both `id="chatList"`, and
     * the widget's script looks its list up by id: on /chat it found the page's list first and drew its rows (thread titles, "sender: text", a hide
     * button on each) inside the messages of the open thread - "the chat shows nothing", and two hide buttons. The widget's list now has its own id
     * (`chatWidgetList`, checked below on a page that still carries the widget) - but the real fix is that the widget is not on /chat at all any
     * more: its fixed bottom-right circle sat on top of that page's own send button.
     */
    public function test_no_id_on_the_chat_page_is_used_twice_and_the_widget_is_not_on_it(): void
    {
        $author = User::factory()->create(['role' => 'member']);
        $thread = ChatThread::create(['title' => 'เปิด', 'author_id' => $author->id, 'is_locked' => false]);
        \App\Models\ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => $author->id, 'body' => 'สวัสดี']);   // the message list is drawn only when there is one
        $dom = $this->pageFor(User::factory()->create(['role' => 'admin']), $thread);

        $seen = [];
        foreach ((new \DOMXPath($dom))->query('//*[@id]') as $node) {
            $seen[$node->getAttribute('id')] = ($seen[$node->getAttribute('id')] ?? 0) + 1;
        }
        unset($seen['loaderOverlay']);   // several pages draw their own next to the layout's: an older, separate duplicate
        $twice = array_keys(array_filter($seen, fn ($n) => $n > 1));

        $this->assertSame([], $twice, 'an id used twice: a script that looks it up by id gets the first one');
        $this->assertNotNull($dom->getElementById('chatList'), 'the page\'s message list');
        $this->assertNull($dom->getElementById('chatWidgetRoot'), 'the floating widget is not drawn on the page that is itself the chat');
        $this->assertNull($dom->getElementById('chatWidgetList'), 'nor is its drawer list');
    }

    public function test_the_widget_still_has_its_own_list_id_elsewhere(): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('repair.dashboard'))->assertOk()->getContent();
        $dom = $this->dom($html);

        $this->assertNotNull($dom->getElementById('chatWidgetRoot'), 'the widget is on an ordinary page');
        $this->assertNotNull($dom->getElementById('chatWidgetList'), 'with its own list id');
        $this->assertNull($dom->getElementById('chatList'), 'this page is not the chat page, so it has no chatList to collide with');
    }
}

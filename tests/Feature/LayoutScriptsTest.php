<?php

namespace Tests\Feature;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where the app layout's JavaScript lives. Turbo Drive replaces the <body> on every visit and re-runs the inline scripts
 * in it, so a layout that registers `document` / `window` listeners inline stacks another copy of each on every page the
 * user opens (16 of them, none guarded). The behaviour now lives in resources/js/layout/, loaded once as a module — the
 * registration-once and behaviour tests are in tests/js/layout.test.mjs (`npm run test:js`); this pins the wiring.
 *
 * The same went for the two other things every page carries: the toast component (188 lines of script + 327 of CSS) and the
 * chat widget (238 lines, whose `setInterval` was started again by every visit, and which put other users' chat text into
 * innerHTML). Their behaviour is in tests/js/toast.test.mjs and tests/js/chat-fab.test.mjs.
 */
class LayoutScriptsTest extends TestCase
{
    use RefreshDatabase;

    /** The bodies of the page's inline <script> blocks (no `src`, not a JSON carrier). */
    private function inlineScripts(string $html): array
    {
        preg_match_all('#<script(?![^>]*\bsrc=)(?![^>]*type="application/json")[^>]*>(.*?)</script>#s', $html, $m);

        return $m[1];
    }

    public function test_the_app_layout_registers_nothing_inline(): void
    {
        $source = file_get_contents(resource_path('views/layouts/app.blade.php'));

        foreach ($this->inlineScripts($source) as $script) {
            $this->assertDoesNotMatchRegularExpression('/addEventListener|setInterval|new MutationObserver/', $script, 'a listener in the layout is registered again on every Turbo visit — put it in resources/js/layout/');
        }
    }

    public function test_the_layout_module_is_a_vite_entry_and_only_the_app_layout_loads_it(): void
    {
        $this->assertStringContainsString("'resources/js/layout/boot.js'", file_get_contents(base_path('vite.config.js')));
        $this->assertStringContainsString('resources/js/layout/boot.js', file_get_contents(resource_path('views/layouts/app.blade.php')));

        // the login / guest layouts must not get the link spinner or the unsaved-changes guard
        foreach (['auth', 'guest'] as $layout) {
            $this->assertStringNotContainsString('layout/boot.js', file_get_contents(resource_path("views/layouts/$layout.blade.php")), $layout);
        }

        $boot = file_get_contents(resource_path('js/layout/boot.js'));
        $this->assertMatchesRegularExpression('/^installLayout\(\);/m', $boot, 'installed at module level, so once per browser session');
    }

    public function test_a_rendered_page_carries_the_layout_module_and_none_of_the_old_inline_code(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $html = $this->actingAs($admin)->get(route('repair.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('resources/js/layout/boot.js', $html);
        foreach (['window.Loader =', 'window.closeSide = function', 'function initDirtyCheck', 'forceHideLoader'] as $old) {
            $this->assertStringNotContainsString($old, $html, "$old is back inline");
        }
    }

    public function test_the_technician_board_has_no_inline_script_and_no_unreachable_detail_modal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $html = $this->actingAs($admin)->get(route('maintenance.requests.rating.technicians'))->assertOk()->getContent();

        $this->assertStringContainsString('technicians-dashboard.js', $html, 'the page bundle is still loaded');
        $this->assertStringContainsString('id="sortSelector"', $html);
        $this->assertStringContainsString('id="techSearch"', $html);

        // the "rating detail" modal was never opened by anything (the rows link to the full page); it also wrote the
        // rating comments and names into innerHTML without escaping them
        $this->assertStringNotContainsString('ratingModal', $html);
        $this->assertStringNotContainsString('openRatingModal', $html);

        $view = file_get_contents(resource_path('views/maintenance/rating/technicians-dashboard.blade.php'));
        $this->assertSame([], $this->inlineScripts($view), 'the board controls live in resources/js/maintenance/rating/technician-board.js');
    }

    public function test_the_toast_component_is_only_markup_and_its_code_and_styles_are_files(): void
    {
        $component = file_get_contents(resource_path('views/components/toast.blade.php'));
        $this->assertStringNotContainsString('<script', $component);
        $this->assertStringNotContainsString('<style', $component);
        $this->assertStringContainsString('class="toast-overlay"', $component);

        $css = file_get_contents(resource_path('css/toast.css'));
        foreach (['--toast-z', '.toast-card', '.toast--success', '.toast--warning', '.toast-fill'] as $rule) {
            $this->assertStringContainsString($rule, $css);
        }
        $this->assertDoesNotMatchRegularExpression('/^\s*text-\s*:/m', $css, 'a truncated `text-shadow` that never applied');

        $app = file_get_contents(resource_path('js/app.js'));
        $this->assertMatchesRegularExpression('/^installToast\(\);/m', $app, 'installed at module level, so once per browser session');
    }

    public function test_every_layout_that_shows_toasts_loads_their_styles(): void
    {
        foreach (['app', 'auth'] as $layout) {
            $source = file_get_contents(resource_path("views/layouts/$layout.blade.php"));
            $this->assertStringContainsString('<x-toast', $source, $layout);
            $this->assertStringContainsString('resources/css/toast.css', $source, "$layout renders <x-toast /> so it needs the styles");
        }

        $this->assertStringContainsString('resources/css/toast.css', $this->get(route('login'))->assertOk()->getContent());
    }

    public function test_the_chat_widget_hands_its_urls_to_the_module_and_carries_no_script(): void
    {
        $partial = file_get_contents(resource_path('views/partials/chat-fab.blade.php'));
        $this->assertStringNotContainsString('<script', $partial);
        $this->assertStringContainsString('data-updates-url=', $partial);
        $this->assertStringContainsString('data-notify-icon=', $partial);
        $this->assertMatchesRegularExpression('/^installChatFab\(\);/m', file_get_contents(resource_path('js/layout/boot.js')));

        $admin = User::factory()->create(['role' => 'admin']);
        $html = $this->actingAs($admin)->get(route('repair.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('data-updates-url="'.route('chat.my_updates').'"', $html);
        $this->assertStringContainsString('id="chatWidgetRoot"', $html);
    }

    public function test_the_common_page_chrome_registers_and_starts_nothing_inline(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $html = $this->actingAs($admin)->get(route('repair.dashboard'))->assertOk()->getContent();

        foreach ($this->inlineScripts($html) as $script) {
            $this->assertDoesNotMatchRegularExpression('/addEventListener|setInterval|MutationObserver/', $script, 'the layout, toast and chat widget are modules now');
        }
    }

    public function test_the_chat_page_has_no_script_of_its_own_and_no_livewire_leftovers(): void
    {
        $view = file_get_contents(resource_path('views/chat/index.blade.php'));

        $this->assertSame([], $this->inlineScripts($view), 'the page logic lives in resources/js/chat/page.js');
        foreach (['wire:', 'livewire', 'data-navigate-once', 'showLoader', 'hideLoader'] as $leftover) {
            $this->assertStringNotContainsString($leftover, $view, "$leftover: Livewire is gone, and the layout provides window.Loader");
        }
        $this->assertStringContainsString("@vite(['resources/js/chat/boot.js'])", $view);
        $this->assertStringContainsString("'resources/js/chat/boot.js'", file_get_contents(base_path('vite.config.js')));
        $this->assertMatchesRegularExpression('/^installChatPage\(\);/m', file_get_contents(resource_path('js/chat/boot.js')), 'an entry that only defines functions is tree-shaken to nothing');
    }

    public function test_an_open_thread_hands_the_page_module_what_it_needs_and_escapes_what_it_renders(): void
    {
        $me = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['name' => '<img src=x onerror=alert(1)>']);
        $thread = ChatThread::create(['title' => 'ทดสอบ', 'author_id' => $other->id]);
        ChatMessage::create(['chat_thread_id' => $thread->id, 'user_id' => $other->id, 'body' => '<script>alert(2)</script>']);

        $html = $this->actingAs($me)->get(route('chat.index', ['thread_id' => $thread->id]))->assertOk()->getContent();

        $this->assertStringContainsString('resources/js/chat/boot.js', $html);
        $this->assertStringContainsString('id="chatBox"', $html);
        $this->assertStringContainsString('data-chat-url="'.route('chat.messages', $thread).'"', $html);
        $this->assertStringContainsString('data-thread-id="'.$thread->id.'"', $html);
        $this->assertStringNotContainsString('<script>alert(2)</script>', $html, 'a message body reaches the page escaped');
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html, 'and so does a sender name');
    }
}

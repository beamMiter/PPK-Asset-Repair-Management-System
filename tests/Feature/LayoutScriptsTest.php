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

    public function test_the_layout_keeps_only_its_font_faces_inline_and_the_rest_of_its_css_is_a_file(): void
    {
        $source = file_get_contents(resource_path('views/layouts/app.blade.php'));

        preg_match_all('#<style[^>]*>(.*?)</style>#s', $source, $blocks);
        $this->assertNotEmpty($blocks[1]);
        foreach ($blocks[1] as $block) {
            // an @font-face URL comes from asset(), which is why these four stay in the Blade
            $left = preg_replace('#/\*.*?\*/#s', '', preg_replace('/@font-face\s*\{.*?\n\s*\}/s', '', $block));
            $this->assertSame('', trim($left), 'the layout\'s styles belong in resources/css/layout.css');
        }

        $css = file_get_contents(resource_path('css/layout.css'));
        foreach (['--side-w', '--topbar-h', '.sidebar', '.is-dirty-field', '.page-create-asset .content'] as $needle) {
            $this->assertStringContainsString($needle, $css);
        }
        $this->assertDoesNotMatchRegularExpression('/@font-face\\s*\\{/', $css, 'the font faces need asset() URLs, so they stay in the Blade');
        $this->assertStringNotContainsString('{{', $css);
        $this->assertStringContainsString("'resources/css/layout.css'", file_get_contents(base_path('vite.config.js')));
    }

    public function test_layout_css_is_loaded_where_the_inline_style_used_to_be_after_the_page_styles(): void
    {
        // The layout's inline <style> came after `@stack('styles')`, so on equal specificity it beat a page's own styles and
        // the CDN sheets; a stylesheet linked from the top of <head> would flip that. Pin the position.
        $admin = User::factory()->create(['role' => 'admin']);
        $html = $this->actingAs($admin)->get(route('assets.create'))->assertOk()->getContent();

        $layoutCss = strpos($html, 'resources/css/layout.css');
        $this->assertNotFalse($layoutCss, 'the layout stylesheet is linked');
        $this->assertLessThan($layoutCss, strpos($html, 'bootstrap.min.css'), 'after the CDN styles');
        $this->assertLessThan($layoutCss, strpos($html, 'resources/css/app.css'), 'after app.css');
        $this->assertLessThan($layoutCss, strpos($html, '.page-create-asset'), 'after the styles this page pushes into <head>');
        $this->assertLessThan(strpos($html, '<body'), $layoutCss, 'still in <head>');
        $this->assertSame(1, substr_count($html, 'resources/css/layout.css'), 'linked once');

        // the top bar's <style> sat at the top of <body>: after every head style, so its file goes right after layout.css
        $topbarCss = strpos($html, 'resources/css/topbar.css');
        $this->assertNotFalse($topbarCss, 'the top bar stylesheet is linked');
        $this->assertLessThan($topbarCss, $layoutCss, 'after layout.css');
        $this->assertLessThan(strpos($html, '<body'), $topbarCss, 'still in <head>');
        $this->assertSame(1, substr_count($html, 'resources/css/topbar.css'), 'linked once');

        // and the sidebar's <style> sat inside <aside>, after the top bar's
        $sidebarCss = strpos($html, 'resources/css/sidebar.css');
        $this->assertNotFalse($sidebarCss, 'the sidebar stylesheet is linked');
        $this->assertLessThan($sidebarCss, $topbarCss, 'after topbar.css');
        $this->assertLessThan(strpos($html, '<body'), $sidebarCss, 'still in <head>');
        $this->assertSame(1, substr_count($html, 'resources/css/sidebar.css'), 'linked once');
    }

    public function test_the_sidebar_component_is_only_markup_and_its_styles_are_a_file(): void
    {
        $component = file_get_contents(resource_path('views/components/sidebar.blade.php'));
        $this->assertStringNotContainsString('<style', $component);
        $this->assertStringNotContainsString('<script', $component);
        $this->assertStringContainsString('mobile-sidebar-container', $component);

        $css = file_get_contents(resource_path('css/sidebar.css'));
        foreach (['.no-scrollbar', '.btn-close-trigger:active', '.mobile-sidebar-container', '@media (max-width: 1024px)', '100dvh'] as $needle) {
            $this->assertStringContainsString($needle, $css);
        }
        $this->assertStringContainsString("'resources/css/sidebar.css'", file_get_contents(base_path('vite.config.js')));

        $admin = User::factory()->create(['role' => 'admin']);
        $html = $this->actingAs($admin)->get(route('repair.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('class="mobile-sidebar-container', $html, 'the sidebar is still rendered');
        $this->assertStringNotContainsString('.mobile-sidebar-container {', $html, 'and its rules are not repeated inline on every page');
    }

    public function test_the_only_inline_style_left_in_the_page_chrome_is_the_font_faces(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $html = $this->actingAs($admin)->get(route('repair.dashboard'))->assertOk()->getContent();

        preg_match_all('#<style[^>]*>(.*?)</style>#s', $html, $blocks);
        foreach ($blocks[1] as $block) {
            $left = preg_replace('#/\*.*?\*/#s', '', preg_replace('/@font-face\s*\{.*?\}/s', '', $block));
            $this->assertSame('', trim($left), 'the layout, top bar, sidebar and toast styles are files; only the asset()-based font faces stay inline');
        }
    }

    public function test_the_topbar_component_is_only_markup_and_its_styles_are_a_file(): void
    {
        $component = file_get_contents(resource_path('views/components/topbar.blade.php'));
        $this->assertStringNotContainsString('<style', $component);
        $this->assertStringNotContainsString('<script', $component);
        $this->assertStringContainsString('navbar-pinwheel', $component);

        $css = file_get_contents(resource_path('css/topbar.css'));
        foreach (['--topbar-h', '--ppk-blue', '.navbar-pinwheel', '.nav-brand-block', '@keyframes navPing', '@media (max-width: 991.98px)'] as $needle) {
            $this->assertStringContainsString($needle, $css);
        }
        $this->assertStringNotContainsString('{{', $css);
        $this->assertStringContainsString("'resources/css/topbar.css'", file_get_contents(base_path('vite.config.js')));

        $admin = User::factory()->create(['role' => 'admin']);
        $html = $this->actingAs($admin)->get(route('repair.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('navbar-pinwheel', $html, 'the bar is still rendered');
        $this->assertStringNotContainsString('.navbar-pinwheel {', $html, 'and its rules are not repeated inline on every page');
    }

    /**
     * TomSelect's theme defines `--ts-pr-caret: 0` (no unit), so its own `right: max(var(--ts-pr-caret), 8px)` for the clear (×)
     * button that initTomSelect adds to optional selects is invalid: `right` falls back to `auto` and the × sat at the start of
     * the control, on top of the first character of the value. The layout gives it a `right` of its own.
     */
    public function test_the_clear_button_of_the_selects_has_a_valid_position_of_its_own(): void
    {
        $css = file_get_contents(resource_path('css/layout.css'));

        $this->assertMatchesRegularExpression('/\.ts-wrapper\.single\s+\.ts-control\s*>\s*\.clear-button\s*\{[^}]*\bright:\s*[0-9.]+(rem|px)\s*!important/s', $css);
        $this->assertMatchesRegularExpression('/\.ts-wrapper\.single\s+\.ts-control\s*>\s*\.clear-button\s*\{[^}]*\bleft:\s*auto\s*!important/s', $css);
    }
}

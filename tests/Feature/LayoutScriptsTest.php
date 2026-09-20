<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where the app layout's JavaScript lives. Turbo Drive replaces the <body> on every visit and re-runs the inline scripts
 * in it, so a layout that registers `document` / `window` listeners inline stacks another copy of each on every page the
 * user opens (16 of them, none guarded). The behaviour now lives in resources/js/layout/, loaded once as a module — the
 * registration-once and behaviour tests are in tests/js/layout.test.mjs (`npm run test:js`); this pins the wiring.
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
}

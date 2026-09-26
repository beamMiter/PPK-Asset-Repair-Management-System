<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the pages pull from third-party CDNs. Each of these was found loaded for nothing:
 *  - the Lottie player from unpkg `@latest` (an unpinned script that anyone publishing a new release can change) on every
 *    page and on the login page, while no view has a `<lottie-player>` element;
 *  - Alpine a second time from unpkg on the manual page, on top of the copy bundled in app.js (every `x-data` started twice);
 *  - Font Awesome (all.min.css and its fonts) on every page, for six icons on the notification-sound settings page.
 */
class CdnAssetsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every third-party script / stylesheet URL on the page, in order. The app's own assets (built files, or the Vite dev
     * server when `public/hot` exists on this machine) are not third-party, whatever host they are served from.
     */
    private function externalAssets(string $html): array
    {
        preg_match_all('/<(?:script|link)\b[^>]*?\b(?:src|href)="(https?:\/\/[^"]+)"/i', $html, $m);

        $own = ['localhost', '127.0.0.1', parse_url((string) config('app.url'), PHP_URL_HOST)];

        return array_values(array_filter($m[1], fn ($url) => ! in_array(parse_url($url, PHP_URL_HOST), $own, true)));
    }

    private function pages(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);

        return [
            'login (guest layout)' => $this->get(route('login'))->assertOk()->getContent(),
            'dashboard' => $this->actingAs($admin)->get(route('repair.dashboard'))->assertOk()->getContent(),
            'request list' => $this->actingAs($admin)->get(route('maintenance.requests.index'))->assertOk()->getContent(),
            'request form' => $this->actingAs($admin)->get(route('maintenance.requests.create'))->assertOk()->getContent(),
            'asset form' => $this->actingAs($admin)->get(route('assets.create'))->assertOk()->getContent(),
            'profile edit' => $this->actingAs($admin)->get(route('profile.edit'))->assertOk()->getContent(),
            'manual' => $this->actingAs($admin)->get(route('help.manual'))->assertOk()->getContent(),
            'sound settings' => $this->actingAs($admin)->get(route('settings.notifications.index'))->assertOk()->getContent(),
        ];
    }

    public function test_no_page_loads_an_unpinned_script_or_stylesheet(): void
    {
        foreach ($this->pages() as $name => $html) {
            foreach ($this->externalAssets($html) as $url) {
                $this->assertStringNotContainsString('@latest', $url, "$name loads an unpinned asset");
                $this->assertDoesNotMatchRegularExpression('/@\d+\.x\b|@\d+\.\d+\.x\b|@\d+\.x\.x\b/', $url, "$name loads a floating version");
            }
        }
    }

    public function test_no_page_loads_the_same_external_asset_twice(): void
    {
        foreach ($this->pages() as $name => $html) {
            $urls = $this->externalAssets($html);

            $this->assertSame([], array_keys(array_filter(array_count_values($urls), fn ($n) => $n > 1)), "$name");
        }
    }

    public function test_the_unused_lottie_player_is_gone_and_alpine_only_comes_from_the_bundle(): void
    {
        foreach ($this->pages() as $name => $html) {
            $urls = implode("\n", $this->externalAssets($html));

            $this->assertStringNotContainsStringIgnoringCase('lottie', $urls, "$name");
            $this->assertStringNotContainsStringIgnoringCase('alpinejs', $urls, "$name: Alpine is bundled by app.js");
        }
    }

    public function test_font_awesome_is_loaded_exactly_on_the_page_that_uses_it(): void
    {
        $pages = $this->pages();

        foreach ($pages as $name => $html) {
            $loads = str_contains(implode("\n", $this->externalAssets($html)), 'font-awesome');
            $uses = (bool) preg_match('/<i\b[^>]*\bclass="[^"]*\bfa-(?:solid|regular|brands)\b/', $html);

            $this->assertSame($uses, $loads, "$name: Font Awesome should be loaded if and only if the page has an fa-* icon");
        }

        $this->assertStringContainsString('font-awesome', implode("\n", $this->externalAssets($pages['sound settings'])));
    }
}

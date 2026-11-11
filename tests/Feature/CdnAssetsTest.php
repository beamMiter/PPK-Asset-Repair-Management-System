<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the pages pull in from other sites. They used to load Bootstrap, Bootstrap Icons and TomSelect from jsDelivr, Inter and
 * Material Symbols from Google Fonts, Figtree from Bunny Fonts, Font Awesome from cdnjs, Cropper from unpkg and the chat sound
 * from mixkit: on a hospital network closed to the internet the pages lost their layout, icons, menus and sound. The same
 * files, same versions, are served from public/vendor now. Earlier finds this test still guards: the Lottie player from
 * unpkg `@latest` loaded for nothing, Alpine loaded twice on the manual page, Font Awesome on every page for six icons.
 */
class CdnAssetsTest extends TestCase
{
    use RefreshDatabase;

    /** Every script, stylesheet and media URL on the page, in order (the app's own and anybody else's). */
    private function assets(string $html): array
    {
        preg_match_all('/<(?:script|link|source|audio|img)\b[^>]*?\b(?:src|href)="([^"]+)"/i', $html, $m);

        return array_values(array_filter($m[1], fn ($url) => ! str_starts_with($url, 'data:') && ! str_starts_with($url, '#')));
    }

    /** The ones from another site. The Vite dev server (how tests render the app's own bundle) is the app's own. */
    private function externalAssets(string $html): array
    {
        $own = ['localhost', '127.0.0.1', parse_url((string) config('app.url'), PHP_URL_HOST)];

        return array_values(array_filter($this->assets($html), fn ($url) => preg_match('#^(https?:)?//#i', $url)
            && ! in_array(parse_url($url, PHP_URL_HOST), $own, true)));
    }

    private function pages(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);

        return [
            'login' => $this->get(route('login'))->assertOk()->getContent(),
            'reset password (guest layout)' => $this->get(route('password.reset', ['token' => 'x', 'email' => 'a@b.c']))->assertOk()->getContent(),
            'dashboard' => $this->actingAs($admin)->get(route('repair.dashboard'))->assertOk()->getContent(),
            'request list' => $this->actingAs($admin)->get(route('maintenance.requests.index'))->assertOk()->getContent(),
            'request form' => $this->actingAs($admin)->get(route('maintenance.requests.create'))->assertOk()->getContent(),
            'asset form' => $this->actingAs($admin)->get(route('assets.create'))->assertOk()->getContent(),
            'profile edit' => $this->actingAs($admin)->get(route('profile.edit'))->assertOk()->getContent(),
            'manual' => $this->actingAs($admin)->get(route('help.manual'))->assertOk()->getContent(),
            'sound settings' => $this->actingAs($admin)->get(route('settings.notifications.index'))->assertOk()->getContent(),
            'chat' => $this->actingAs($admin)->get(route('chat.index'))->assertOk()->getContent(),
        ];
    }

    public function test_no_page_loads_a_script_stylesheet_font_or_sound_from_another_site(): void
    {
        foreach ($this->pages() as $name => $html) {
            $this->assertSame([], $this->externalAssets($html), "$name loads from another site");
        }
    }

    public function test_every_third_party_file_is_pinned_to_a_version(): void
    {
        foreach ($this->pages() as $name => $html) {
            foreach ($this->assets($html) as $url) {
                if (preg_match('#/vendor/((?!fonts/|mixkit/)[^/]+)/#', $url, $m)) {
                    $this->assertMatchesRegularExpression('/@\d+\.\d+\.\d+$/', $m[1], "$name: $url is not pinned to an exact version");
                }
            }
        }
    }

    public function test_no_page_loads_the_same_file_twice(): void
    {
        foreach ($this->pages() as $name => $html) {
            // scripts, stylesheets and sounds; a picture (an avatar in the top bar and the sidebar) may well appear twice. The Vite
            // dev server's own client is left out: each @vite call prints it in dev mode, and a built page has none
            preg_match_all('/<(?:script|link|source)\b[^>]*?\b(?:src|href)="([^"]+)"/i', $html, $m);
            $dev = rtrim((string) file_get_contents(base_path('tests/fixtures/vite-hot')));
            $urls = array_filter($m[1], fn ($url) => ! str_starts_with($url, $dev));

            $this->assertSame([], array_keys(array_filter(array_count_values($urls), fn ($n) => $n > 1)), "$name");
        }
    }

    public function test_the_unused_lottie_player_is_gone_and_alpine_only_comes_from_the_bundle(): void
    {
        foreach ($this->pages() as $name => $html) {
            $urls = implode("\n", $this->assets($html));

            $this->assertStringNotContainsStringIgnoringCase('lottie', $urls, "$name");
            $this->assertStringNotContainsStringIgnoringCase('alpinejs', $urls, "$name: Alpine is bundled by app.js");
        }
    }

    public function test_font_awesome_is_loaded_exactly_on_the_page_that_uses_it(): void
    {
        $pages = $this->pages();

        foreach ($pages as $name => $html) {
            $loads = str_contains(implode("\n", $this->assets($html)), '/vendor/font-awesome@');
            $uses = (bool) preg_match('/<i\b[^>]*\bclass="[^"]*\bfa-(?:solid|regular|brands)\b/', $html);

            $this->assertSame($uses, $loads, "$name: Font Awesome should be loaded if and only if the page has an fa-* icon");
        }

        $this->assertStringContainsString('/vendor/font-awesome@', implode("\n", $this->assets($pages['sound settings'])));
    }
}

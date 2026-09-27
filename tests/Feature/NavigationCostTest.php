<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What one page change makes the browser do. Turbo Drive replaces the <body> on every visit and re-creates everything in it, so
 * whatever the body carries is asked for again on each page: two CDN scripts (fetched and run again — Bootstrap stacked its
 * document listeners once more each time), two <audio preload="auto"> (one of them an mp3 from another site), every avatar from
 * ui-avatars.com (InitialsAvatarTest). And Turbo 8 prefetches a link's page whenever the pointer rests on it for 100 ms — a full
 * render of a personal page for each of the 17 links the mouse crosses in the menu.
 */
class NavigationCostTest extends TestCase
{
    use RefreshDatabase;

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($dom);
    }

    private function page(string $role = 'admin'): string
    {
        return $this->actingAs(User::factory()->create(['role' => $role]))->get(route('maintenance.requests.index'))->assertOk()->getContent();
    }

    public function test_turbo_does_not_prefetch_the_page_of_every_link_the_pointer_crosses(): void
    {
        // the guest pages first: a signed-in user is redirected away from them
        foreach ([route('login'), route('register'), route('password.request'), route('password.reset', ['token' => 'abc'])] as $url) {
            $this->assertSame(1, $this->xpath($this->get($url)->assertOk()->getContent())->query('//head/meta[@name="turbo-prefetch"][@content="false"]')->length, $url);
        }

        $this->assertSame(1, $this->xpath($this->page())->query('//head/meta[@name="turbo-prefetch"][@content="false"]')->length, 'the app layout');
    }

    public function test_the_layouts_cdn_scripts_are_in_the_head_not_re_created_by_every_visit(): void
    {
        $xp = $this->xpath($this->page());

        foreach (['bootstrap.bundle.min.js', 'tom-select.complete.min.js'] as $lib) {
            $inHead = $xp->query('//head/script[contains(@src,"'.$lib.'")]');
            $this->assertSame(1, $inHead->length, "$lib is in the head");
            $this->assertTrue($inHead->item(0)->hasAttribute('defer'), "$lib is deferred: it does not hold the first paint up");
            $this->assertSame(0, $xp->query('//body//script[contains(@src,"'.$lib.'")]')->length, "$lib is not in the body");
        }
        $this->assertSame(0, $xp->query('//body//script[starts-with(@src,"https://cdn.")]')->length, 'no CDN script in the body at all');

        // they run before the layout's own modules, which use TomSelect and Bootstrap's Offcanvas (deferred scripts run in document order)
        $order = [];
        foreach ($xp->query('//head/script[@src]') as $i => $script) {
            $order[$script->getAttribute('src')] = $i;
        }
        $tom = collect($order)->first(fn ($i, $src) => str_contains($src, 'tom-select.complete'));
        $modules = collect($order)->filter(fn ($i, $src) => str_contains($src, 'resources/js/app.js') || str_contains($src, '/build/assets/app-'));
        $this->assertNotEmpty($modules, 'the layout modules are in the head');
        foreach ($modules as $src => $i) {
            $this->assertLessThan($i, $tom, "TomSelect comes before $src");
        }
    }

    public function test_the_notification_sounds_are_fetched_when_they_ring_not_on_every_page(): void
    {
        $html = $this->page('it_support');

        foreach (['notifySound', 'chatNotifySound'] as $id) {
            $this->assertMatchesRegularExpression('/<audio\b[^>]*\bid="'.$id.'"[^>]*>/', $html, "#$id is on the page");
            preg_match('/<audio\b[^>]*\bid="'.$id.'"[^>]*>/', $html, $tag);
            $this->assertStringContainsString('preload="none"', $tag[0], "#$id waits until it rings");
        }
        $this->assertDoesNotMatchRegularExpression('/<audio\b[^>]*preload="auto"/', $html, 'no audio is preloaded');
    }

    public function test_the_layout_source_keeps_these_out_of_the_body(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $body = substr($layout, strpos($layout, '</head>')); // everything after the head

        $this->assertStringNotContainsString('cdn.jsdelivr.net', $body, 'a CDN script in the body is fetched and run again on every Turbo visit');
        $this->assertStringNotContainsString('preload="auto"', $body);
        $this->assertStringNotContainsString('preload="auto"', file_get_contents(resource_path('views/partials/chat-fab.blade.php')));
    }
}

<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #chatBadge (the red unread count on the chat FAB) had a min-width but no height and no line-height: at 11px with the
 * browser's default line-height the line box was taller than the 20px min-width, so `rounded-full` drew an oval and the
 * digit sat wherever that line box put it, not the middle of the circle. A fixed height equal to the min-width, plus flex
 * centring, fixes the shape — but the digit still sat low even at `leading-none`, because the page's default font is
 * Sarabun, whose ascent is unusually tall (headroom for Thai marks stacked above a vowel), which pushes a plain digit's
 * ink low within any line box built from its metrics. The badge never shows Thai, so it opts out with `font-sans`
 * (system-ui / Segoe UI / Roboto — ordinary, balanced metrics for a digit) instead of trying to out-nudge Sarabun.
 */
class ChatBadgeShapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_unread_badge_is_a_flex_centred_circle(): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('maintenance.requests.index'))->assertOk()->getContent();

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $badge = (new DOMXPath($dom))->query('//*[@id="chatBadge"]')->item(0);
        $this->assertNotNull($badge, '#chatBadge is on the page');
        $class = $badge->getAttribute('class');

        // a circle needs equal height and width for the one- or two-digit case: a fixed height matching the min-width
        $this->assertMatchesRegularExpression('/(?<![\w-])h-5(?![\w-])/', $class, 'a fixed height');
        $this->assertMatchesRegularExpression('/(?<![\w-])min-w-5(?![\w-])/', $class, 'the same size as the height, as a minimum (grows for "99+")');

        // the digit centred in that height: needs a flex container, both centring axes, and a line-height that fits
        $this->assertMatchesRegularExpression('/(?<![\w-])inline-flex(?![\w-])/', $class, 'a flex container (for items-center to do anything)');
        $this->assertStringContainsString('items-center', $class);
        $this->assertStringContainsString('justify-center', $class);
        $this->assertStringContainsString('leading-none', $class, 'no default line-height taller than the box');

        $this->assertStringContainsString('rounded-full', $class);
    }

    public function test_the_badge_opts_out_of_the_pages_thai_font(): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('maintenance.requests.index'))->assertOk()->getContent();

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $badge = (new DOMXPath($dom))->query('//*[@id="chatBadge"]')->item(0);
        $class = $badge->getAttribute('class');

        // `.font-sans` (a class selector) beats the `html, body { font-family: Sarabun }` rule in resources/css/layout.css
        // (a plain-element selector) on specificity alone, whichever file loads last — Sarabun's ascent is tuned for Thai
        // marks stacked over a vowel, which leaves a lone digit sitting low in any box built from its line metrics.
        $this->assertStringContainsString('font-sans', $class, 'a normal-metrics font, not the page\'s Thai one, for a digit that is never Thai');

        $css = file_get_contents(resource_path('css/layout.css'));
        $this->assertMatchesRegularExpression('/^(html,\s*)?body\s*\{[^}]*font-family:\s*[\'"]Sarabun/m', $css, 'the rule font-sans has to outrank is still a plain element selector');
    }
}

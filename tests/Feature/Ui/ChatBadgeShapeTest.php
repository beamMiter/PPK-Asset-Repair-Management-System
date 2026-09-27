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

    private function badgeClass(): string
    {
        $html = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('maintenance.requests.index'))->assertOk()->getContent();

        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $badge = (new DOMXPath($dom))->query('//*[@id="chatBadge"]')->item(0);
        $this->assertNotNull($badge, '#chatBadge is on the page');

        return $badge->getAttribute('class');
    }

    public function test_the_unread_badge_is_a_flex_centred_circle(): void
    {
        $class = $this->badgeClass();

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
        $class = $this->badgeClass();

        // `.font-sans` (a class selector) beats the `html, body { font-family: Sarabun }` rule in resources/css/layout.css
        // (a plain-element selector) on specificity alone, whichever file loads last — Sarabun's ascent is tuned for Thai
        // marks stacked over a vowel, which leaves a lone digit sitting low in any box built from its line metrics.
        $this->assertStringContainsString('font-sans', $class, 'a normal-metrics font, not the page\'s Thai one, for a digit that is never Thai');

        $css = file_get_contents(resource_path('css/layout.css'));
        $this->assertMatchesRegularExpression('/^(html,\s*)?body\s*\{[^}]*font-family:\s*[\'"]Sarabun/m', $css, 'the rule font-sans has to outrank is still a plain element selector');
    }

    public function test_the_digit_is_nudged_up_without_moving_the_circle(): void
    {
        $class = $this->badgeClass();

        // even in a normal font a digit's ink sits baseline-up, so the em-box's own centre sits a touch above the ink's
        // centre — pb-0.5 eats 2px off the bottom of the box (not off its total height: Tailwind's preflight makes every
        // box border-box), so the centred content shifts up ~1px while the circle stays the fixed h-5 it was
        $this->assertMatchesRegularExpression('/(?<![\w-])pb-0\.5(?![\w-])/', $class, 'bottom-only padding nudges the centred content up');
        $this->assertMatchesRegularExpression('/(?<![\w-])h-5(?![\w-])/', $class, 'height is still the fixed one pb-0.5 must not grow');

        // proof the box stays border-box regardless of padding: preflight.css (Tailwind's own base reset, unrelated to
        // this app's classes) sets it on every element, so pb-0.5 shrinks the content area rather than growing h-5
        $preflight = file_get_contents(base_path('node_modules/tailwindcss/lib/css/preflight.css'));
        $this->assertMatchesRegularExpression('/\*,\s*::before,\s*::after\s*\{[^}]*box-sizing:\s*border-box/s', $preflight, 'tailwind preflight sets border-box on every element');
    }
}

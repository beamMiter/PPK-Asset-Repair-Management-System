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
 * centring, fixes both: a circle for one or two digits, and the digit dead centre in it.
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
}

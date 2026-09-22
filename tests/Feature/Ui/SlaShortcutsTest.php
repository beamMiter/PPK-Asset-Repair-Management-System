<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "รายงานสรุป" and "ล่าสุด" on the SLA dashboard were split buttons (an icon block plus a label) of their own — the
 * shared component already draws that exact look (`<x-ui.button icon="..." split>`). They are bare icons now, the same
 * shape as the paperclip/camera pair and the job page's assign-team icon elsewhere in the app.
 *
 * "ทางลัด" (the three quick date ranges — 6 months, 12 months, this year) were plain underlined links with no way to
 * tell which one, if any, was currently applied. They are a small set of pills now, the applied one filled navy. That
 * needed knowing which one is applied in the first place: the page already computed this once, for the "แสดงข้อมูล:"
 * line below the filters, but compared `request('from')` against dates it worked out a different way
 * (`subMonths(5)->startOfMonth()` / `subMonths(11)->startOfMonth()`) than the ones the links themselves send
 * (`subMonths(6)->addDay()` / `subYear()->addDay()`) — so that line never once matched, even right after clicking a
 * shortcut. Both places now read the one `$activeShortcut` the links' own dates produce.
 */
class SlaShortcutsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($dom);
    }

    private function page(array $query = []): string
    {
        return $this->actingAs($this->admin())
            ->get(route('maintenance.sla.index', $query))->assertOk()->getContent();
    }

    public function test_report_and_refresh_are_bare_icons(): void
    {
        $xp = $this->xpath($this->page());

        foreach (['print' => 'รายงานสรุป', 'refresh' => 'รีเฟรชข้อมูลล่าสุด'] as $glyph => $label) {
            $button = $xp->query('//button[.//span[normalize-space()="'.$glyph.'"]]')->item(0);
            $this->assertNotNull($button, "the $glyph button is there");
            $this->assertSame($label, $button->getAttribute('aria-label'), "$glyph: says what it is to a screen reader");
            $this->assertSame($label, $button->getAttribute('title'), "$glyph: says what it is on hover");
            $words = trim(preg_replace('/\s+/', ' ', str_replace($glyph, '', $button->textContent)));
            $this->assertSame('', $words, "$glyph: no visible label, only the icon");
        }
    }

    public function test_this_year_is_the_active_shortcut_with_no_range_chosen(): void
    {
        $html = $this->page();
        $xp = $this->xpath($html);

        $active = $xp->query('//a[@aria-current="true"]');
        $this->assertSame(1, $active->length, 'exactly one shortcut is marked current');
        $this->assertStringContainsString('ปีนี้', trim($active->item(0)->textContent));
        $this->assertStringContainsString('bg-[#0F2D5C]', $active->item(0)->getAttribute('class'));

        foreach (['6 เดือน', '12 เดือน'] as $label) {
            $pill = collect(iterator_to_array($xp->query('//a')))->first(fn ($a) => trim($a->textContent) === $label);
            $this->assertNotNull($pill, "$label pill is on the page");
            $this->assertNotSame('true', $pill->getAttribute('aria-current'), "$label is not marked current");
            $this->assertStringNotContainsString('bg-[#0F2D5C]', $pill->getAttribute('class'), "$label is not filled navy");
        }

        $this->assertStringContainsString('ปีนี้ —', $html, '"แสดงข้อมูล:" agrees it is ปีนี้');
    }

    public function test_the_six_month_shortcut_is_active_right_after_clicking_it(): void
    {
        // the exact query string the "6 เดือน" pill's own href sends
        $from = now()->subMonths(6)->addDay()->format('Y-m-d');

        $html = $this->page(['from' => $from]);
        $xp = $this->xpath($html);

        $active = $xp->query('//a[@aria-current="true"]');
        $this->assertSame(1, $active->length);
        $this->assertSame('6 เดือน', trim($active->item(0)->textContent));

        // the bug this replaces: the old comparison never matched this exact date, so this line stayed "ช่วงวันที่"
        $this->assertStringContainsString('6 เดือน —', $html, '"แสดงข้อมูล:" agrees it is 6 เดือน, not "ช่วงวันที่"');
    }

    public function test_the_twelve_month_shortcut_is_active_right_after_clicking_it(): void
    {
        $from = now()->subYear()->addDay()->format('Y-m-d');

        $html = $this->page(['from' => $from]);
        $xp = $this->xpath($html);

        $active = $xp->query('//a[@aria-current="true"]');
        $this->assertSame(1, $active->length);
        $this->assertSame('12 เดือน', trim($active->item(0)->textContent));
        $this->assertStringContainsString('12 เดือน —', $html);
    }

    public function test_a_custom_range_that_matches_no_shortcut_marks_none_current(): void
    {
        $html = $this->page(['from' => '2020-01-01', 'to' => '2020-06-01']);
        $xp = $this->xpath($html);

        $this->assertSame(0, $xp->query('//a[@aria-current="true"]')->length, 'no pill is marked current');
        $this->assertStringContainsString('ช่วงวันที่ —', $html, '"แสดงข้อมูล:" falls back to the generic label');
    }

    public function test_choosing_a_to_date_alongside_a_shortcuts_from_is_not_read_as_that_shortcut(): void
    {
        // a custom "to" means the visitor built their own range, even if "from" happens to match a shortcut's date
        $from = now()->subMonths(6)->addDay()->format('Y-m-d');

        $html = $this->page(['from' => $from, 'to' => now()->subDays(3)->format('Y-m-d')]);

        $this->assertSame(0, $this->xpath($html)->query('//a[@aria-current="true"]')->length);
    }
}

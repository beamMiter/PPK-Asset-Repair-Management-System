<?php

namespace Tests\Feature\Ui;

use App\Models\MaintenanceRequest;
use App\Models\User;
use FontLib\Font;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A filter-bar select is one of a row of `md:col-span-N` boxes in a 12-column grid, all `w-full` — its own width comes
 * entirely from how many of those 12 columns its `<div>` claims, so a select with a long list of long options (สถานะ:
 * "หยุดการซ่อมบำรุงชั่วคราว") sitting in the same span as one with short options (ประเภทงาน: "ยังไม่ระบุประเภท") — or
 * worse, in a SMALLER span — clips or wraps its chosen value while its neighbour sits half-empty. This pins the columns
 * each select needs, worked out from Sarabun's own advance widths (the same measurement `ThaiTextRoomTest` uses,
 * php-font-lib's `hmtx` table) against the pixel width a `lg:col-span-N` box actually resolves to on a 1440px screen —
 * a common laptop width, sidebar expanded (the widest the sidebar gets), which is the standard this file's `columnBudgetPx`
 * uses throughout. (`md:`, the 768–1023px tablet band, is not covered here — narrower still, but a secondary case.)
 *
 * Every check reads the real `lg:col-span-N` (or `md:col-span-N`, its fallback) out of the rendered page — never a number
 * typed into the test — so reverting the class in the view, not just changing this file, is what it takes to pass.
 */
class FilterSelectWidthTest extends TestCase
{
    use RefreshDatabase;

    private const SIDEBAR_PX = 260;
    private const CONTENT_PAD_PX = 16;   // .content { padding: ... 1rem }, one side
    private const PAGE_PAD_LG_PX = 32;   // the page's own lg:px-8, one side
    private const GAP_PX = 12;           // gap-3 between grid columns
    private const SELECT_OVERHEAD_PX = 48; // px-3 both sides + border + the native dropdown arrow

    private static function columnUnitPx(float $viewportPx = 1440): float
    {
        $chrome = self::SIDEBAR_PX + 2 * self::CONTENT_PAD_PX + 2 * self::PAGE_PAD_LG_PX;
        $forGaps = 11 * self::GAP_PX;

        return ($viewportPx - $chrome - $forGaps) / 12;
    }

    /** How much text a select spanning $span of the 12 columns can show before it starts clipping its value. */
    private function columnBudgetPx(int $span): float
    {
        $box = $span * self::columnUnitPx() + ($span - 1) * self::GAP_PX;

        return $box - self::SELECT_OVERHEAD_PX;
    }

    private ?array $cmap = null;

    private ?array $hmtx = null;

    private ?int $upm = null;

    /** The rendered width (px) of $text at 13px Sarabun — the size every filter-bar select uses (text-[13px]). */
    private function textWidthPx(string $text): float
    {
        if ($this->cmap === null) {
            $font = Font::load(public_path('images/fonts/Sarabun-Regular.ttf'));
            $font->parse();
            $this->cmap = $font->getUnicodeCharMap();
            $this->hmtx = $font->getData('hmtx');
            $this->upm = $font->getData('head', 'unitsPerEm');
        }

        $total = 0;
        $len = mb_strlen($text, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $cp = mb_ord(mb_substr($text, $i, 1, 'UTF-8'), 'UTF-8');
            $gid = $this->cmap[$cp] ?? null;
            $total += $gid !== null ? ($this->hmtx[$gid][0] ?? 0) : 0;
        }

        return $total / $this->upm * 13;
    }

    /** Longest of a set of option labels — the one a select actually has to make room for. */
    private function longest(array $labels): string
    {
        return collect($labels)->sortByDesc(fn ($s) => $this->textWidthPx($s))->first();
    }

    /** The real column-span the page gives this select right now: its `lg:` override, or `md:` when there is none. */
    private function actualSpan(string $html, string $selectId): int
    {
        $this->assertMatchesRegularExpression(
            '/<div class="[^"]*"[^>]*>\s*<label for="'.preg_quote($selectId, '/').'"/',
            $html, "#$selectId's wrapper div is right before its label"
        );
        preg_match('/<div class="([^"]*)"[^>]*>\s*<label for="'.preg_quote($selectId, '/').'"/', $html, $m);
        $class = $m[1] ?? '';

        if (preg_match('/lg:col-span-(\d+)/', $class, $lg)) {
            return (int) $lg[1];
        }
        $this->assertMatchesRegularExpression('/md:col-span-(\d+)/', $class, "#$selectId: no lg: or md: col-span at all");
        preg_match('/md:col-span-(\d+)/', $class, $md);

        return (int) $md[1];
    }

    private function assertColumnFits(string $html, string $selectId, array $labels, string $what): void
    {
        $span = $this->actualSpan($html, $selectId);
        $longest = $this->longest($labels);
        $needed = $this->textWidthPx($longest);
        $budget = $this->columnBudgetPx($span);

        $this->assertLessThanOrEqual(
            $budget, $needed,
            "$what: lg:col-span-$span gives {$budget}px, but \"$longest\" needs {$needed}px"
        );
    }

    /** The option labels a <select id="$selectId"> actually renders, read from the page (dynamic data included). */
    private function renderedOptions(string $html, string $selectId): array
    {
        preg_match('/id="'.preg_quote($selectId, '/').'"[\s\S]*?<\/select>/', $html, $select);
        $this->assertNotEmpty($select, "#$selectId is on the page");
        preg_match_all('/<option[^>]*>([^<]*)<\/option>/u', $select[0], $opts);
        $this->assertNotEmpty($opts[1] ?? [], "#$selectId has options to check");

        return array_map('trim', $opts[1]);
    }

    public function test_the_request_list_status_and_type_selects_have_the_room_their_content_needs(): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('maintenance.requests.index'))->assertOk()->getContent();

        $this->assertColumnFits($html, 'status', array_merge(array_values(MaintenanceRequest::statusLabels()), ['ทุกสถานะ']), 'requests list: สถานะ');
        // ประเภทงาน adds "ยังไม่ระบุประเภท" and every seeded type name to the static "ทั้งหมด" — read the real page so a
        // longer seeded type name is caught too, not just today's
        $this->assertColumnFits($html, 'type_id', $this->renderedOptions($html, 'type_id'), 'requests list: ประเภทงาน');
    }

    public function test_my_jobs_status_select_has_the_room_its_content_needs(): void
    {
        $tech = User::factory()->create(['role' => 'it_support']);
        $html = $this->actingAs($tech)->get(route('repairs.my_jobs'))->assertOk()->getContent();

        // the labels this page's own $statusLabel closure uses (repair/my-jobs.blade.php) — not the model's, which
        // differ (its on_hold is shorter here: "พักไว้ชั่วคราว")
        $this->assertColumnFits($html, 'status', [
            'ทุกสถานะ', 'รอดำเนินการ', 'รับทราบแล้ว', 'รับเรื่องแล้ว', 'กำลังดำเนินการ',
            'พักไว้ชั่วคราว', 'ซ่อมบำรุงเสร็จสิ้น', 'อนุมัติผลการซ่อมบำรุง', 'ยกเลิกการซ่อมบำรุง', 'ไม่รับเรื่อง',
        ], 'my jobs: สถานะใบงาน');
    }

    public function test_the_user_list_role_and_department_selects_have_the_room_their_content_needs(): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.users.index'))->assertOk()->getContent();

        $this->assertColumnFits($html, 'role', array_merge(['บทบาททั้งหมด'], array_values(User::roleLabels())), 'user list: บทบาท');
        $this->assertColumnFits($html, 'department', $this->renderedOptions($html, 'department'), 'user list: หน่วยงาน');
    }

    public function test_the_technician_rating_sort_select_has_the_room_its_longest_option_needs(): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('maintenance.requests.rating.technicians'))->assertOk()->getContent();

        $this->assertColumnFits($html, 'sortSelector', [
            'ผลงานดีที่สุด (Impact Score)', 'คะแนนเฉลี่ยสูงสุด', 'จำนวนการประเมินสูงสุด',
        ], 'technician rating: เรียงลำดับข้อมูล');
    }
}

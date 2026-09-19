<?php

namespace Tests\Feature\Ui;

use App\Models\MaintenanceLog;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * The history dialog's timeline (partials/_timeline). Built from unsaved models, so it needs no database.
 * Pins the parts that were confusing: look-alike tick icons, and the "เริ่มต้น -> x" chip that said nothing.
 */
class HistoryTimelineTest extends TestCase
{
    /** @param array<int, array<string, mixed>> $rows */
    private function render(array $rows): string
    {
        $base = Carbon::parse('2026-01-10 09:00:00');
        $logs = collect($rows)->values()->map(function (array $row, int $i) use ($base) {
            $log = (new MaintenanceLog)->forceFill($row + ['id' => $i + 1, 'created_at' => $base->copy()->addHours($i)]);
            $log->setRelation('user', new User(['name' => 'สมชาย ใจดี']));

            return $log;
        });

        $req = new MaintenanceRequest;
        $req->setRelation('logs', $logs);

        return view('maintenance.requests.partials._timeline', ['req' => $req])->render();
    }

    /** @return list<string> icon ligatures in card order (latest first) */
    private function glyphs(string $html): array
    {
        preg_match_all('/<span class="material-symbols-outlined text-\[20px\]">\s*([a-z_]+)\s*<\/span>/', $html, $m);

        return $m[1];
    }

    public function test_every_status_has_its_own_icon_and_approval_is_a_paper_with_a_tick(): void
    {
        $statuses = ['pending', 'acknowledged', 'accepted', 'in_progress', 'on_hold', 'resolved', 'closed', 'cancelled', 'rejected'];
        $html = $this->render(array_map(fn ($s) => ['action' => 'transition', 'to_status' => $s], $statuses));

        $glyphs = $this->glyphs($html);
        $this->assertCount(count($statuses), $glyphs);
        $this->assertSame($glyphs, array_unique($glyphs), 'two statuses share an icon: '.implode(', ', $glyphs));

        $closed = $this->render([['action' => 'transition', 'to_status' => 'closed']]);
        $this->assertSame(['task'], $this->glyphs($closed));
        $this->assertStringContainsString('bg-emerald-600 text-white', $closed, 'approval is the one filled dot');
        $this->assertStringNotContainsString('verified', $closed);
    }

    public function test_a_status_change_says_where_it_came_from_in_words_not_with_an_arrow(): void
    {
        $html = $this->render([['action' => 'transition', 'from_status' => 'resolved', 'to_status' => 'closed', 'note' => 'ผู้แจ้งตรวจสอบและปิดงาน']]);

        $this->assertStringContainsString('อนุมัติผลการซ่อมบำรุง', $html);
        $this->assertStringContainsString('เปลี่ยนจาก', $html);
        $this->assertStringContainsString('ซ่อมบำรุงเสร็จสิ้น', $html);
        $this->assertStringContainsString('ผู้แจ้งตรวจสอบและปิดงาน', $html);
        $this->assertStringNotContainsString('->', $html);
        $this->assertStringNotContainsString('trending_flat', $html);
        $this->assertStringNotContainsString('เริ่มต้น', $html);
    }

    public function test_old_rows_without_status_columns_are_read_from_the_note_prefix(): void
    {
        // code prefix (seeded / legacy) and Thai-label prefix (what the transition service writes)
        $html = $this->render([
            ['action' => 'start_request', 'note' => '[accepted -> in_progress] เริ่มดำเนินการซ่อมบำรุง'],
            ['action' => 'transition', 'note' => '[รับทราบแล้ว -> รับเรื่องแล้ว] รับเรื่องเรียบร้อย • เจ้าหน้าที่: สมศรี'],
        ]);

        $this->assertSame(['thumb_up', 'directions_run'], $this->glyphs($html));
        $this->assertStringContainsString('เริ่มดำเนินการซ่อมบำรุง', $html);
        $this->assertStringContainsString('รับเรื่องเรียบร้อย • เจ้าหน้าที่: สมศรี', $html);
        $this->assertStringNotContainsString('[accepted', $html, 'the bracket prefix must not leak into the card');
        $this->assertStringNotContainsString('[รับทราบแล้ว', $html);
        $this->assertStringNotContainsString('อัปเดตรายการ', $html);
    }

    public function test_creation_card_has_no_previous_status_and_does_not_repeat_itself(): void
    {
        $html = $this->render([['action' => 'create_request', 'to_status' => 'pending', 'note' => 'สร้างใบแจ้งซ่อมใหม่เข้าระบบ']]);

        $this->assertStringContainsString('สร้างใบแจ้งซ่อมใหม่', $html);
        $this->assertSame(1, substr_count($html, 'สร้างใบแจ้งซ่อมใหม่'), 'the note repeats the title');
        $this->assertStringNotContainsString('เปลี่ยนจาก', $html);
        $this->assertSame(['add_circle'], $this->glyphs($html));
    }

    public function test_empty_notes_and_multiline_notes_render(): void
    {
        $html = $this->render([
            ['action' => 'transition', 'to_status' => 'accepted', 'note' => null],
            ['action' => 'transition', 'to_status' => 'on_hold', 'note' => "[in_progress -> on_hold] รออะไหล่\nโทรตามซัพพลายเออร์แล้ว"],
        ]);

        $this->assertStringContainsString('โทรตามซัพพลายเออร์แล้ว', $html);
        $this->assertStringNotContainsString('border-l-[3px]', explode('รับเรื่องแล้ว', $html)[1] ?? '', 'no note, no quote bar');
    }

    public function test_time_in_state_and_hold_are_labelled_and_the_newest_card_has_none(): void
    {
        $html = $this->render([
            ['action' => 'transition', 'to_status' => 'in_progress'],
            ['action' => 'transition', 'to_status' => 'on_hold'],
            ['action' => 'transition', 'to_status' => 'resolved'],
        ]);

        $this->assertSame(1, substr_count($html, 'อยู่ในสถานะนี้ 1 ชม.'));
        $this->assertSame(1, substr_count($html, 'หยุดซ่อมชั่วคราว 1 ชม.'));
    }

    public function test_unknown_events_get_a_neutral_card_and_an_empty_history_a_message(): void
    {
        $html = $this->render([['action' => 'something_new', 'note' => 'x']]);
        $this->assertStringContainsString('อัปเดตรายการ', $html);
        $this->assertSame(['info'], $this->glyphs($html));

        $this->assertStringContainsString('ยังไม่มีบันทึกประวัติการดำเนินงาน', $this->render([]));
    }

    public function test_text_is_at_least_12px_and_uses_no_bootstrap_colliding_spacing(): void
    {
        $html = $this->render([
            ['action' => 'create_request', 'to_status' => 'pending'],
            ['action' => 'transition', 'from_status' => 'pending', 'to_status' => 'acknowledged', 'note' => 'รับทราบ'],
        ]);

        $this->assertDoesNotMatchRegularExpression('/text-\[(?:9|10|11)px\]/', $html);

        // Bootstrap's !important .p*/.m*/.gap-3..5 differ from Tailwind's (1 and 2 are the same value, so they are safe)
        preg_match_all('/class="([^"]*)"/', $html, $all);
        foreach (explode(' ', implode(' ', $all[1])) as $token) {
            $this->assertDoesNotMatchRegularExpression('/^(?:[mp][tbxy]?|gap)-[345]$/', $token, "`$token` collides with Bootstrap's !important utility");
        }
    }
}

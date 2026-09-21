<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * "อนุมัติผลการซ่อมบำรุง" (status closed) has one icon, `task`, the one the history timeline uses (HistoryTimelineTest pins
 * that page). It used to be `verified` on the my-jobs list and in the manual, `fact_check` on the dashboard, a plain
 * `check` on the approve button and `check_circle` in the "approved" dialog. Read from the views' source so it needs no database.
 */
class ApprovalIconTest extends TestCase
{
    private function source(string $view): string
    {
        return file_get_contents(resource_path('views/'.$view));
    }

    public function test_the_status_maps_use_task_for_closed(): void
    {
        $this->assertMatchesRegularExpression("/'closed'\s*=>\s*\['icon' => 'task',/", $this->source('maintenance/requests/partials/_timeline.blade.php'));
        $this->assertMatchesRegularExpression("/'closed' => 'task',/", $this->source('repair/my-jobs.blade.php'));
        $this->assertMatchesRegularExpression("/'closed' => \[\s*'icon' => 'task',/", $this->source('repair/dashboard.blade.php'));
    }

    public function test_the_approve_button_and_the_approved_dialog_use_task(): void
    {
        $this->assertStringContainsString('icon="task" split>อนุมัติปิดงาน', $this->source('maintenance/requests/partials/_page_header.blade.php'));
        $this->assertMatchesRegularExpression('/text-\[48px\]">task<\/span>/', $this->source('maintenance/requests/partials/_modal_post_close.blade.php'));
    }

    public function test_the_manual_status_legend_uses_task(): void
    {
        $this->assertMatchesRegularExpression("/'icon' => 'task',\s*'color' => 'text-emerald-\d+',\s*'name' => 'อนุมัติผลการซ่อมบำรุง'/", $this->source('help/manual.blade.php'));
    }

    public function test_the_manuals_closing_section_keeps_its_own_icon(): void
    {
        // the side-menu link and the heading of "การตรวจสอบและปิดงาน" are a guide section, not the status: they stay `verified`
        $manual = $this->source('help/manual.blade.php');

        $this->assertSame(2, preg_match_all('/>verified<\/span>\s*การตรวจสอบและปิดงาน/', $manual));
        $this->assertDoesNotMatchRegularExpression('/>task<\/span>\s*การตรวจสอบและปิดงาน/', $manual);
    }

    /** The approval icon is the same green as "ซ่อมบำรุงเสร็จสิ้น" (resolved) wherever a page colours the two icons. */
    public function test_the_approval_icon_is_the_same_green_as_resolved(): void
    {
        $manual = $this->source('help/manual.blade.php');
        $this->assertSame(1, preg_match("/'icon' => 'task_alt',\s*'color' => '(text-emerald-\d+)',\s*'name' => 'ซ่อมบำรุงเสร็จสิ้น'/", $manual, $resolved));
        $this->assertSame(1, preg_match("/'icon' => 'task',\s*'color' => '(text-emerald-\d+)',\s*'name' => 'อนุมัติผลการซ่อมบำรุง'/", $manual, $closed));
        $this->assertSame($resolved[1], $closed[1], 'manual legend');

        // the chip beside a job: the icon and the status text share this colour
        $this->assertSame(1, preg_match("/'resolved' => '(text-emerald-\d+)',\s*'closed' => '(text-emerald-\d+)'/", $this->source('repair/my-jobs.blade.php'), $chip));
        $this->assertSame($chip[1], $chip[2], 'my-jobs status chip');

        $this->assertSame(1, preg_match("/'resolved' => \[\s*'icon' => '[a-z_]+',\s*'color' => '(text-emerald-\d+)'.*?'closed' => \[\s*'icon' => 'task',\s*'color' => '(text-emerald-\d+)'/s", $this->source('repair/dashboard.blade.php'), $map));
        $this->assertSame($map[1], $map[2], 'dashboard status map');
    }

    public function test_the_old_approval_icons_are_gone(): void
    {
        // `check_circle` stays valid for other statuses (resolved on the dashboard), so only the approval dialog is checked for it
        foreach (['repair/my-jobs.blade.php', 'repair/dashboard.blade.php'] as $view) {
            $this->assertDoesNotMatchRegularExpression('/[>\'"](verified|fact_check)[<\'"]/', $this->source($view), "$view still uses an old approval icon");
        }

        $this->assertStringNotContainsString('fact_check', $this->source('help/manual.blade.php'));
        $this->assertStringNotContainsString('check_circle', $this->source('maintenance/requests/partials/_modal_post_close.blade.php'));
    }
}

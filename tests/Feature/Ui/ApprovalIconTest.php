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

    public function test_the_manual_uses_task_for_the_approval_status_and_section(): void
    {
        $manual = $this->source('help/manual.blade.php');

        // the status legend row
        $this->assertMatchesRegularExpression("/'icon' => 'task',\s*'color' => 'text-emerald-800',\s*'name' => 'อนุมัติผลการซ่อมบำรุง'/", $manual);
        // the side-menu link and the heading of the "ตรวจสอบและปิดงาน" section
        $this->assertSame(2, preg_match_all('/>task<\/span>\s*การตรวจสอบและปิดงาน/', $manual));
    }

    public function test_the_old_approval_icons_are_gone(): void
    {
        // `check_circle` stays valid for other statuses (resolved on the dashboard), so only the approval dialog is checked for it
        foreach (['repair/my-jobs.blade.php', 'repair/dashboard.blade.php', 'help/manual.blade.php'] as $view) {
            $this->assertDoesNotMatchRegularExpression('/[>\'"](verified|fact_check)[<\'"]/', $this->source($view), "$view still uses an old approval icon");
        }

        $this->assertStringNotContainsString('check_circle', $this->source('maintenance/requests/partials/_modal_post_close.blade.php'));
    }
}

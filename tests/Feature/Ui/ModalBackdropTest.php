<?php

namespace Tests\Feature\Ui;

use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Tests\TestCase;

/**
 * Every dialog on a page — assign team, confirm reject/cancel/hold/close, the history log, the rating dialog, the shared confirm dialog —
 * dims the page behind it the same way: bg-slate-900/40 with a light blur (backdrop-blur-sm). The "closed" dialog that
 * pops up right after approving a job's repair (_modal_post_close, "อนุมัติผลการซ่อมบำรุงเรียบร้อยแล้ว!") used a darker
 * tint and a heavier blur of its own — the one dialog on the page that looked different from the rest.
 */
class ModalBackdropTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    private const BACKDROP = 'bg-slate-900/40 backdrop-blur-sm';

    public function test_the_post_close_dialog_dims_the_page_the_same_as_every_other_dialog(): void
    {
        $reporter = User::factory()->create(['role' => 'member']);
        $req = MaintenanceRequest::factory()->create([
            'asset_id' => Asset::factory()->create()->id, 'reporter_id' => $reporter->id,
            'technician_id' => User::factory()->create(['role' => 'it_support'])->id, 'status' => 'closed',
        ]);

        $html = $this->actingAs($reporter)->withSession(['show_post_close_modal' => true])
            ->get(route('maintenance.requests.show', $req))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="postCloseModal"/', $html);

        // its own opening tag, not the whole page (which holds several dialogs)
        preg_match('/<div id="postCloseModal"[^>]*>/', $html, $tag);
        $this->assertNotEmpty($tag, 'the dialog is on the page');
        $this->assertStringContainsString(self::BACKDROP, $tag[0], 'the same tint and blur as every other dialog');
        $this->assertStringNotContainsString('bg-slate-900/60', $tag[0], 'not the darker tint it had');
        $this->assertStringNotContainsString('backdrop-blur-md', $tag[0], 'not the heavier blur it had');
    }

    public function test_the_assign_reject_and_history_dialogs_share_the_same_backdrop(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $req = MaintenanceRequest::factory()->create([
            'asset_id' => Asset::factory()->create()->id, 'reporter_id' => User::factory()->create(['role' => 'member'])->id,
            'technician_id' => null, 'status' => 'pending',
        ]);

        $html = $this->actingAs($admin)->get(route('maintenance.requests.show', $req))->assertOk()->getContent();

        // one per dialog wrapper (_modal_assign, _modal_history, _modal_rating, and one per _modal_status_actions dialog on this page)
        $this->assertGreaterThanOrEqual(4, substr_count($html, self::BACKDROP), 'the assign, history, rating and status dialogs all use it');
        $this->assertStringNotContainsString('bg-slate-900/60', $html, 'no dialog on the page has a darker tint of its own');
    }
}

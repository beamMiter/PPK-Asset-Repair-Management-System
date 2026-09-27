<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The request detail page is assembled from a header, a cards block, five dialogs and a script partial that share
 * the variables of `show.blade.php`. Every status × role has to render all of it, and the parts that are
 * permission-gated have to stay gated.
 */
class RequestShowPageTest extends TestCase
{
    use RefreshDatabase;

    private const STATUSES = ['pending', 'acknowledged', 'accepted', 'in_progress', 'on_hold', 'resolved', 'closed', 'cancelled', 'rejected'];

    private function request(string $status, User $reporter, ?User $tech): MaintenanceRequest
    {
        $req = MaintenanceRequest::factory()->create([
            'asset_id' => Asset::factory()->create()->id,
            'reporter_id' => $reporter->id,
            'technician_id' => $tech?->id,
            'status' => $status,
        ]);

        if ($tech) {
            MaintenanceAssignment::create(['maintenance_request_id' => $req->id, 'user_id' => $tech->id, 'status' => 'in_progress', 'is_lead' => true, 'role' => 'lead']);
        }

        return $req;
    }

    public function test_every_status_renders_completely_for_admin_assignee_and_reporter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tech = User::factory()->create(['role' => 'it_support']);
        $reporter = User::factory()->create(['role' => 'member']);

        foreach (self::STATUSES as $status) {
            $req = $this->request($status, $reporter, in_array($status, ['pending', 'acknowledged']) ? null : $tech);

            foreach ([$admin, $tech, $reporter] as $viewer) {
                $html = $this->actingAs($viewer)->get(route('maintenance.requests.show', $req))->assertOk()->getContent();
                $who = "$status as {$viewer->role}";

                $this->assertStringContainsString('id="historyModal"', $html, $who);   // _modal_history
                $this->assertStringContainsString('พิมพ์ PDF', $html, $who);            // _page_header
                $this->assertStringContainsString('บันทึกการประเมิน', $html, $who);     // _modal_rating
                $this->assertStringContainsString("'use strict'", $html, $who);        // _page_scripts
                $this->assertStringNotContainsString('@include', $html, $who);
                $this->assertStringNotContainsString('<x-', $html, $who);
            }
        }
    }

    public function test_dialogs_that_need_a_permission_appear_only_for_those_who_have_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reporter = User::factory()->create(['role' => 'member']);
        $req = $this->request('pending', $reporter, null);

        $asAdmin = $this->actingAs($admin)->get(route('maintenance.requests.show', $req))->assertOk()->getContent();
        $asReporter = $this->actingAs($reporter)->get(route('maintenance.requests.show', $req))->assertOk()->getContent();

        $this->assertStringContainsString('ค้นหาและเลือกเจ้าหน้าที่ที่ต้องการ', $asAdmin);     // _modal_assign
        $this->assertStringNotContainsString('ค้นหาและเลือกเจ้าหน้าที่ที่ต้องการ', $asReporter);
        $this->assertStringContainsString('id="rejectModal"', $asAdmin);                       // _modal_status_actions
        $this->assertStringNotContainsString('id="rejectModal"', $asReporter);
    }

    public function test_the_post_close_dialog_shows_only_when_the_session_asks_for_it(): void
    {
        $reporter = User::factory()->create(['role' => 'member']);
        $req = $this->request('closed', $reporter, User::factory()->create(['role' => 'it_support']));

        $this->actingAs($reporter)->get(route('maintenance.requests.show', $req))
            ->assertOk()->assertDontSee('id="postCloseModal"', false);

        $this->actingAs($reporter)->withSession(['show_post_close_modal' => true])->get(route('maintenance.requests.show', $req))
            ->assertOk()->assertSee('id="postCloseModal"', false);
    }
}

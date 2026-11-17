<?php

namespace Tests\Feature;

use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest as MR;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "Who is on this job" has two answers in the data: `technician_id` (the person in charge) and the team rows. The team dialog leaves the
 * first empty on purpose ("no lead system any more"), so every list, page and report that still read only `technician_id` said "nobody"
 * or missed the job once a team was picked. The owner's rule (2026-11-15/16): everybody on the team counts. The dashboard chart and the
 * ratings follow it already; these are the places that did not.
 */
class ListsAndReportsFollowTheTeamTest extends TestCase
{
    use RefreshDatabase;

    private User $ann;
    private User $bob;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ann = User::factory()->create(['role' => 'technician', 'name' => 'Ann Alpha']);
        $this->bob = User::factory()->create(['role' => 'it_support', 'name' => 'Bob Bravo']);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    /** An open job with these people on its team (name => assignment status), nobody marked in charge, the way the team dialog leaves it. */
    private function job(array $team, array $overrides = []): MR
    {
        $job = MR::factory()->create(array_merge(['status' => 'in_progress', 'technician_id' => null, 'asset_id' => null], $overrides));
        foreach ($team as $name => $status) {
            MaintenanceAssignment::create(['maintenance_request_id' => $job->id, 'user_id' => $this->{$name}->id, 'role' => $this->{$name}->role, 'is_lead' => false, 'status' => $status]);
        }

        return $job;
    }

    private function myJobs(User $who, array $query = []): array
    {
        $page = $this->actingAs($who)->get(route('repairs.my_jobs', $query))->assertOk();

        return collect($page->viewData('list')->items())->pluck('id')->sort()->values()->all();
    }

    public function test_my_jobs_lists_the_jobs_i_am_on_and_not_the_ones_i_was_taken_off(): void
    {
        $onIt = $this->job(['ann' => 'in_progress']);
        $takenOff = $this->job(['ann' => 'cancelled', 'bob' => 'in_progress']);
        $notMine = $this->job(['bob' => 'in_progress']);

        $this->assertSame([$onIt->id], $this->myJobs($this->ann, ['filter' => 'my']));
        $this->assertSame([$takenOff->id, $notMine->id], $this->myJobs($this->bob, ['filter' => 'my']));
    }

    public function test_the_staff_filter_finds_a_job_by_anybody_on_its_team(): void
    {
        // Ann is "in charge" of this one, Bob is only on the team; a third has Ann taken off
        $both = $this->job(['ann' => 'in_progress', 'bob' => 'in_progress'], ['technician_id' => $this->ann->id]);
        $bobOnly = $this->job(['bob' => 'in_progress']);
        $annRemoved = $this->job(['ann' => 'cancelled', 'bob' => 'in_progress']);
        $nobody = $this->job([]);

        $this->assertSame([$both->id, $bobOnly->id, $annRemoved->id], $this->myJobs($this->admin, ['tech' => $this->bob->id]));
        $this->assertSame([$both->id], $this->myJobs($this->admin, ['tech' => $this->ann->id]));
    }

    public function test_the_counts_above_the_list_follow_the_same_filter(): void
    {
        $this->job(['bob' => 'in_progress']);
        $this->job(['bob' => 'in_progress']);
        $this->job(['ann' => 'in_progress']);

        $stats = $this->actingAs($this->admin)->get(route('repairs.my_jobs', ['tech' => $this->bob->id]))->assertOk()->viewData('stats');

        $this->assertSame(2, $stats['in_progress']);
    }

    public function test_the_sla_page_names_the_team_and_does_not_say_nobody_is_assigned(): void
    {
        $this->job(['ann' => 'in_progress', 'bob' => 'in_progress'], ['sla_due_date' => now()->subDay(), 'title' => 'เกินกำหนดแล้ว']);

        $page = $this->actingAs($this->admin)->get(route('maintenance.sla.index'))->assertOk();

        $page->assertSee('Ann Alpha, Bob Bravo')->assertDontSee('ยังไม่ระบุเจ้าหน้าที่');
        $ticket = collect($page->viewData('breachedTickets'))->firstWhere('title', 'เกินกำหนดแล้ว');
        $this->assertNotNull($ticket, 'the overdue job is on the page');
        $this->assertTrue($ticket->relationLoaded('workers'), 'the page loads the team once, not once per ticket');
    }

    public function test_the_work_order_prints_the_team_where_it_used_to_print_a_dash_or_a_blank_line(): void
    {
        $job = $this->job(['ann' => 'in_progress', 'bob' => 'in_progress']);

        $html = $this->actingAs($this->admin)->get(route('maintenance.requests.work-order', ['req' => $job, 'html' => 1]))->assertOk()->getContent();

        $this->assertStringContainsString('<td>Ann Alpha, Bob Bravo</td>', $html, 'the "เจ้าหน้าที่หลัก" cell');
        $this->assertStringContainsString('( Ann Alpha, Bob Bravo )', $html, 'the signature line');
    }

    public function test_a_person_in_charge_with_no_team_row_is_still_named_once(): void
    {
        $older = $this->job([], ['technician_id' => $this->ann->id, 'sla_due_date' => now()->subDay(), 'title' => 'งานเก่า']);
        $both = $this->job(['ann' => 'in_progress', 'bob' => 'in_progress'], ['technician_id' => $this->ann->id]);

        $this->assertSame('Ann Alpha', $older->fresh()->teamNames());
        $this->assertSame('Ann Alpha, Bob Bravo', $both->fresh()->teamNames(), 'in charge and on the team: named once');
        $this->actingAs($this->admin)->get(route('maintenance.sla.index'))->assertOk()->assertSee('Ann Alpha')->assertDontSee('ยังไม่ระบุเจ้าหน้าที่');
    }

    public function test_the_work_order_still_names_the_person_in_charge_when_there_is_one(): void
    {
        $job = $this->job(['ann' => 'in_progress', 'bob' => 'in_progress'], ['technician_id' => $this->ann->id]);

        $html = $this->actingAs($this->admin)->get(route('maintenance.requests.work-order', ['req' => $job, 'html' => 1]))->assertOk()->getContent();

        $this->assertStringContainsString('<td>Ann Alpha</td>', $html);
        $this->assertStringContainsString('( Ann Alpha )', $html);
    }

    public function test_the_per_person_summary_counts_every_person_on_the_team_once(): void
    {
        $sup = User::factory()->create(['role' => 'supervisor']);
        // (the factory invents stage dates, which would end up in the average: only the closed job below has them)
        $noDates = ['started_at' => null, 'resolved_at' => null];
        $this->job(['ann' => 'in_progress', 'bob' => 'in_progress'], ['technician_id' => $this->ann->id] + $noDates);   // in charge AND on the team: one job
        $this->job(['bob' => 'done'], ['status' => 'closed', 'started_at' => now()->subHours(5), 'resolved_at' => now()->subHours(3)]);
        $this->job(['bob' => 'cancelled'], $noDates);                                                       // taken off: not Bob's

        Sanctum::actingAs($sup);
        $rows = collect($this->getJson('/api/stats/maintenance/technicians')->assertOk()->json('data'))->keyBy('id');

        $this->assertSame(['total' => 1, 'open' => 1, 'closed' => 0], collect($rows[$this->ann->id])->only(['total', 'open', 'closed'])->all());
        $this->assertSame(['total' => 2, 'open' => 1, 'closed' => 1], collect($rows[$this->bob->id])->only(['total', 'open', 'closed'])->all());
        $this->assertEquals(2.0, $rows[$this->bob->id]['avg_hours']);
    }
}

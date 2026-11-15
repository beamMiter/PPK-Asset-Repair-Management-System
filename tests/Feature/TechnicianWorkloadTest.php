<?php

namespace Tests\Feature;

use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard's "who is carrying how much" counted everything but resolved, closed and cancelled, so a job the
 * technician had turned down (rejected) stayed in their load for good. It now counts the model's own open statuses.
 */
class TechnicianWorkloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_open_jobs_count_towards_a_technicians_load(): void
    {
        $tech = User::factory()->create(['role' => 'technician']);
        foreach (MaintenanceRequest::OPEN_STATUSES as $status) {
            MaintenanceRequest::factory()->create(['status' => $status, 'technician_id' => $tech->id, 'asset_id' => null]);
        }
        foreach (['resolved', 'closed', 'cancelled', 'rejected'] as $status) {
            MaintenanceRequest::factory()->create(['status' => $status, 'technician_id' => $tech->id, 'asset_id' => null]);
        }

        $workload = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('repair.dashboard'))
            ->assertOk()
            ->viewData('techWorkload');

        $this->assertSame(count(MaintenanceRequest::OPEN_STATUSES), collect($workload)->firstWhere('id', $tech->id)['total'] ?? null);
    }

    /**
     * The chart is titled "ภาระงานเจ้าหน้าที่แต่ละคน" but counted only the person in charge (`technician_id`), and the team dialog
     * leaves that empty on purpose: an accepted job was nobody's until it started, and a job with three people on it vanished from the
     * chart after the supervisor picked them. The owner chose to count every person on the job (2026-11-15).
     */
    private function team(MaintenanceRequest $job, User ...$people): void
    {
        foreach ($people as $i => $person) {
            MaintenanceAssignment::create([
                'maintenance_request_id' => $job->id, 'user_id' => $person->id, 'is_lead' => $i === 0,
                'status' => in_array($job->status, ['resolved', 'closed'], true) ? 'done' : 'in_progress',
            ]);
        }
    }

    private function load(): \Illuminate\Support\Collection
    {
        return collect($this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('repair.dashboard'))->assertOk()->viewData('techWorkload'))
            ->mapWithKeys(fn ($row) => [$row['id'] => $row['total']]);
    }

    public function test_everybody_on_a_jobs_team_carries_it_even_though_nobody_is_marked_in_charge(): void
    {
        [$ann, $bob, $cat] = [User::factory()->create(['role' => 'technician']), User::factory()->create(['role' => 'it_support']), User::factory()->create(['role' => 'network'])];
        $job = MaintenanceRequest::factory()->create(['status' => 'in_progress', 'technician_id' => null, 'asset_id' => null]);
        $this->team($job, $ann, $bob, $cat);

        $load = $this->load();

        $this->assertSame([1, 1, 1], [$load[$ann->id] ?? 0, $load[$bob->id] ?? 0, $load[$cat->id] ?? 0]);
    }

    public function test_a_job_somebody_has_accepted_counts_before_it_starts(): void
    {
        $ann = User::factory()->create(['role' => 'technician']);
        $job = MaintenanceRequest::factory()->create(['status' => 'accepted', 'technician_id' => null, 'asset_id' => null]);
        $this->team($job, $ann);

        $this->assertSame(1, $this->load()[$ann->id] ?? 0);
    }

    public function test_a_person_taken_off_the_job_and_a_finished_or_deleted_job_do_not_count(): void
    {
        [$ann, $bob] = [User::factory()->create(['role' => 'technician']), User::factory()->create(['role' => 'it_support'])];

        $open = MaintenanceRequest::factory()->create(['status' => 'in_progress', 'technician_id' => null, 'asset_id' => null]);
        $this->team($open, $ann, $bob);
        MaintenanceAssignment::where('maintenance_request_id', $open->id)->where('user_id', $bob->id)->update(['status' => 'cancelled']);

        foreach (['resolved', 'closed', 'cancelled', 'rejected'] as $status) {
            $this->team(MaintenanceRequest::factory()->create(['status' => $status, 'technician_id' => null, 'asset_id' => null]), $bob);
        }
        $this->team(MaintenanceRequest::factory()->create(['status' => 'in_progress', 'technician_id' => null, 'asset_id' => null]), $bob);
        MaintenanceRequest::latest('id')->first()->delete();

        $load = $this->load();

        $this->assertSame(1, $load[$ann->id] ?? 0);
        $this->assertSame(0, $load[$bob->id] ?? 0, 'taken off, finished and deleted jobs are not Bob\'s load');
    }

    public function test_a_person_in_charge_and_on_the_team_counts_the_job_once_and_the_busiest_comes_first(): void
    {
        [$ann, $bob] = [User::factory()->create(['role' => 'technician']), User::factory()->create(['role' => 'it_support'])];
        $one = MaintenanceRequest::factory()->create(['status' => 'in_progress', 'technician_id' => $ann->id, 'asset_id' => null]);
        $two = MaintenanceRequest::factory()->create(['status' => 'on_hold', 'technician_id' => null, 'asset_id' => null]);
        $this->team($one, $ann, $bob);
        $this->team($two, $ann);

        $load = $this->load();

        $this->assertSame(2, $load[$ann->id] ?? 0, 'two jobs, the one she is in charge of is not counted twice');
        $this->assertSame(1, $load[$bob->id] ?? 0);
        $this->assertSame($ann->id, $load->keys()->first(), 'busiest first');
    }
}

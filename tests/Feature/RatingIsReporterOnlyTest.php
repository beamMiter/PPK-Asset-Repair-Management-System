<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRating;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A rating is the reporter's word on the service they received: only the person who reported the job makes one. The rate button, the
 * policy and the API already said so; the web door (`rating/{job}/store`) let admins and supervisors in as well ("to help rate"), and their
 * stars were counted in a technician's average beside the reporter's.
 */
class RatingIsReporterOnlyTest extends TestCase
{
    use RefreshDatabase;

    private MaintenanceRequest $job;

    private User $reporter;

    private User $tech;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reporter = User::factory()->create(['role' => 'member']);
        $this->tech = User::factory()->create(['role' => 'it_support']);
        $this->job = MaintenanceRequest::factory()->create([
            'status' => 'closed', 'closed_at' => now()->subDays(2), 'reporter_id' => $this->reporter->id, 'technician_id' => $this->tech->id,
            'asset_id' => Asset::factory()->create(['his_asset_id' => null])->id,
        ]);
        MaintenanceAssignment::create(['maintenance_request_id' => $this->job->id, 'user_id' => $this->tech->id, 'status' => 'done', 'is_lead' => true]);
    }

    public function test_nobody_but_the_reporter_can_rate_the_job(): void
    {
        foreach (['admin', 'supervisor', 'it_support', 'technician', 'member'] as $role) {
            $stranger = User::factory()->create(['role' => $role]);

            $this->actingAs($stranger)->post(route('maintenance.requests.rating.store', $this->job), ['score' => 5, 'comment' => 'ดี'])->assertForbidden();
            $this->assertSame(0, MaintenanceRating::where('maintenance_request_id', $this->job->id)->count(), "$role: no rating was made");

            $this->actingAs($stranger)->get(route('maintenance.requests.rating.create', $this->job))->assertForbidden();   // nor is anyone sent on to the dialog
            $this->flushSession();
        }
    }

    public function test_the_reporter_can_and_it_is_credited_to_the_lead(): void
    {
        $this->actingAs($this->reporter)->post(route('maintenance.requests.rating.store', $this->job), ['score' => 5, 'comment' => 'ดีมาก']);

        $rating = MaintenanceRating::where('maintenance_request_id', $this->job->id)->first();
        $this->assertNotNull($rating);
        $this->assertSame($this->reporter->id, $rating->rater_id);
        $this->assertSame($this->tech->id, $rating->technician_id);
    }

    public function test_the_api_says_the_same(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson("/api/repair-requests/{$this->job->id}/rating", ['score' => 5])->assertForbidden();

        $this->assertSame(0, MaintenanceRating::count());
    }

    public function test_an_admin_who_reported_the_job_is_a_reporter_like_any_other(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $own = MaintenanceRequest::factory()->create([
            'status' => 'closed', 'closed_at' => now()->subDay(), 'reporter_id' => $admin->id, 'technician_id' => $this->tech->id,
            'asset_id' => Asset::factory()->create(['his_asset_id' => null])->id,
        ]);
        MaintenanceAssignment::create(['maintenance_request_id' => $own->id, 'user_id' => $this->tech->id, 'status' => 'done', 'is_lead' => true]);

        $this->actingAs($admin)->post(route('maintenance.requests.rating.store', $own), ['score' => 4]);

        $this->assertSame(1, MaintenanceRating::where('maintenance_request_id', $own->id)->count());
    }
}

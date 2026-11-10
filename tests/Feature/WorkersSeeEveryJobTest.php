<?php

namespace Tests\Feature;

use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The request list shows a technician every job, but opening one somebody else had taken sent them back to the dashboard
 * with "you may only see your own jobs". The owner chose: the team may read every job (to cover for each other), and only
 * the people on a job may change it. Each action keeps its own permission, so the page they open is read-only.
 */
class WorkersSeeEveryJobTest extends TestCase
{
    use RefreshDatabase;

    private User $onJob;

    private User $other;

    private User $reporter;

    private MaintenanceRequest $job;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->onJob = User::factory()->create(['role' => 'technician']);
        $this->other = User::factory()->create(['role' => 'it_support']);
        $this->reporter = User::factory()->create(['role' => 'member']);
        $this->job = MaintenanceRequest::factory()->create([
            'status' => 'in_progress', 'reporter_id' => $this->reporter->id, 'technician_id' => $this->onJob->id,
            'asset_id' => null, 'started_at' => now(), 'title' => 'งานของคนอื่น',
        ]);
        MaintenanceAssignment::create([
            'maintenance_request_id' => $this->job->id, 'user_id' => $this->onJob->id, 'role' => 'technician', 'is_lead' => true, 'status' => 'in_progress',
        ]);
    }

    public function test_a_technician_not_on_a_job_opens_it_from_the_list(): void
    {
        $this->actingAs($this->other)->get(route('maintenance.requests.index'))
            ->assertOk()
            ->assertSee(route('maintenance.requests.show', $this->job), false);

        $this->actingAs($this->other)->get(route('maintenance.requests.show', $this->job))
            ->assertOk()
            ->assertSee('งานของคนอื่น');
    }

    public function test_the_page_offers_them_nothing_to_change(): void
    {
        $html = $this->actingAs($this->other)->get(route('maintenance.requests.show', $this->job))->assertOk()->getContent();

        foreach (['edit', 'hold', 'resolve', 'cancel', 'assignments.store', 'attachments', 'type.update'] as $action) {
            $this->assertStringNotContainsString('"'.route("maintenance.requests.{$action}", $this->job).'"', $html, "no {$action} on a job they are not on");
        }
    }

    public function test_they_still_cannot_change_it(): void
    {
        $this->actingAs($this->other)->postJson(route('maintenance.requests.hold', $this->job), ['note' => 'x'])->assertForbidden();
        $this->actingAs($this->other)->postJson(route('maintenance.requests.resolve', $this->job), ['resolution_note' => 'x'])->assertForbidden();
        $this->actingAs($this->other)->putJson(route('maintenance.requests.update', $this->job), ['title' => 'x'])->assertForbidden();
        $this->actingAs($this->other)->postJson(route('maintenance.requests.attachments', $this->job), [
            'files' => [UploadedFile::fake()->image('a.jpg', 10, 10)],
        ])->assertForbidden();

        $this->assertSame('in_progress', $this->job->fresh()->status);
        $this->assertSame('งานของคนอื่น', $this->job->fresh()->title);
    }

    public function test_they_cannot_start_or_retype_a_job_someone_else_has_taken(): void
    {
        $type = MaintenanceRequestType::create(['name' => 'ฮาร์ดแวร์', 'is_active' => true, 'sort_order' => 1]);
        $accepted = MaintenanceRequest::factory()->create([
            'status' => 'accepted', 'reporter_id' => $this->reporter->id, 'technician_id' => $this->onJob->id, 'asset_id' => null,
            'type_id' => null, 'started_at' => null, 'resolved_at' => null, 'closed_at' => null, 'completed_date' => null,
        ]);
        MaintenanceAssignment::create([
            'maintenance_request_id' => $accepted->id, 'user_id' => $this->onJob->id, 'role' => 'technician', 'is_lead' => true, 'status' => 'assigned',
        ]);

        $html = $this->actingAs($this->other)->get(route('maintenance.requests.show', $accepted))->assertOk()->getContent();
        $this->assertStringNotContainsString('"'.route('maintenance.requests.start', $accepted).'"', $html, 'no start button on somebody else\'s job');
        $this->assertStringNotContainsString('"'.route('maintenance.requests.type.update', $accepted).'"', $html, 'no type form on somebody else\'s job');

        $this->actingAs($this->other)->postJson(route('maintenance.requests.start', $accepted))->assertForbidden();
        $this->actingAs($this->other)->postJson(route('maintenance.requests.type.update', $accepted), ['type_id' => $type->id])->assertForbidden();
        $this->assertSame('accepted', $accepted->fresh()->status);
        $this->assertNull($accepted->fresh()->type_id);

        // the people on it, and admins and supervisors, still can
        $this->actingAs($this->onJob)->postJson(route('maintenance.requests.type.update', $accepted), ['type_id' => $type->id])->assertSuccessful();
        $this->assertSame($type->id, $accepted->fresh()->type_id);
        $this->actingAs(User::factory()->create(['role' => 'supervisor']))->postJson(route('maintenance.requests.type.update', $accepted), ['type_id' => null])->assertSuccessful();
    }

    public function test_a_job_nobody_has_taken_can_be_typed_by_any_of_the_staff(): void
    {
        $type = MaintenanceRequestType::create(['name' => 'เครือข่าย', 'is_active' => true, 'sort_order' => 1]);
        $queued = MaintenanceRequest::factory()->create([
            'status' => 'pending', 'reporter_id' => $this->reporter->id, 'technician_id' => null, 'asset_id' => null, 'type_id' => null,
        ]);

        $this->actingAs($this->other)->postJson(route('maintenance.requests.type.update', $queued), ['type_id' => $type->id])->assertSuccessful();

        $this->assertSame($type->id, $queued->fresh()->type_id);
    }

    public function test_they_can_open_its_files_and_the_app_shows_them_the_job_and_its_history(): void
    {
        $this->actingAs($this->onJob)->post(route('maintenance.requests.attachments', $this->job), [
            'files' => [UploadedFile::fake()->image('a.jpg', 10, 10)],
        ]);
        $att = $this->job->attachments()->firstOrFail();

        $this->actingAs($this->other)->get(route('attachments.show', $att))->assertOk();

        Sanctum::actingAs($this->other);
        $this->getJson('/api/repair-requests/'.$this->job->id)->assertOk()->assertJsonPath('data.id', $this->job->id);
        $this->getJson('/api/repair-requests/'.$this->job->id.'/logs')->assertOk();
    }

    public function test_a_member_still_sees_only_their_own_jobs(): void
    {
        $stranger = User::factory()->create(['role' => 'member']);

        $this->actingAs($stranger)->get(route('maintenance.requests.show', $this->job))->assertRedirect(route('dashboard'));
        $this->actingAs($this->reporter)->get(route('maintenance.requests.show', $this->job))->assertOk();
    }
}

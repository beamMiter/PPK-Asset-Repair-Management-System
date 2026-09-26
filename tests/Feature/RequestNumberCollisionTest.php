<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRating;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The number of a request is worked out from the highest in the table when the row is created (the column is unique): two requests made
 * at the same moment could be given the same one, and the second failed. It now asks again.
 */
class RequestNumberCollisionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $sup;

    private User $tech;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->sup = User::factory()->create(['role' => 'supervisor']);
        $this->tech = User::factory()->create(['role' => 'it_support', 'name' => 'ช่างเอ']);
        $this->member = User::factory()->create(['role' => 'member']);
    }

    private function job(string $status = 'pending', array $extra = []): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create($extra + [
            'status' => $status, 'reporter_id' => $this->member->id, 'technician_id' => null, 'request_date' => now()->subDays(2),
            // the factory dates every stage at random (some in the future): a job starts with none, its steps write their own
            'acknowledged_at' => null, 'accepted_at' => null, 'started_at' => null, 'on_hold_at' => null, 'resolved_at' => null,
            'closed_at' => null, 'completed_date' => null, 'paused_duration_minutes' => 0,
            'asset_id' => Asset::factory()->create(['his_asset_id' => null])->id,
        ]);
    }

    private function step(User $as, string $action, MaintenanceRequest $req, array $body = [])
    {
        return $this->actingAs($as)->post(route("maintenance.requests.$action", $req), $body);
    }

    private function team(MaintenanceRequest $req): array
    {
        return MaintenanceAssignment::where('maintenance_request_id', $req->id)->where('status', '!=', 'cancelled')
            ->orderBy('id')->get()->map(fn ($a) => "{$a->user->name}:" . ($a->is_lead ? 'lead' : 'member'))->all();
    }

    public function test_a_request_number_taken_a_moment_earlier_by_somebody_else_is_not_an_error(): void
    {
        new MaintenanceRequest();   // boot the model, so the listener below runs after the one that hands out the number
        $collided = null;
        MaintenanceRequest::creating(function (MaintenanceRequest $model) use (&$collided) {
            if ($collided !== null || $model->title !== 'ทับเลข') {
                return;
            }
            // "somebody else" is given, and saves, the very number this request was just given
            $collided = MaintenanceRequest::factory()->create(['request_no' => $model->request_no, 'title' => 'อีกคนหนึ่ง']);
        });

        $created = app(\App\Services\MaintenanceRequestService::class)->createRequest(['title' => 'ทับเลข'], $this->member);

        $this->assertNotNull($collided, 'the collision was staged');
        $this->assertNotSame($collided->request_no, $created->request_no, 'the second request asked again');
        $this->assertSame(2, MaintenanceRequest::whereIn('title', ['ทับเลข', 'อีกคนหนึ่ง'])->count());
        $this->assertSame(
            (int) substr($collided->request_no, -5) + 1,
            (int) substr($created->request_no, -5),
            'and got the next one'
        );
    }
}

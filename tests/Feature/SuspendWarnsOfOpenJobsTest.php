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
 * A suspended technician keeps the jobs they were on and nothing else looks at them: suspending now says how many are still in their hands.
 */
class SuspendWarnsOfOpenJobsTest extends TestCase
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

    public function test_suspending_a_technician_with_open_jobs_says_how_many(): void
    {
        foreach (['in_progress', 'accepted', 'on_hold'] as $status) {
            $req = $this->job($status, ['technician_id' => $this->tech->id]);
            MaintenanceAssignment::create(['maintenance_request_id' => $req->id, 'user_id' => $this->tech->id, 'status' => 'in_progress', 'is_lead' => true]);
        }
        $done = $this->job('closed', ['technician_id' => $this->tech->id]);   // finished work is not "in their hands"
        MaintenanceAssignment::create(['maintenance_request_id' => $done->id, 'user_id' => $this->tech->id, 'status' => 'done', 'is_lead' => true]);
        $dropped = $this->job('in_progress');                                   // they left this team
        MaintenanceAssignment::create(['maintenance_request_id' => $dropped->id, 'user_id' => $this->tech->id, 'status' => 'cancelled', 'is_lead' => false]);

        $this->actingAs($this->admin)->patch(route('admin.users.suspend', $this->tech));

        $this->assertNotNull($this->tech->fresh()->suspended_at);
        $this->assertSame('warning', session('toast.type'));
        $this->assertStringContainsString('ยังมีงานค้าง 3 งาน', session('toast.message'));
    }

    public function test_suspending_somebody_with_no_open_jobs_is_a_plain_success(): void
    {
        $this->actingAs($this->admin)->patch(route('admin.users.suspend', $this->tech));

        $this->assertSame('success', session('toast.type'));
        $this->assertStringNotContainsString('งานค้าง', session('toast.message'));
    }
}

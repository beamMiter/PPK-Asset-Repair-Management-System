<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A reporter may edit what they wrote (title, description, place, contact, asset) while the request is still open for
 * editing — they may not choose who works on it, nor move the date every SLA figure is measured from. Both used to slip
 * through `PUT /maintenance/requests/{id}` (and `PUT /api/repair-requests/{id}`): `user_ids` was read before the
 * non-team fields were stripped, and `request_date` was never stripped.
 */
class RequestUpdateTamperingTest extends TestCase
{
    use RefreshDatabase;

    private function pendingOf(User $reporter): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create([
            'status' => 'pending',
            'reporter_id' => $reporter->id,
            'technician_id' => null,
            'request_date' => '2026-03-01 08:00:00',
            'asset_id' => Asset::factory()->create(['asset_code' => 'T-'.uniqid(), 'serial_number' => uniqid('S'), 'his_asset_id' => null])->id,
        ]);
    }

    private function team(MaintenanceRequest $req, User ...$users): void
    {
        foreach ($users as $u) {
            MaintenanceAssignment::create(['maintenance_request_id' => $req->id, 'user_id' => $u->id, 'status' => 'in_progress', 'is_lead' => false]);
        }
    }

    private function activeTeam(MaintenanceRequest $req): array
    {
        return MaintenanceAssignment::where('maintenance_request_id', $req->id)
            ->where('status', '!=', 'cancelled')->pluck('user_id')->sort()->values()->all();
    }

    public function test_a_reporter_cannot_add_staff_to_their_own_request(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $tech = User::factory()->create(['role' => 'it_support']);
        $req = $this->pendingOf($member);

        $this->actingAs($member)->put(route('maintenance.requests.update', $req), ['title' => 'still mine', 'user_ids' => [$tech->id]]);

        $this->assertSame([], $this->activeTeam($req));
        $this->assertSame('still mine', $req->fresh()->title, 'the legitimate part of the edit still applies');
    }

    public function test_a_reporter_cannot_remove_the_team_by_sending_an_empty_list(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $a = User::factory()->create(['role' => 'it_support']);
        $b = User::factory()->create(['role' => 'network']);
        $req = $this->pendingOf($member);
        $this->team($req, $a, $b);

        $this->actingAs($member)->putJson("/api/repair-requests/{$req->id}", ['title' => 'x', 'user_ids' => []])->assertOk();
        $this->assertSame([$a->id, $b->id], $this->activeTeam($req));

        $this->actingAs($member)->put(route('maintenance.requests.update', $req), ['title' => 'y', 'user_ids' => []]);
        $this->assertSame([$a->id, $b->id], $this->activeTeam($req));
    }

    public function test_a_reporter_cannot_move_the_request_date(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $req = $this->pendingOf($member);

        $this->actingAs($member)->put(route('maintenance.requests.update', $req), ['title' => 'x', 'request_date' => '2019-05-05']);
        $this->actingAs($member)->putJson("/api/repair-requests/{$req->id}", ['title' => 'x', 'request_date' => '2019-05-05']);

        $this->assertSame('2026-03-01 08:00:00', $req->fresh()->request_date->format('Y-m-d H:i:s'));
    }

    public function test_a_reporter_can_still_edit_their_own_text_fields(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $req = $this->pendingOf($member);

        $this->actingAs($member)->put(route('maintenance.requests.update', $req), [
            'title' => 'New title', 'description' => 'More detail', 'location_text' => 'Ward 3', 'reporter_phone' => '0812345678',
        ])->assertRedirect(route('maintenance.requests.show', $req));

        $fresh = $req->fresh();
        $this->assertSame(['New title', 'More detail', 'Ward 3', '0812345678'], [$fresh->title, $fresh->description, $fresh->location_text, $fresh->reporter_phone]);
    }

    public function test_the_team_can_still_manage_the_team_and_the_date(): void
    {
        foreach (['admin', 'supervisor'] as $role) {
            $staff = User::factory()->create(['role' => $role]);
            $a = User::factory()->create(['role' => 'it_support']);
            $b = User::factory()->create(['role' => 'network']);
            $req = $this->pendingOf(User::factory()->create(['role' => 'member']));

            $this->actingAs($staff)->put(route('maintenance.requests.update', $req), ['title' => 't', 'user_ids' => [$a->id, $b->id], 'request_date' => '2026-03-02 09:30:00']);
            $this->assertSame([$a->id, $b->id], $this->activeTeam($req), "$role adds the team");
            $this->assertSame('2026-03-02 09:30:00', $req->fresh()->request_date->format('Y-m-d H:i:s'), "$role sets the date");

            $this->actingAs($staff)->put(route('maintenance.requests.update', $req), ['title' => 't', 'user_ids' => [$b->id]]);
            $this->assertSame([$b->id], $this->activeTeam($req), "$role drops one member");
        }
    }
}

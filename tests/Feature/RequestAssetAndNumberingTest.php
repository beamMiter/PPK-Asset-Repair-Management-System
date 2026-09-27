<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An asset is "in repair" while a request on it is open, and free again when none is. That was kept for a status change but
 * not when the request moved to another asset (the asset it left stayed "in repair" for good) or was deleted (an active one
 * kept the asset busy, and — because the next request number ignored deleted rows — the next new request was given the deleted
 * one's number and failed on the unique key).
 */
class RequestAssetAndNumberingTest extends TestCase
{
    use RefreshDatabase;

    private function asset(): Asset
    {
        return Asset::factory()->create(['asset_code' => 'X-'.uniqid(), 'serial_number' => uniqid('S'), 'his_asset_id' => null, 'status' => 'active']);
    }

    private function open(Asset $asset, string $status = 'pending', ?User $reporter = null): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create([
            'status' => $status,
            'asset_id' => $asset->id,
            'reporter_id' => ($reporter ?? User::factory()->create(['role' => 'member']))->id,
            'technician_id' => null,
        ]);
    }

    public function test_moving_a_request_to_another_asset_frees_the_one_it_left(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        [$a, $b] = [$this->asset(), $this->asset()];
        $req = $this->open($a, 'pending', $member);
        $this->assertSame('in_repair', $a->fresh()->status);

        $this->actingAs($member)->put(route('maintenance.requests.update', $req), ['title' => 'wrong asset, sorry', 'asset_id' => $b->id]);

        $this->assertSame('active', $a->fresh()->status, 'nothing is open on the old asset any more');
        $this->assertSame('in_repair', $b->fresh()->status);
    }

    public function test_the_old_asset_stays_busy_when_another_request_is_still_open_on_it(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        [$a, $b] = [$this->asset(), $this->asset()];
        $this->open($a, 'in_progress');
        $req = $this->open($a, 'pending', $member);

        $this->actingAs($member)->put(route('maintenance.requests.update', $req), ['title' => 'x', 'asset_id' => $b->id]);

        $this->assertSame('in_repair', $a->fresh()->status);
    }

    public function test_deleting_an_active_request_frees_its_asset_and_restoring_it_takes_it_again(): void
    {
        $a = $this->asset();
        $req = $this->open($a, 'in_progress');

        $req->delete();   // soft delete, as the (currently unreachable) DELETE endpoint does
        $this->assertSame('active', $a->fresh()->status);

        MaintenanceRequest::withTrashed()->find($req->id)->restore();
        $this->assertSame('in_repair', $a->fresh()->status);
    }

    public function test_deleting_one_of_two_open_requests_keeps_the_asset_busy(): void
    {
        $a = $this->asset();
        $keep = $this->open($a, 'in_progress');
        $drop = $this->open($a, 'pending');

        $drop->delete();   // soft delete, as the (currently unreachable) DELETE endpoint does

        $this->assertSame('in_repair', $a->fresh()->status);
        $this->assertNotNull($keep->fresh());
    }

    public function test_a_deleted_requests_number_is_not_handed_out_again(): void
    {
        $first = $this->open($this->asset());
        $last = $this->open($this->asset());

        $last->delete();   // soft delete, as the (currently unreachable) DELETE endpoint does

        $next = $this->open($this->asset());   // used to fail on the unique request_no key

        $this->assertNotSame($last->request_no, $next->request_no);
        $this->assertSame((int) substr($last->request_no, -5) + 1, (int) substr($next->request_no, -5), 'the sequence goes on after the deleted one');
        $this->assertSame((int) substr($first->request_no, -5) + 1, (int) substr($last->request_no, -5));
    }
}

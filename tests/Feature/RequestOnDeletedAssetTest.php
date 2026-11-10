<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * `exists:assets,id` counted deleted assets, so a new repair request could name one, and the service then read the
 * department off an asset it could not load: the API answered 500, the form "something went wrong". A request must name
 * a live asset; an edit may keep the asset the request already has, deleted or not.
 */
class RequestOnDeletedAssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_request_cannot_name_a_deleted_asset(): void
    {
        $asset = Asset::factory()->create(['his_asset_id' => null, 'department_id' => null]);
        $asset->delete();
        $member = User::factory()->create(['role' => 'member']);

        Sanctum::actingAs($member);
        $this->postJson('/api/repair-requests', ['title' => 'จอเสีย', 'asset_id' => $asset->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('asset_id');

        $this->actingAs($member)->post(route('maintenance.requests.store'), ['title' => 'จอเสีย', 'asset_id' => $asset->id])
            ->assertSessionHasErrors('asset_id');

        $this->assertSame(0, MaintenanceRequest::count());
    }

    public function test_a_request_already_on_a_deleted_asset_can_still_be_edited(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $asset = Asset::factory()->create(['his_asset_id' => null]);
        $job = MaintenanceRequest::factory()->create([
            'asset_id' => $asset->id, 'status' => 'pending', 'reporter_id' => $member->id, 'technician_id' => null,
        ]);
        $asset->delete(); // from before deleting was refused while a repair is open

        $this->actingAs($member)->put(route('maintenance.requests.update', $job), ['title' => 'แก้หัวข้อ', 'asset_id' => $asset->id])
            ->assertSessionHasNoErrors();

        $this->assertSame('แก้หัวข้อ', $job->fresh()->title);
        $this->assertSame($asset->id, $job->fresh()->asset_id);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the request list (web page and JSON API) shows, in which order, to whom.
 */
class MaintenanceRequestListTest extends TestCase
{
    use RefreshDatabase;

    private function make(array $attrs = []): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create($attrs + ['asset_id' => Asset::factory()->create()->id]);
    }

    private function ids($response): array
    {
        return collect($response->viewData('list')->items())->pluck('id')->all();
    }

    public function test_a_member_sees_only_their_own_requests_and_staff_see_all(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $mine = $this->make(['reporter_id' => $member->id]);
        $others = $this->make(['reporter_id' => User::factory()->create(['role' => 'member'])->id]);

        $this->assertSame([$mine->id], $this->ids($this->actingAs($member)->get(route('maintenance.requests.index'))->assertOk()));

        $staff = User::factory()->create(['role' => 'it_support']);
        $all = $this->ids($this->actingAs($staff)->get(route('maintenance.requests.index'))->assertOk());
        $this->assertEqualsCanonicalizing([$mine->id, $others->id], $all);

        // the JSON API applies the same rule
        $json = $this->actingAs($member)->getJson('/api/repair-requests')->assertOk()->json('data');
        $this->assertSame([$mine->id], collect($json)->pluck('id')->all());
    }

    public function test_default_order_puts_requests_without_a_number_last_then_newest_number_first(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $a = $this->make(['request_no' => 'MR-0001']);
        $c = $this->make(['request_no' => 'MR-0003']);
        $b = $this->make(['request_no' => 'MR-0002']);
        $blank = $this->make();
        \DB::table('maintenance_requests')->where('id', $blank->id)->update(['request_no' => null]);

        $desc = $this->ids($this->actingAs($admin)->get(route('maintenance.requests.index'))->assertOk());
        $this->assertSame([$c->id, $b->id, $a->id, $blank->id], $desc);

        $asc = $this->ids($this->actingAs($admin)->get(route('maintenance.requests.index', ['sort_by' => 'request_no', 'sort_dir' => 'asc']))->assertOk());
        $this->assertSame([$a->id, $b->id, $c->id, $blank->id], $asc, 'a blank number stays last in either direction');
    }

    public function test_sorting_by_id_and_request_date_and_the_choice_is_remembered_per_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $old = $this->make(['request_date' => now()->subDays(10)]);
        $new = $this->make(['request_date' => now()->subDay()]);

        $byDateDesc = $this->ids($this->actingAs($admin)->get(route('maintenance.requests.index', ['sort_by' => 'request_date', 'sort_dir' => 'desc'])));
        $this->assertSame([$new->id, $old->id], $byDateDesc);

        // no query string: the last choice is used again
        $this->assertSame([$new->id, $old->id], $this->ids($this->actingAs($admin)->get(route('maintenance.requests.index'))));

        $byIdAsc = $this->ids($this->actingAs($admin)->get(route('maintenance.requests.index', ['sort_by' => 'id', 'sort_dir' => 'asc'])));
        $this->assertSame([$old->id, $new->id], $byIdAsc);

        // an unknown column is ignored (the remembered one stays)
        $this->assertSame([$old->id, $new->id], $this->ids($this->actingAs($admin)->get(route('maintenance.requests.index', ['sort_by' => 'title; drop table users']))));
    }

    public function test_filters_status_search_asset_and_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $type = MaintenanceRequestType::create(['name' => 'Network', 'is_active' => true]);
        $asset = Asset::factory()->create();

        $match = $this->make(['status' => 'pending', 'title' => 'Switch is down', 'type_id' => $type->id, 'asset_id' => $asset->id]);
        $this->make(['status' => 'pending', 'title' => 'Printer', 'type_id' => null]);
        $this->make(['status' => 'closed', 'title' => 'Switch is down too', 'type_id' => $type->id]);

        $get = fn (array $q) => $this->ids($this->actingAs($admin)->get(route('maintenance.requests.index', $q))->assertOk());

        $this->assertSame([$match->id], $get(['status' => 'pending', 'q' => 'Switch']));
        $this->assertSame([$match->id], $get(['asset_id' => $asset->id]));
        $this->assertCount(2, $get(['type_id' => $type->id]));
        $this->assertCount(1, $get(['type_id' => '__null__']));
        $this->assertSame([$match->id], $get(['type_id' => $type->id, 'status' => 'pending']));
    }

    public function test_the_list_shows_my_response_status_for_the_signed_in_assignee(): void
    {
        $tech = User::factory()->create(['role' => 'it_support']);
        $req = $this->make();
        \DB::table('maintenance_assignments')->insert([
            'maintenance_request_id' => $req->id, 'user_id' => $tech->id, 'status' => 'in_progress',
            'response_status' => 'accepted', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $row = $this->actingAs($tech)->get(route('maintenance.requests.index'))->viewData('list')->items()[0];

        $this->assertSame('accepted', $row->my_response_status);
    }
}

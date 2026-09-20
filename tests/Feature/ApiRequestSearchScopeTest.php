<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * `GET /api/search/maintenance-requests` read the table directly: no visibility rule and no soft-delete filter, so any
 * signed-in user — a member with no requests of their own included — got other people's request numbers, titles and
 * statuses, deleted ones too. It now follows the same rule as the request list.
 */
class ApiRequestSearchScopeTest extends TestCase
{
    use RefreshDatabase;

    private function make(User $reporter, array $attrs = []): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create($attrs + [
            'reporter_id' => $reporter->id,
            'asset_id' => Asset::factory()->create(['asset_code' => 'S-'.uniqid(), 'serial_number' => uniqid('S'), 'his_asset_id' => null])->id,
        ]);
    }

    private function search(User $as, string $query = ''): array
    {
        Sanctum::actingAs($as);

        return collect($this->getJson('/api/search/maintenance-requests'.$query)->assertOk()->json('data'))->pluck('id')->sort()->values()->all();
    }

    public function test_a_member_finds_only_their_own_requests(): void
    {
        $me = User::factory()->create(['role' => 'member']);
        $mine = $this->make($me, ['title' => 'Printer jam']);
        $this->make(User::factory()->create(['role' => 'member']), ['title' => 'Printer on fire']);

        $this->assertSame([$mine->id], $this->search($me), 'no query');
        $this->assertSame([$mine->id], $this->search($me, '?q=Printer'), 'a query that also matches somebody else\'s');
    }

    public function test_a_member_with_no_requests_gets_nothing_not_everyone_elses(): void
    {
        $this->make(User::factory()->create(['role' => 'member']));
        $this->make(User::factory()->create(['role' => 'member']));

        $this->assertSame([], $this->search(User::factory()->create(['role' => 'member'])));
    }

    public function test_staff_search_everything(): void
    {
        $a = $this->make(User::factory()->create(['role' => 'member']));
        $b = $this->make(User::factory()->create(['role' => 'member']));

        foreach (['admin', 'supervisor', 'it_support'] as $role) {
            $this->assertSame([$a->id, $b->id], $this->search(User::factory()->create(['role' => $role])), $role);
        }
    }

    public function test_deleted_requests_are_never_returned_not_even_to_staff(): void
    {
        $me = User::factory()->create(['role' => 'member']);
        $gone = $this->make($me, ['title' => 'Deleted one']);
        $kept = $this->make($me, ['title' => 'Kept one']);
        $gone->delete();

        $this->assertSame([$kept->id], $this->search($me));
        $this->assertSame([$kept->id], $this->search(User::factory()->create(['role' => 'admin'])));
    }

    public function test_the_response_shape_status_filter_and_limit_are_unchanged(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create(['role' => 'member']);
        $pending = $this->make($member, ['status' => 'pending', 'title' => 'Alpha']);
        $this->make($member, ['status' => 'closed', 'title' => 'Beta']);
        $this->make($member, ['status' => 'closed', 'title' => 'Gamma']);

        Sanctum::actingAs($admin);
        $row = $this->getJson('/api/search/maintenance-requests?status=pending')->assertOk()->json('data.0');
        $this->assertSame(['id', 'request_no', 'status', 'title'], collect($row)->keys()->sort()->values()->all());
        $this->assertSame($pending->id, $row['id']);

        $this->assertCount(2, $this->getJson('/api/search/maintenance-requests?limit=2')->json('data'));
        $this->assertCount(1, $this->getJson('/api/search/maintenance-requests?limit=0')->assertOk()->json('data'), 'limit is clamped to at least 1');
        $this->assertCount(3, $this->getJson('/api/search/maintenance-requests?limit=500')->assertOk()->json('data'), 'and to at most 50');
    }

    public function test_a_guest_is_refused(): void
    {
        $this->getJson('/api/search/maintenance-requests')->assertUnauthorized();
    }
}

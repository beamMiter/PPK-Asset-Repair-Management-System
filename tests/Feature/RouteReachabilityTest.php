<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Two routes were declared but could not work: `GET /api/repair-requests/my-jobs` sat below `GET /api/repair-requests/{req}`
 * (so "my-jobs" was taken as a request id and answered 404 to everyone), and `DELETE …/assignments/{assignment}` threw a
 * TypeError on every call — nothing in the app used it, the team is edited through `assignments.store`.
 */
class RouteReachabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_static_route_is_shadowed_by_an_earlier_route(): void
    {
        $shadowed = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (str_contains($route->uri(), '{') || str_starts_with($route->uri(), '_') || $route->uri() === '/') {
                continue;
            }

            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                try {
                    $hit = Route::getRoutes()->match(Request::create('/'.$route->uri(), $method));
                } catch (\Throwable $e) {
                    $shadowed[] = "$method /{$route->uri()} matches nothing (".class_basename($e).')';
                    continue;
                }

                if ($hit !== $route) {
                    $shadowed[] = "$method /{$route->uri()} is answered by {$hit->uri()} ({$hit->getActionName()})";
                }
            }
        }

        $this->assertSame([], $shadowed);
    }

    public function test_the_api_my_jobs_list_answers_with_the_users_own_jobs_only(): void
    {
        $asset = Asset::factory()->create(['asset_code' => 'MJ-'.uniqid(), 'serial_number' => uniqid('S'), 'his_asset_id' => null]);
        $me = User::factory()->create(['role' => 'it_support']);
        $colleague = User::factory()->create(['role' => 'network']);

        $mine = MaintenanceRequest::factory()->create(['asset_id' => $asset->id, 'status' => 'in_progress', 'technician_id' => null, 'title' => 'Mine']);
        MaintenanceAssignment::create(['maintenance_request_id' => $mine->id, 'user_id' => $me->id, 'status' => 'in_progress', 'is_lead' => false]);
        $cancelled = MaintenanceRequest::factory()->create(['asset_id' => $asset->id, 'status' => 'in_progress', 'technician_id' => null]);
        MaintenanceAssignment::create(['maintenance_request_id' => $cancelled->id, 'user_id' => $me->id, 'status' => 'cancelled', 'is_lead' => false]);
        $theirs = MaintenanceRequest::factory()->create(['asset_id' => $asset->id, 'status' => 'in_progress', 'technician_id' => null]);
        MaintenanceAssignment::create(['maintenance_request_id' => $theirs->id, 'user_id' => $colleague->id, 'status' => 'in_progress', 'is_lead' => false]);

        $res = $this->actingAs($me, 'sanctum')->getJson('/api/repair-requests/my-jobs')->assertOk();

        $this->assertSame([$mine->id], collect($res->json('data.data'))->pluck('id')->all());
        $this->assertSame('repair-requests.my-jobs', Route::getRoutes()->match(Request::create('/api/repair-requests/my-jobs', 'GET'))->getName());
    }

    public function test_the_api_my_jobs_list_is_for_staff_and_signed_in_users_only(): void
    {
        $this->getJson('/api/repair-requests/my-jobs')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'member']), 'sanctum')->getJson('/api/repair-requests/my-jobs')->assertForbidden();
    }

    public function test_a_request_id_route_still_takes_an_id(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $req = MaintenanceRequest::factory()->create([
            'reporter_id' => $member->id,
            'asset_id' => Asset::factory()->create(['asset_code' => 'MJ-'.uniqid(), 'serial_number' => uniqid('S'), 'his_asset_id' => null])->id,
        ]);

        $this->actingAs($member, 'sanctum')->getJson("/api/repair-requests/{$req->id}")->assertOk()->assertJsonPath('data.id', $req->id);
    }

    public function test_the_broken_single_assignment_delete_route_is_gone_and_the_team_is_still_editable(): void
    {
        $this->assertFalse(Route::has('maintenance.requests.assignments.destroy'));
        $this->assertTrue(Route::has('maintenance.requests.assignments.store'), 'the team is edited through this route');

        $req = MaintenanceRequest::factory()->create(['asset_id' => Asset::factory()->create(['asset_code' => 'MJ-'.uniqid(), 'serial_number' => uniqid('S'), 'his_asset_id' => null])->id]);
        $assignment = MaintenanceAssignment::create(['maintenance_request_id' => $req->id, 'user_id' => User::factory()->create(['role' => 'it_support'])->id, 'status' => 'in_progress', 'is_lead' => false]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->delete("/maintenance/requests/{$req->id}/assignments/{$assignment->id}")
            ->assertNotFound(); // no route for that URL any more (it used to be a TypeError, i.e. a 500)
    }
}

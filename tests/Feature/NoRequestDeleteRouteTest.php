<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * `DELETE /api/repair-requests/{req}` was documented as "admin and supervisor only" but answered 403 to everybody: the policy has no
 * `delete` method, and Laravel does not run a policy's `before()` (the admin bypass) for a method that is not there. Nothing used it.
 * A request is the record of who did what, so the owner chose to take the route away rather than make it work.
 */
class NoRequestDeleteRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_there_is_no_route_that_deletes_a_request(): void
    {
        $this->assertFalse(Route::has('repair-requests.destroy'));

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (in_array('DELETE', $route->methods(), true) && preg_match('#(repair-requests|maintenance/requests)/\{(req|maintenanceRequest)\}$#', $route->uri())) {
                $this->fail('a route deletes a request: DELETE /'.$route->uri());
            }
        }
    }

    public function test_an_admin_and_a_supervisor_cannot_delete_a_request_through_the_api_and_it_stays(): void
    {
        $job = MaintenanceRequest::factory()->create(['status' => 'pending', 'technician_id' => null, 'asset_id' => null]);

        foreach (['admin', 'supervisor'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));

            $this->deleteJson("/api/repair-requests/{$job->id}")->assertStatus(405);
        }

        $this->assertNotNull(MaintenanceRequest::find($job->id), 'the request is still there');
    }
}

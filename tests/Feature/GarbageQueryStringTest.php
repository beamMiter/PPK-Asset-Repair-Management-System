<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Department;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A typo in a URL must never be a server error. Every parameter-less GET page and API route is requested as an admin
 * with nonsense filter values (bad dates, negative / huge paging, SQL-ish text, unknown sort columns); none may answer 5xx.
 * This is how `/api/stats/assets/by-department` (unknown column) and `?limit=-1` on the technician board were found.
 */
class GarbageQueryStringTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_listing_route_answers_with_a_server_error(): void
    {
        $this->seed();
        $admin = User::where('role', 'admin')->firstOrFail();

        $garbage = [
            [],
            ['from' => 'garbage', 'to' => 'zzz', 'page' => 'abc', 'per_page' => '-5', 'q' => "x'\"%_\\", 'status' => 'nope', 'sort_by' => 'x', 'sort_dir' => 'y',
                'type_id' => 'abc', 'asset_id' => 'abc', 'category_id' => 'x', 'department_id' => 'z', 'limit' => 'abc', 'month' => 'nope', 'year' => 'abc',
                'role' => 'a', 'tech' => 'x', 'technician_id' => 'q', 'filter' => 'zz', 'thread_id' => 'x'],
            ['page' => '999999', 'per_page' => '1000000', 'limit' => '-1', 'q' => str_repeat('é', 5000), 'from' => '0000-00-00', 'to' => '9999-99-99',
                'sort_by' => 'id; drop', 'sort_dir' => 'sideways'],
        ];

        $failures = [];
        $checked = 0;

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = $route->uri();

            if (! in_array('GET', $route->methods(), true) || str_contains($uri, '{') || $uri === 'up'
                || str_starts_with($uri, '_') || str_starts_with($uri, 'storage') || str_starts_with($uri, 'broadcasting')) {
                continue;
            }

            foreach ($garbage as $i => $query) {
                $url = '/'.$uri.($query ? '?'.http_build_query($query) : '');
                $response = str_starts_with($uri, 'api/')
                    ? $this->actingAs($admin, 'sanctum')->getJson($url)
                    : $this->actingAs($admin)->get($url);

                $checked++;
                if ($response->getStatusCode() >= 500) {
                    $failures[] = "GET $url#$i → ".$response->getStatusCode();
                }
            }
        }

        $this->assertGreaterThan(30, $checked, 'the route scan should have found the listing routes');
        $this->assertSame([], array_values(array_unique($failures)));
    }

    public function test_assets_by_department_statistics_name_the_departments(): void
    {
        $dept = Department::factory()->create(['name_th' => 'ห้องปฏิบัติการ', 'name_en' => 'Lab']);
        Asset::factory()->count(2)->create(['department_id' => $dept->id]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $res = $this->getJson('/api/stats/assets/by-department')->assertOk();

        $this->assertContains(['id' => $dept->id, 'name' => 'ห้องปฏิบัติการ', 'count' => 2], $res->json('data'));
    }

    public function test_technician_board_clamps_the_page_size(): void
    {
        // needs at least one listed technician — with none, paginate() never runs its LIMIT query
        $tech = User::factory()->create(['role' => 'it_support']);
        $req = MaintenanceRequest::factory()->create(['asset_id' => Asset::factory()->create()->id, 'technician_id' => $tech->id]);
        MaintenanceAssignment::create(['maintenance_request_id' => $req->id, 'user_id' => $tech->id, 'status' => 'in_progress', 'is_lead' => true]);

        $admin = User::factory()->create(['role' => 'admin']);

        foreach ([-1, 0, 'abc', 100000] as $limit) {
            $res = $this->actingAs($admin)->get(route('maintenance.requests.rating.technicians', ['limit' => $limit]))->assertOk();

            $this->assertCount(1, $res->viewData('technicians')->items(), "limit=$limit");
        }
    }
}

<?php

namespace Tests\Feature;

use App\Http\Controllers\MaintenanceRatingController;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * R4: guardRatingAccess() and store() both need the resolved technician id.
 * The resolver used to run its (whereHas + 3 orderBy) query once per call;
 * it is now memoised per request for the lifetime of the controller.
 */
class RatingTechnicianResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolver_is_memoised_within_a_request(): void
    {
        $lead   = User::factory()->create(['role' => 'technician']);
        $helper = User::factory()->create(['role' => 'it_support']);
        $mr     = MaintenanceRequest::factory()->create();

        MaintenanceAssignment::create([
            'maintenance_request_id' => $mr->id,
            'user_id'                => $helper->id,
            'role'                   => 'it_support',
            'is_lead'                => false,
            'assigned_at'            => now()->subDay(),
            'status'                 => MaintenanceAssignment::STATUS_DONE,
        ]);
        MaintenanceAssignment::create([
            'maintenance_request_id' => $mr->id,
            'user_id'                => $lead->id,
            'role'                   => 'technician',
            'is_lead'                => true,
            'assigned_at'            => now(),
            'status'                 => MaintenanceAssignment::STATUS_DONE,
        ]);

        $method = new ReflectionMethod(MaintenanceRatingController::class, 'resolveTechnicianIdForRating');
        $method->setAccessible(true);
        $controller = app(MaintenanceRatingController::class);

        DB::enableQueryLog();
        $first  = $method->invoke($controller, $mr);
        $second = $method->invoke($controller, $mr);
        $queries = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'maintenance_assignments'))
            ->count();
        DB::disableQueryLog();

        $this->assertSame($lead->id, $first, 'is_lead assignment should win');
        $this->assertSame($first, $second);
        $this->assertSame(1, $queries, 'resolver should hit maintenance_assignments only once');
    }
}

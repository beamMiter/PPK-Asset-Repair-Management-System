<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_summary_structure(): void
    {
        $user = User::factory()->create();
        Asset::factory()->count(5)->create();
        MaintenanceRequest::factory()->count(12)->create();

        Sanctum::actingAs($user);
        Cache::flush();

        $resp = $this->getJson('/api/stats/summary');
        $resp->assertOk()->assertJsonStructure([
            'assets_total',
            'requests_open',
            'requests_returned',
            'requests_closed',
            'recent_daily' => [
                '*' => ['date', 'count'],
            ],
        ]);
    }

    public function test_open_and_closed_counts_use_the_canonical_status_sets(): void
    {
        $user = User::factory()->create();
        Asset::factory()->count(3)->create();

        // open bucket — includes 'acknowledged'
        MaintenanceRequest::factory()->create(['status' => MaintenanceRequest::STATUS_ACKNOWLEDGED]);
        MaintenanceRequest::factory()->create(['status' => MaintenanceRequest::STATUS_IN_PROGRESS]);
        // terminal bucket — includes 'rejected'
        MaintenanceRequest::factory()->create(['status' => MaintenanceRequest::STATUS_REJECTED]);
        MaintenanceRequest::factory()->create(['status' => MaintenanceRequest::STATUS_CLOSED]);

        Sanctum::actingAs($user);
        Cache::flush();

        $resp = $this->getJson('/api/stats/summary')->assertOk();

        $this->assertSame(2, $resp->json('requests_open'), 'acknowledged + in_progress');
        $this->assertSame(2, $resp->json('requests_closed'), 'rejected + closed');
    }

    public function test_every_status_is_counted_once_so_open_returned_and_closed_add_up_to_the_total(): void
    {
        $user = User::factory()->create();

        // one request in every status the system has (the old "completed" and the "returned" one included)
        $open = ['pending', 'acknowledged', 'accepted', 'in_progress', 'on_hold'];
        $closed = ['resolved', 'closed', 'cancelled', 'rejected', 'completed'];
        foreach ([...$open, 'returned', ...$closed] as $status) {
            MaintenanceRequest::factory()->create(['status' => $status]);
        }

        Sanctum::actingAs($user);
        Cache::flush();

        $resp = $this->getJson('/api/stats/summary')->assertOk();

        $this->assertSame(count($open), $resp->json('requests_open'), 'the open ones, not the sent-back one');
        $this->assertSame(1, $resp->json('requests_returned'), 'the one sent back to its reporter');
        $this->assertSame(count($closed), $resp->json('requests_closed'), 'resolved, closed, cancelled, rejected and the old "completed"');
        $this->assertSame(
            MaintenanceRequest::count(),
            $resp->json('requests_open') + $resp->json('requests_returned') + $resp->json('requests_closed'),
            'no request is left out of the three numbers',
        );
    }

    public function test_an_answer_cached_before_the_new_number_existed_is_not_served(): void
    {
        Sanctum::actingAs(User::factory()->create());
        Cache::flush();

        // what an older version of the app left in the cache (60 seconds at most, but a deploy must not hand it to a client that now expects the new key)
        Cache::put('stats:summary:v1', ['assets_total' => 99, 'requests_open' => 99, 'requests_closed' => 99, 'recent_daily' => []], 60);

        $resp = $this->getJson('/api/stats/summary')->assertOk();

        $resp->assertJsonStructure(['assets_total', 'requests_open', 'requests_returned', 'requests_closed', 'recent_daily']);
        $this->assertNotSame(99, $resp->json('assets_total'));
    }
}

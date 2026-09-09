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
}

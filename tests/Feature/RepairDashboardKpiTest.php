<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RD2: the KPI cards read "ปีนี้ / ปีที่แล้ว" and the query is year-to-date,
 * but the $kpi keys were named thisMonth/lastMonth/thisMonthCompleted. Renamed
 * to thisYear/lastYear/thisYearCompleted — behaviour is unchanged, the page
 * must still render and the year-to-date counts must be right.
 */
class RepairDashboardKpiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_with_year_to_date_kpi(): void
    {
        MaintenanceRequest::factory()->create(['request_date' => now()->startOfYear()->addDay()]);
        MaintenanceRequest::factory()->create(['request_date' => now()]);
        MaintenanceRequest::factory()->create(['request_date' => now()->subYear()]);

        $kpi = $this->actingAs(User::factory()->create())
            ->get(route('repair.dashboard'))
            ->assertOk()
            ->viewData('kpi');

        $this->assertArrayHasKey('thisYear', $kpi);
        $this->assertArrayHasKey('thisYearCompleted', $kpi);
        $this->assertArrayNotHasKey('thisMonth', $kpi);

        $this->assertSame(2, $kpi['thisYear']);
        $this->assertSame(1, $kpi['lastYear']);
    }

    public function test_average_close_time_uses_every_finished_request_not_a_3000_row_sample(): void
    {
        $user = User::factory()->create();
        $asset = \App\Models\Asset::factory()->create();
        $start = now()->subDays(30)->startOfHour();

        $row = fn (int $minutes) => [
            'asset_id' => $asset->id,
            'reporter_id' => $user->id,
            'title' => 't',
            'status' => 'closed',
            'request_date' => $start,
            'completed_date' => $start->copy()->addMinutes($minutes),
            'created_at' => $start,
            'updated_at' => $start,
        ];

        // 3000 requests closed after 1 h, then one that took 3002 h: the mean over all 3001 is exactly 2.0 h,
        // while the old `limit(3000)` sample (the first 3000 rows) said 1.0 h.
        collect(range(1, 3000))->chunk(500)->each(
            fn ($chunk) => \DB::table('maintenance_requests')->insert($chunk->map(fn () => $row(60))->all())
        );
        \DB::table('maintenance_requests')->insert($row(60 * 3002));

        $kpi = $this->actingAs($user)->get(route('repair.dashboard'))->assertOk()->viewData('kpi');

        $this->assertSame(2.0, $kpi['avgResolveHours']);
    }

    public function test_average_close_time_is_null_when_nothing_is_finished(): void
    {
        MaintenanceRequest::factory()->create(['status' => 'pending', 'completed_date' => null]);

        $kpi = $this->actingAs(User::factory()->create())->get(route('repair.dashboard'))->viewData('kpi');

        $this->assertNull($kpi['avgResolveHours']);
    }
}

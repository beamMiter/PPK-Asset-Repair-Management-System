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
}

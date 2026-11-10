<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Department;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The repair dashboard, the /api/stats numbers and the asset autocomplete read the tables directly, past the soft-delete
 * scope: a deleted request still counted in every total and chart, and a deleted asset was still offered to pick. Only
 * live rows count now.
 */
class DeletedRowsAreNotCountedTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function job(string $status, array $extra = []): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create($extra + [
            'status' => $status, 'request_date' => now(), 'created_at' => now(), 'asset_id' => null,
            'resolved_at' => null, 'closed_at' => null, 'completed_date' => null,
        ]);
    }

    /** One live job and one deleted job of each kind the numbers count. */
    private function liveAndDeleted(): void
    {
        $this->job('pending');
        $this->job('pending')->delete();
        $this->job('closed', ['completed_date' => now()]);
        $this->job('closed', ['completed_date' => now()])->delete();
    }

    public function test_the_dashboard_totals_count_live_requests_only(): void
    {
        $this->liveAndDeleted();

        $page = $this->actingAs($this->admin)->get(route('repair.dashboard'))->assertOk();

        $stats = $page->viewData('stats');
        $this->assertSame(2, $stats['total']);
        $this->assertSame(1, $stats['pending']);
        $this->assertSame(1, $stats['completed']);

        $kpi = $page->viewData('kpi');
        $this->assertSame(2, $kpi['thisYear']);
        $this->assertSame(1, $kpi['thisYearCompleted']);
    }

    public function test_the_dashboard_charts_count_live_requests_only(): void
    {
        $this->liveAndDeleted();

        $page = $this->actingAs($this->admin)->get(route('repair.dashboard'))->assertOk();

        $this->assertSame(2, collect($page->viewData('monthlyTrend'))->sum('cnt'));
        $this->assertSame(2, collect($page->viewData('byAssetType'))->sum('cnt'));
        $this->assertSame(2, collect($page->viewData('byDept'))->sum('cnt'));
    }

    public function test_the_technician_workload_counts_live_jobs_only(): void
    {
        $tech = User::factory()->create(['role' => 'technician']);
        $this->job('in_progress', ['technician_id' => $tech->id, 'started_at' => now()]);
        $this->job('in_progress', ['technician_id' => $tech->id, 'started_at' => now()])->delete();

        $workload = $this->actingAs($this->admin)->get(route('repair.dashboard'))->assertOk()->viewData('techWorkload');

        $this->assertSame(1, collect($workload)->firstWhere('id', $tech->id)['total'] ?? null);
    }

    public function test_the_api_summary_and_status_counts_count_live_rows_only(): void
    {
        $this->liveAndDeleted();
        Asset::factory()->create(['his_asset_id' => null]);
        Asset::factory()->create(['his_asset_id' => null])->delete();
        Sanctum::actingAs($this->admin);

        $summary = $this->getJson('/api/stats/summary')->assertOk();
        $this->assertSame(1, $summary->json('assets_total'));
        $this->assertSame(1, $summary->json('requests_open'));
        $this->assertSame(1, $summary->json('requests_closed'));
        $this->assertSame(2, collect($summary->json('recent_daily'))->sum('count'));

        $this->assertSame(['closed' => 1, 'pending' => 1], collect($this->getJson('/api/stats/maintenance/status-counts')->json('data'))->sortKeys()->all());
    }

    public function test_the_api_technician_summary_counts_live_jobs_only(): void
    {
        $tech = User::factory()->create(['role' => 'technician']);
        $this->job('in_progress', ['technician_id' => $tech->id, 'started_at' => now()]);
        $this->job('in_progress', ['technician_id' => $tech->id, 'started_at' => now()])->delete();
        Sanctum::actingAs($this->admin);

        $row = collect($this->getJson('/api/stats/maintenance/technicians')->assertOk()->json('data'))->firstWhere('id', $tech->id);

        $this->assertSame(1, $row['total']);
        $this->assertSame(1, $row['open']);
    }

    public function test_the_api_assets_by_department_counts_live_assets_only(): void
    {
        $dept = Department::factory()->create();
        Asset::factory()->create(['his_asset_id' => null, 'department_id' => $dept->id]);
        Asset::factory()->create(['his_asset_id' => null, 'department_id' => $dept->id])->delete();
        Sanctum::actingAs($this->admin);

        $row = collect($this->getJson('/api/stats/assets/by-department')->assertOk()->json('data'))->firstWhere('id', $dept->id);

        $this->assertSame(1, $row['count']);
    }

    public function test_the_asset_autocomplete_does_not_offer_a_deleted_asset(): void
    {
        Asset::factory()->create(['his_asset_id' => null, 'asset_code' => 'LIVE-1']);
        Asset::factory()->create(['his_asset_id' => null, 'asset_code' => 'GONE-1'])->delete();
        Sanctum::actingAs(User::factory()->create(['role' => 'member']));

        $codes = collect($this->getJson('/api/search/assets?q=-1')->assertOk()->json('data'))->pluck('code')->all();

        $this->assertSame(['LIVE-1'], $codes);
    }
}

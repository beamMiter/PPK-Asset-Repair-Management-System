<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `/maintenance/sla-performance?from=garbage` used to be a 500 (Carbon::parse threw); an unusable date is ignored
 * and the default range (this year → end of this month) is used.
 */
class SlaDashboardInputTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_date_that_is_not_a_date_is_ignored(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['garbage', '99-99-99', ' ', '2026-13-45'] as $bad) {
            $this->actingAs($admin)->get(route('maintenance.sla.index', ['from' => $bad, 'to' => $bad]))->assertOk();
        }
        $this->actingAs($admin)->get(route('maintenance.sla.index', ['from' => ['a'], 'to' => ['b']]))->assertOk();
    }

    public function test_a_real_range_is_still_applied(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('maintenance.sla.index', ['from' => '2026-01-01', 'to' => '2026-01-31']))->assertOk();
    }

    public function test_the_pdf_report_builds_even_with_a_bad_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $res = $this->actingAs($admin)->post(route('maintenance.sla.report'), ['from' => 'garbage']);

        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('content-type'));
    }
}

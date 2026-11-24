<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest as MR;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A reporter whose repair is done ("ซ่อมบำรุงเสร็จสิ้น") is the one who has to approve it ("อนุมัติปิดงาน"), and only after that can they rate it.
 * Nothing told them: they had to open the list and notice the status. The banner says it where they land (the dashboard, above the list), with
 * how many wait for them and a link to the list already filtered. The sibling of the banner for requests sent back (ReturnedRequestsBannerTest).
 */
class ResolvedRequestsBannerTest extends TestCase
{
    use RefreshDatabase;

    private User $reporter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reporter = User::factory()->create(['role' => 'member']);
    }

    private function request(string $status, ?User $reporter = null, array $overrides = []): MR
    {
        return MR::factory()->create(array_merge([
            'status' => $status, 'reporter_id' => ($reporter ?? $this->reporter)->id,
            'technician_id' => null, 'asset_id' => null,
        ], $overrides));
    }

    private function dashboard(?User $as = null)
    {
        return $this->actingAs($as ?? $this->reporter)->get(route('repair.dashboard'))->assertOk();
    }

    private function list(array $query = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->reporter)->get(route('maintenance.requests.index', $query))->assertOk();
    }

    public function test_a_reporter_with_finished_repairs_is_told_how_many_wait_for_their_approval(): void
    {
        $this->request('resolved');
        $this->request('resolved');

        foreach ([$this->dashboard(), $this->list()] as $page) {
            $page->assertSee('data-resolved-banner', false)
                ->assertSee('ซ่อมบำรุงเสร็จสิ้นแล้ว 2 รายการ')
                ->assertSee('อนุมัติปิดงาน')
                ->assertSee(route('maintenance.requests.index', ['status' => 'resolved']), false);
        }
    }

    public function test_there_is_no_banner_when_nothing_of_theirs_waits_for_approval(): void
    {
        foreach (['pending', 'acknowledged', 'accepted', 'in_progress', 'on_hold', 'returned', 'closed', 'cancelled', 'rejected'] as $status) {
            $this->request($status);
        }

        $this->dashboard()->assertDontSee('data-resolved-banner', false);
        $this->list()->assertDontSee('data-resolved-banner', false);
    }

    public function test_it_counts_only_what_the_viewer_reported(): void
    {
        $someoneElse = User::factory()->create(['role' => 'member']);
        $this->request('resolved', $someoneElse);
        $this->request('resolved', $someoneElse);

        $this->dashboard()->assertDontSee('data-resolved-banner', false);

        // an admin sees every request, and is still told only about their own
        $admin = User::factory()->create(['role' => 'admin']);
        $this->dashboard($admin)->assertDontSee('data-resolved-banner', false);
        $this->request('resolved', $admin);
        $this->dashboard($admin)->assertSee('ซ่อมบำรุงเสร็จสิ้นแล้ว 1 รายการ');
    }

    public function test_a_deleted_request_is_not_counted(): void
    {
        $this->request('resolved')->delete();

        $this->dashboard()->assertDontSee('data-resolved-banner', false);
    }

    public function test_it_goes_once_the_request_is_approved(): void
    {
        $request = $this->request('resolved');
        $this->dashboard()->assertSee('data-resolved-banner', false);

        $request->update(['status' => 'closed']);

        $this->dashboard()->assertDontSee('data-resolved-banner', false);
    }

    public function test_it_is_not_repeated_above_the_list_of_exactly_those_requests(): void
    {
        $this->request('resolved');

        $this->list(['status' => 'resolved'])->assertDontSee('data-resolved-banner', false);
        $this->list(['status' => 'pending'])->assertSee('data-resolved-banner', false);
    }

    public function test_the_manual_tells_the_reporter_about_the_banner_at_the_approval_step(): void
    {
        $manual = (string) file_get_contents(resource_path('views/help/manual.blade.php'));

        $this->assertStringContainsString('จะมีแถบเตือนสีเขียวที่หน้าแดชบอร์ดและหน้ารายการงานซ่อม', $manual);
    }

    public function test_it_sits_beside_the_banner_for_requests_sent_back_without_replacing_it(): void
    {
        $this->request('resolved');
        $this->request('returned', null, ['status_updated_at' => now()]);

        $this->dashboard()
            ->assertSee('data-resolved-banner', false)
            ->assertSee('data-returned-banner', false);
    }
}

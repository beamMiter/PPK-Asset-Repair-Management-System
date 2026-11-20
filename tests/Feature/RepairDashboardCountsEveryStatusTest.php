<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequest as MR;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard's "สถานะการทำงาน" donut counted three groups (waiting, in progress, done) and wrote the fourth as a hard-coded 0 under a label
 * ("อนุมัติแล้ว") that was not its own, while "ใบแจ้งซ่อมสะสมทั้งหมด" counted every request. A request that was cancelled, refused or sent back
 * therefore sat in the total and in no slice, and the slices never added up to it. Now every status has a slice, the labels travel with the
 * numbers, and a status added to the model later is caught here instead of falling out of the chart.
 */
class RepairDashboardCountsEveryStatusTest extends TestCase
{
    use RefreshDatabase;

    private function stats(array $query = []): array
    {
        return $this->actingAs(User::factory()->create(['role' => 'member']))->get(route('repair.dashboard', $query))->assertOk()->viewData('stats');
    }

    private function slices(array $query = []): array
    {
        return $this->actingAs(User::factory()->create(['role' => 'member']))->get(route('repair.dashboard', $query))->assertOk()->viewData('statusSlices');
    }

    private function request(string $status, int $n = 1): void
    {
        MR::factory()->count($n)->create(['status' => $status, 'asset_id' => null]);
    }

    public function test_every_status_the_model_knows_lands_in_a_slice_so_the_slices_add_up_to_the_total(): void
    {
        $statuses = array_keys(MR::statusLabels());
        foreach ($statuses as $status) {
            $this->request($status);
        }

        $slices = $this->slices();
        $stats = $this->stats();

        $this->assertSame(count($statuses), $stats['total'], 'one request per status');
        $this->assertSame($stats['total'], array_sum(array_column($slices, 'value')), 'the slices add up to the total: nothing is in the total and in no slice');
    }

    public function test_cancelled_refused_and_sent_back_requests_have_slices_of_their_own(): void
    {
        $this->request('pending', 2);
        $this->request('in_progress');
        $this->request('on_hold');
        $this->request('resolved');
        $this->request('closed', 2);
        $this->request('returned', 3);
        $this->request('cancelled', 2);
        $this->request('rejected');

        $byLabel = collect($this->slices())->pluck('value', 'label')->all();

        $this->assertSame([
            'รอดำเนินการ' => 2,
            'กำลังดำเนินการ' => 2,
            'ซ่อมบำรุงเสร็จสิ้น' => 3,
            'ส่งกลับแก้ไข' => 3,
            'ยกเลิก/ไม่รับเรื่อง' => 3,
        ], $byLabel);
    }

    public function test_a_deleted_request_is_in_no_slice(): void
    {
        $this->request('cancelled', 2);
        MR::query()->where('status', 'cancelled')->first()->delete();

        $this->assertSame(1, $this->stats()['cancelled']);
        $this->assertSame(1, $this->stats()['total']);
    }

    public function test_the_donut_gets_its_labels_and_numbers_together_from_the_page(): void
    {
        $this->request('returned', 2);

        $html = $this->actingAs(User::factory()->create(['role' => 'member']))->get(route('repair.dashboard'))->assertOk()->getContent();

        preg_match('/<canvas id="statusDonut" data-labels=\'([^\']*)\'\s+data-values=\'([^\']*)\'/', $html, $m);
        $this->assertNotEmpty($m, 'the status donut carries data-labels and data-values like the type donut');
        $labels = json_decode(html_entity_decode($m[1]), true);
        $values = json_decode(html_entity_decode($m[2]), true);

        $this->assertCount(count($labels), $values, 'one number per label: a label without a number slid every slice after it');
        $this->assertSame(2, $values[array_search('ส่งกลับแก้ไข', $labels, true)]);
    }

    public function test_the_filter_by_a_group_still_counts_that_group_only(): void
    {
        $this->request('pending', 2);
        $this->request('returned', 1);
        $this->request('cancelled', 1);
        $this->request('rejected', 1);

        $stats = $this->stats(['status' => 'cancelled']);

        $this->assertSame(2, $stats['total'], 'cancelled and refused are one group, as the filter always had it');
        $this->assertSame(2, $stats['cancelled']);
        $this->assertSame(0, $stats['pending']);
    }
}

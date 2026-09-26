<?php

namespace Tests\Feature\Ui;

use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The workflow buttons of the job page (รับทราบ, รับเรื่อง, ดำเนินการ, หยุดชั่วคราว, …) are the same size and shape as the buttons
 * beside them (แก้ไข, พิมพ์ PDF, กลับ): a plain button, its icon in front of its label. They used to be `split` buttons — the icon in a
 * darker block of its own on the left — which made them wider and heavier than the rest of the row.
 */
class JobHeaderButtonsTest extends TestCase
{
    use RefreshDatabase;

    /** the buttons and links of the header row, the row that ends with กลับ */
    private function row(string $html): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xp = new DOMXPath($dom);

        $row = $xp->query('//a[.//span[normalize-space()="chevron_left"]]/parent::div')->item(0);
        $this->assertNotNull($row, 'the header row (the one with กลับ) exists');

        return iterator_to_array($xp->query('.//button | .//a', $row));
    }

    private function words(DOMElement $control): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('/\b[a-z_]{3,}\b/', '', $control->textContent)));
    }

    public function test_every_workflow_button_has_the_size_and_shape_of_the_back_button(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reporter = User::factory()->create(['role' => 'member']);
        $seen = [];

        foreach (['pending', 'acknowledged', 'accepted', 'in_progress', 'on_hold', 'resolved', 'closed'] as $status) {
            $req = MaintenanceRequest::factory()->create([
                'asset_id' => Asset::factory()->create()->id, 'reporter_id' => $reporter->id, 'technician_id' => null, 'status' => $status,
            ]);

            // the reporter rates a closed job; everyone else who works the job is the admin
            $html = $this->actingAs($status === 'closed' ? $reporter : $admin)
                ->get(route('maintenance.requests.show', $req))->assertOk()->getContent();

            $controls = $this->row($html);
            $back = collect($controls)->first(fn (DOMElement $c) => str_contains($c->textContent, 'chevron_left'));
            $this->assertNotNull($back, "$status: กลับ is in the row");

            foreach ($controls as $control) {
                $label = $this->words($control);
                $seen[$label] = true;
                $class = $control->getAttribute('class');

                foreach (['h-11', 'rounded-md', 'text-[13px]', 'px-[16px]', 'gap-1.5'] as $shared) {
                    $this->assertStringContainsString($shared, $class, "$status: \"$label\" has the size of กลับ ($shared)");
                }
                $this->assertStringNotContainsString('overflow-hidden', $class, "$status: \"$label\" is not a split button");
                $this->assertStringNotContainsString('bg-black/10', $control->ownerDocument->saveHTML($control), "$status: \"$label\" has no darker icon block");
            }
        }

        // the run above must have looked at every workflow button, not passed on an empty row
        foreach (['รับทราบ', 'รับเรื่อง', 'ดำเนินการ', 'หยุดชั่วคราว', 'กลับเข้าดำเนินการ', 'เสร็จสิ้น', 'อนุมัติปิดงาน', 'ไม่รับเรื่อง', 'ยกเลิกการซ่อมบำรุง', 'ประเมินความพึงพอใจ', 'กลับ'] as $label) {
            $this->assertArrayHasKey($label, $seen, "\"$label\" was checked");
        }
    }
}

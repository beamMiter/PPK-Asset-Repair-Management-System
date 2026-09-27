<?php

namespace Tests\Feature\Ui;

use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The button that sends a new repair request carries a tick (check), like the confirming buttons of the other pages, not a paper
 * plane (send). Editing a request keeps the save icon of the other edit forms.
 */
class RequestFormSubmitIconTest extends TestCase
{
    use RefreshDatabase;

    /** every icon glyph inside the submit button that says $label (the split button draws it twice: wide screens and phones) */
    private function glyphs(string $html, string $label): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $found = [];
        foreach ((new DOMXPath($dom))->query('//button[@type="submit"][contains(normalize-space(.), "'.$label.'")]//span[contains(@class,"material-symbols-outlined")]') as $span) {
            $found[] = trim($span->textContent);
        }

        return $found;
    }

    public function test_sending_a_new_request_shows_a_tick_not_a_paper_plane(): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => 'member']))
            ->get(route('maintenance.requests.create'))->assertOk()->getContent();

        $glyphs = $this->glyphs($html, 'ส่งใบแจ้งซ่อมบำรุง');
        $this->assertNotEmpty($glyphs, 'the send button has an icon');
        $this->assertSame(['check'], array_values(array_unique($glyphs)), 'a tick, on wide screens and on phones');
    }

    public function test_editing_a_request_keeps_the_save_icon(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $req = MaintenanceRequest::factory()->create([
            'asset_id' => Asset::factory()->create()->id, 'reporter_id' => User::factory()->create(['role' => 'member'])->id,
            'technician_id' => null, 'status' => 'pending',
        ]);

        $html = $this->actingAs($admin)->get(route('maintenance.requests.edit', $req))->assertOk()->getContent();

        $this->assertSame(['save'], array_values(array_unique($this->glyphs($html, 'บันทึกการแก้ไข'))));
    }
}

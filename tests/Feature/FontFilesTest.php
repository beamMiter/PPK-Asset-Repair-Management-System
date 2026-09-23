<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `public/fonts` was deleted in a33f009 (the files live in `public/images/fonts`) but the layout kept asking for
 * `/fonts/Sarabun-*.woff2`, so the local Sarabun never loaded. It went unnoticed because the top bar also pulls Sarabun
 * from Google Fonts — but on a network with no internet (a hospital intranet) every page fell back to the system font.
 * The PDF templates had the same dead path; they were unaffected only because dompdf falls back to the family that
 * `installed-fonts.json` registers.
 */
class FontFilesTest extends TestCase
{
    use RefreshDatabase;

    /** Every url() inside an @font-face block, resolved to a file under public/ or to the absolute path it names. */
    private function fontFilesDeclaredIn(string $html): array
    {
        preg_match_all('/@font-face\s*\{[^}]*\}/s', $html, $blocks);
        $files = [];

        foreach ($blocks[0] as $block) {
            preg_match_all('/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/', $block, $urls);
            foreach ($urls[2] as $url) {
                if (str_starts_with($url, 'data:')) {
                    continue;
                }
                $path = str_starts_with($url, base_path()) ? $url : public_path(ltrim((string) parse_url($url, PHP_URL_PATH), '/'));
                $files[$url] = $path;
            }
        }

        return $files;
    }

    /** Declared fonts that are missing, empty, or not what their extension says (a stray HTML error page would pass an exists() check). */
    private function missing(array $files): array
    {
        $magic = ['woff2' => 'wOF2', 'woff' => 'wOFF', 'ttf' => "\x00\x01\x00\x00"];

        return array_keys(array_filter($files, function ($path) use ($magic) {
            if (! is_file($path) || filesize($path) === 0) {
                return true;
            }

            $expected = $magic[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;

            return $expected !== null && file_get_contents($path, false, null, 0, 4) !== $expected;
        }));
    }

    public function test_every_font_the_page_layout_declares_exists(): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('repair.dashboard'))->assertOk()->getContent();

        $files = $this->fontFilesDeclaredIn($html);

        $this->assertNotEmpty($files, 'the layout should declare its Sarabun faces');
        $this->assertSame([], $this->missing($files));

        // 400 / 500 / 600 / 700, each with a woff2 source
        foreach (['Regular', 'Medium', 'SemiBold', 'Bold'] as $weight) {
            $this->assertContains("Sarabun-$weight.woff2", array_map('basename', array_keys($files)), $weight);
        }
    }

    public function test_the_pdf_templates_do_not_point_at_fonts_that_are_not_there(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $asset = Asset::factory()->create(['asset_code' => 'FT-'.uniqid(), 'serial_number' => uniqid('S'), 'his_asset_id' => null]);
        $req = MaintenanceRequest::factory()->create(['asset_id' => $asset->id]);

        $workOrder = $this->actingAs($admin)->get(route('maintenance.requests.work-order', ['req' => $req, 'html' => 1]))->assertOk()->getContent();
        $assetSheet = view('assets.print', ['asset' => $asset->load(['categoryRef', 'department'])->loadCount(['maintenanceRequests as maintenance_requests_count', 'requestAttachments as attachments_count']), 'hospital' => ['name_th' => 'x', 'name_en' => 'x', 'subtitle' => 'x', 'logo' => '']])->render();
        $sla = view('maintenance.sla.report', ['hospital' => ['name_th' => 'x', 'name_en' => 'x', 'subtitle' => 'x', 'logo' => ''], 'reportDate' => now()] + $this->slaData())->render();

        foreach (['work order' => $workOrder, 'asset sheet' => $assetSheet, 'sla report' => $sla] as $name => $html) {
            $this->assertSame([], $this->missing($this->fontFilesDeclaredIn($html)), $name);
        }
    }

    public function test_every_pdf_still_embeds_sarabun_for_both_weights(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $asset = Asset::factory()->create(['asset_code' => 'FT-'.uniqid(), 'serial_number' => uniqid('S'), 'his_asset_id' => null, 'name' => 'เครื่องคอมพิวเตอร์']);
        $req = MaintenanceRequest::factory()->create(['asset_id' => $asset->id, 'title' => 'ทดสอบภาษาไทย']);

        $pdfs = [
            'work order' => $this->actingAs($admin)->get(route('maintenance.requests.work-order', $req)),
            'asset sheet' => $this->actingAs($admin)->get(route('assets.print', $asset)),
            'sla report' => $this->actingAs($admin)->post(route('maintenance.sla.report')),
        ];

        foreach ($pdfs as $name => $res) {
            $res->assertOk();
            preg_match_all('#/BaseFont\s*/([A-Za-z0-9+_-]+)#', $res->getContent(), $m);
            $fonts = array_unique($m[1]);

            // the SLA report uses Sarabun with the tone-mark-over-vowel glyphs added (SarabunPDF, see ThaiPdfText)
            $family = $name === 'sla report' ? 'SarabunPDF' : 'Sarabun';

            $this->assertContains("$family-Regular", $fonts, "$name regular");
            $this->assertContains("$family-Bold", $fonts, "$name bold");
        }
    }

    public function test_pages_no_longer_import_sarabun_from_google_fonts(): void
    {
        // The top bar used to `@import` Sarabun (weights 300–700) from fonts.googleapis.com on every page. The local files
        // are declared by the layout now, so the page no longer needs the internet for its main font.
        $html = $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('repair.dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('family=Sarabun', $html);
        $this->assertStringContainsString("font-family: 'Sarabun'", $html, 'the local faces are still declared');
    }

    /** the variables maintenance/sla/report.blade.php reads, taken from what the dashboard builds */
    private function slaData(): array
    {
        $controller = new \ReflectionMethod(\App\Http\Controllers\Maintenance\SlaPerformanceController::class, 'getSlaDashboardData');
        $controller->setAccessible(true);

        return $controller->invoke(new \App\Http\Controllers\Maintenance\SlaPerformanceController(), request());
    }
}

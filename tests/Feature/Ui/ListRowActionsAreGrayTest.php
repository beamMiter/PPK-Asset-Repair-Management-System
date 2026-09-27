<?php

namespace Tests\Feature\Ui;

use App\Models\Asset;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The "ดูรายละเอียด" and "แก้ไข" of a list row are the standard small grey button (<x-ui.button size="sm">) - the one the Technician Rating page
 * already used. The requests list and the assets list drew them by hand, view in indigo and edit in emerald, so every list looked different.
 * Not part of this: the repair jobs list (รายการงานซ่อม), the users list and the system management pages keep their own.
 */
class ListRowActionsAreGrayTest extends TestCase
{
    use RefreshDatabase;

    private function sortedClasses(string $tag): array
    {
        $this->assertSame(1, preg_match('/class="([^"]*)"/', $tag, $m), $tag);
        $classes = preg_split('/\s+/', trim($m[1]));
        sort($classes);

        return $classes;
    }

    /** the class list of the standard small button */
    private function standard(): array
    {
        $html = Blade::render('<x-ui.button href="/x" size="sm" icon="visibility">x</x-ui.button>');
        $this->assertSame(1, preg_match('/<a [^>]*>/', $html, $m));

        return $this->sortedClasses($m[0]);
    }

    /** @return array<int, array> the class lists of every <a> that goes to $href and reads $label */
    private function anchors(string $html, string $href, string $label): array
    {
        $found = [];
        preg_match_all('/<a\s[^>]*href="' . preg_quote($href, '/') . '"[^>]*>(.*?)<\/a>/su', $html, $all, PREG_SET_ORDER);
        foreach ($all as $m) {
            if (str_contains(strip_tags($m[1]), $label)) {
                $found[] = $this->sortedClasses($m[0]);
            }
        }

        return $found;
    }

    public function test_the_requests_list_row_actions_are_the_standard_grey_button(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $request = MaintenanceRequest::factory()->create();

        $html = $this->actingAs($admin)->get(route('maintenance.requests.index'))->assertOk()->getContent();

        $view = $this->anchors($html, route('maintenance.requests.show', $request), 'ดูรายละเอียด');
        $edit = $this->anchors($html, route('maintenance.requests.edit', $request), 'แก้ไข');
        $this->assertCount(1, $view);
        $this->assertCount(1, $edit);
        $this->assertSame($this->standard(), $view[0]);
        $this->assertSame($this->standard(), $edit[0], 'edit is grey too, not emerald');
        $this->assertStringNotContainsString('border-indigo-300', $html);
        $this->assertStringNotContainsString('border-emerald-300', $html);
    }

    public function test_the_assets_list_row_actions_are_the_standard_grey_button_in_the_table_and_the_cards(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $asset = Asset::factory()->create();

        $html = $this->actingAs($admin)->get(route('assets.index'))->assertOk()->getContent();

        $view = $this->anchors($html, route('assets.show', $asset), 'ดูรายละเอียด');
        $edit = $this->anchors($html, route('assets.edit', $asset), 'แก้ไข');
        $this->assertCount(2, $view, 'the table row and the mobile card');
        $this->assertCount(2, $edit);
        foreach ([...$view, ...$edit] as $classes) {
            $this->assertSame($this->standard(), $classes);
        }
        $this->assertStringNotContainsString('border-indigo-300', $html);
        $this->assertStringNotContainsString('border-emerald-300', $html);
    }

    public function test_the_technician_rating_page_uses_the_same_button(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tech = User::factory()->create(['role' => 'technician']);
        MaintenanceAssignment::create(['maintenance_request_id' => MaintenanceRequest::factory()->create()->id, 'user_id' => $tech->id, 'role' => 'technician', 'is_lead' => true, 'assigned_at' => now()]);

        $html = $this->actingAs($admin)->get(route('maintenance.requests.rating.technicians'))->assertOk()->getContent();

        $view = $this->anchors($html, route('technicians.rating.summary', $tech->id), 'ดูรายละเอียด');
        $this->assertNotEmpty($view, 'the technician is on the board');
        $this->assertSame($this->standard(), $view[0]);
    }

    public function test_a_person_who_may_not_edit_is_not_offered_it(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $asset = Asset::factory()->create();

        $html = $this->actingAs($member)->get(route('assets.index'))->assertOk()->getContent();

        $this->assertNotEmpty($this->anchors($html, route('assets.show', $asset), 'ดูรายละเอียด'));
        $this->assertSame([], $this->anchors($html, route('assets.edit', $asset), 'แก้ไข'), 'a member only looks');
    }

    public function test_the_pages_that_keep_their_own_buttons_were_not_touched(): void
    {
        // the users list and the system management pages: the emerald edit and the indigo view are theirs (PatternButtonsTest pins the users list)
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'member']);
        MaintenanceRequestType::create(['name' => 'งานทดสอบ', 'is_active' => true, 'sort_order' => 1]);

        $users = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->getContent();
        $types = $this->actingAs($admin)->get(route('settings.maintenance-types.index'))->assertOk()->getContent();

        $this->assertStringContainsString('border-emerald-300', $users);
        $this->assertStringContainsString('border-emerald-300', $types, 'the types list keeps its own edit');
        $jobs = file_get_contents(resource_path('views/repair/my-jobs.blade.php'));
        $this->assertStringContainsString('visibility', $jobs);
        $this->assertStringNotContainsString('<x-ui.button :href="route(\'maintenance.requests.show\'', $jobs, 'the repair jobs list keeps its own card actions');
    }
}

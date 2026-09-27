<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Support\AssetInput;
use Illuminate\Support\MessageBag;
use App\Models\Department;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pins what the asset pages and the asset API do on bad input and on the "back to active" rule, so the controller
 * can share one implementation between them.
 */
class AssetControllerBehaviourTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /** The toast a page rendered (the layout embeds the session toast as JSON), or null. */
    private function renderedToast($response): ?array
    {
        preg_match('#<script id="session-toast-data" type="application/json">\s*(.*?)\s*</script>#s', $response->getContent(), $m);

        return isset($m[1]) ? json_decode($m[1], true) : null;
    }

    private function valid(array $extra = []): array
    {
        return $extra + ['asset_code' => 'NEW-1', 'name' => 'New asset', 'status' => 'active'];
    }

    public function test_page_create_with_missing_fields_goes_back_with_the_field_names_in_the_toast(): void
    {
        $res = $this->actingAs($this->admin())->from(route('assets.create'))->post(route('assets.store'), []);

        $res->assertRedirect(route('assets.create'));
        $res->assertSessionHasErrors(['asset_code', 'name']);
        $this->assertSame('ข้อมูลไม่ถูกต้อง: รหัสครุภัณฑ์, ชื่อครุภัณฑ์', session('toast.message'));
        $this->assertSame('warning', session('toast.type'));
        $this->assertSame(3000, session('toast.timeout'));
        $this->assertSame(0, Asset::count());
    }

    public function test_api_create_with_missing_fields_returns_422_with_the_same_message(): void
    {
        Sanctum::actingAs($this->admin());

        $res = $this->postJson('/api/assets', []);

        $res->assertStatus(422)->assertJsonValidationErrors(['asset_code', 'name'], 'errors');
        $this->assertSame('ข้อมูลไม่ถูกต้อง: รหัสครุภัณฑ์, ชื่อครุภัณฑ์', $res->json('toast.message'));
        $this->assertSame(2200, $res->json('toast.timeout'));
    }

    public function test_the_failed_fields_are_named_in_thai_and_a_field_nobody_named_keeps_its_key(): void
    {
        $existing = Asset::factory()->create(['serial_number' => 'SN-1']);

        $res = $this->actingAs($this->admin())->from('/x')->post(route('assets.store'), $this->valid([
            'serial_number' => 'SN-1', 'warranty_start' => '2026-01-10', 'warranty_expire' => '2026-01-01', 'price' => -5,
        ]));

        // Serial and หมดประกัน keep the names the asset form always used; price now has one in lang/th/validation.php instead of "price"
        $this->assertSame('ข้อมูลไม่ถูกต้อง: Serial, หมดประกัน, ราคา', session('toast.message'));
        $this->assertSame(
            'ข้อมูลไม่ถูกต้อง: field_nobody_named, ไฟล์แนบ',
            AssetInput::failureMessage(new MessageBag(['field_nobody_named' => ['x'], 'files.0' => ['x'], 'files.1' => ['y']])),
            'no Thai name: the raw key; files.0 and files.1 are one field, ไฟล์แนบ, said once',
        );
        $this->assertSame(1, Asset::where('id', $existing->id)->count());
    }

    public function test_update_may_keep_its_own_code_and_serial_but_not_take_another_assets(): void
    {
        $a = Asset::factory()->create(['asset_code' => 'A-1', 'serial_number' => 'S-1']);
        $b = Asset::factory()->create(['asset_code' => 'B-1', 'serial_number' => 'S-2']);
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('assets.update', $a), ['asset_code' => 'A-1', 'serial_number' => 'S-1', 'name' => 'Renamed'])
            ->assertRedirect(route('assets.show', $a));
        $this->assertSame('Renamed', $a->fresh()->name);

        $this->actingAs($admin)->from('/back')->put(route('assets.update', $a), ['asset_code' => 'B-1'])->assertSessionHasErrors('asset_code');
        $this->assertSame('A-1', $a->fresh()->asset_code);

        Sanctum::actingAs($admin);
        $this->putJson("/api/assets/{$a->id}", ['serial_number' => 'S-2'])->assertStatus(422)->assertJsonValidationErrors('serial_number', 'errors');
    }

    public function test_an_asset_with_an_open_repair_cannot_be_set_back_to_active_by_hand(): void
    {
        $asset = Asset::factory()->create(['status' => 'in_repair']);
        MaintenanceRequest::factory()->create(['asset_id' => $asset->id, 'status' => 'in_progress']);
        $admin = $this->admin();

        // page
        $res = $this->actingAs($admin)->from('/back')->put(route('assets.update', $asset), ['status' => 'active']);
        $res->assertRedirect('/back');
        $this->assertSame('warning', session('toast.type'));
        $this->assertStringContainsString('ยังมีใบแจ้งซ่อม', session('toast.message'));
        $this->assertSame(4000, session('toast.timeout'));
        $this->assertSame('in_repair', $asset->fresh()->status);

        // API
        Sanctum::actingAs($admin);
        $api = $this->putJson("/api/assets/{$asset->id}", ['status' => 'active']);
        $api->assertStatus(422)->assertJsonPath('errors.status.0', fn ($m) => str_contains($m, 'ยังมีใบแจ้งซ่อม'));
        $this->assertSame(3000, $api->json('toast.timeout'));
        $this->assertSame('in_repair', $asset->fresh()->status);
    }

    public function test_back_to_active_is_allowed_once_every_request_is_finished(): void
    {
        $asset = Asset::factory()->create(['status' => 'in_repair']);
        MaintenanceRequest::factory()->create(['asset_id' => $asset->id, 'status' => 'closed']);
        MaintenanceRequest::factory()->create(['asset_id' => $asset->id, 'status' => 'cancelled']);

        $this->actingAs($this->admin())->put(route('assets.update', $asset), ['status' => 'active'])->assertRedirect(route('assets.show', $asset));

        $this->assertSame('active', $asset->fresh()->status);
    }

    public function test_every_open_status_blocks_reactivation(): void
    {
        foreach (['pending', 'acknowledged', 'accepted', 'in_progress', 'on_hold'] as $status) {
            $asset = Asset::factory()->create(['status' => 'in_repair']);
            MaintenanceRequest::factory()->create(['asset_id' => $asset->id, 'status' => $status]);

            $this->actingAs($this->admin())->from('/b')->put(route('assets.update', $asset), ['status' => 'active']);

            $this->assertSame('in_repair', $asset->fresh()->status, $status);
        }
    }

    public function test_a_member_can_read_assets_but_not_create_or_change_them(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $asset = Asset::factory()->create();

        $this->actingAs($member)->get(route('assets.index'))->assertOk();
        $this->actingAs($member)->get(route('assets.show', $asset))->assertOk();
        $this->actingAs($member)->getJson(route('assets.create'))->assertForbidden();
        $this->actingAs($member)->postJson(route('assets.store'), $this->valid())->assertForbidden();
        $this->actingAs($member)->putJson(route('assets.update', $asset), ['name' => 'x'])->assertForbidden();
        $this->assertSame(0, Asset::where('asset_code', 'NEW-1')->count());
    }

    public function test_create_and_edit_pages_tell_you_when_there_is_no_department_or_category(): void
    {
        Department::query()->delete();
        \App\Models\AssetCategory::query()->delete();
        $admin = $this->admin();

        // both are missing: the later of the two toasts (categories) is the one shown
        $create = $this->actingAs($admin)->get(route('assets.create'))->assertOk();
        $this->assertStringContainsString('ยังไม่มีหมวดหมู่ทรัพย์สิน', $this->renderedToast($create)['message']);

        $asset = Asset::factory()->create(['department_id' => null, 'category_id' => null]);
        $edit = $this->actingAs($admin)->get(route('assets.edit', $asset))->assertOk();
        $this->assertStringContainsString('ยังไม่มีหมวดหมู่ทรัพย์สิน', $this->renderedToast($edit)['message']);

        // only departments missing
        \App\Models\AssetCategory::create(['name' => 'PCs', 'slug' => 'pcs']);
        $create = $this->actingAs($admin)->get(route('assets.create'))->assertOk();
        $this->assertStringContainsString('ยังไม่มีข้อมูลหน่วยงาน', $this->renderedToast($create)['message']);
    }

    public function test_list_search_puts_an_exact_code_match_first_and_reports_the_count(): void
    {
        $admin = $this->admin();
        $partial = Asset::factory()->create(['asset_code' => 'PC-1000', 'name' => 'zz']);
        $exact = Asset::factory()->create(['asset_code' => 'PC-10', 'name' => 'zz']);

        $res = $this->actingAs($admin)->get(route('assets.index', ['q' => 'PC-10']))->assertOk();

        $this->assertSame([$exact->id, $partial->id], $res->viewData('assets')->pluck('id')->all());
        $this->assertSame('ค้นหาพบ 2 รายการ', $this->renderedToast($res)['message']);
        $none = $this->actingAs($admin)->get(route('assets.index', ['q' => 'nothing-matches-this']))->assertOk();
        $this->assertSame(['warning', 'ไม่พบข้อมูลตามคำค้นหา'], [$this->renderedToast($none)['type'], $this->renderedToast($none)['message']]);
    }

    public function test_list_sort_by_category_and_an_unsafe_direction_is_ignored(): void
    {
        $admin = $this->admin();
        Asset::factory()->count(3)->create();

        $this->actingAs($admin)->get(route('assets.index', ['sort_by' => 'category', 'sort_dir' => 'asc']))->assertOk();
        $this->actingAs($admin)->get(route('assets.index', ['sort_by' => 'name', 'sort_dir' => 'asc; drop table assets']))->assertOk();

        $this->assertSame(3, Asset::count());
    }
}

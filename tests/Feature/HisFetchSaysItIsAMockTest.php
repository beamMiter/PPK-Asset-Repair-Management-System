<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "ดึงข้อมูล HIS" fills the asset form from `HisAssetSyncService::getMockHisData()`, which makes up a brand, a vendor, a price and a
 * warranty for ANY number typed. The manual and the README say so, but the form said nothing and the toast said "สำเร็จ": somebody
 * could register made-up data believing the hospital system gave it. The form, the toast and the answer say it is a mock now, and
 * all three follow one switch (`HisAssetSyncService::IS_MOCK`) that goes false when the hospital's API takes over.
 */
class HisFetchSaysItIsAMockTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_the_new_asset_form_says_next_to_the_button_that_the_data_is_a_mock(): void
    {
        $this->actingAs($this->admin())->get(route('assets.create'))
            ->assertOk()
            ->assertSee('id="btn-fetch-his"', false)
            ->assertSee('id="his-mock-note"', false)
            ->assertSee('ข้อมูลจำลองเพื่อทดสอบ');
    }

    public function test_the_edit_form_says_it_too(): void
    {
        $asset = Asset::factory()->create(['asset_code' => 'HM-'.uniqid(), 'serial_number' => uniqid('S'), 'his_asset_id' => null]);

        $this->actingAs($this->admin())->get(route('assets.edit', $asset))
            ->assertOk()
            ->assertSee('id="his-mock-note"', false);
    }

    public function test_the_answer_says_it_is_a_mock_and_its_toast_is_a_warning_not_a_success(): void
    {
        $res = $this->actingAs($this->admin())->getJson('/assets/fetch-his?his_id=RPJ-123456')->assertOk();

        $res->assertJsonPath('status', 'found')->assertJsonPath('mock', true);
        $this->assertSame('warning', $res->json('toast.type'));
        $this->assertStringContainsString('ข้อมูลจำลอง', $res->json('toast.message'));
        $this->assertStringNotContainsString('สำเร็จ', $res->json('toast.message'));
    }

    public function test_the_form_script_shows_a_mock_toast_and_keeps_the_plain_success_for_real_data(): void
    {
        $page = $this->actingAs($this->admin())->get(route('assets.create'))->assertOk()->getContent();

        $this->assertStringContainsString('json.mock', $page);
        $this->assertStringContainsString('ดึงข้อมูลจำลองแล้ว', $page);
        $this->assertStringContainsString('ดึงข้อมูล HIS สำเร็จ', $page, 'the success wording stays for when the hospital API is connected');
    }
}

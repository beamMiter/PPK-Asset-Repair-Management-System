<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Deleting an asset was a dead end: it went with a repair still open on it, nothing could bring it back, and its code,
 * serial and HIS number stayed taken (the database keeps them unique, deleted rows included) behind a bare "already used".
 */
class AssetDeleteLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function asset(array $extra = []): Asset
    {
        return Asset::factory()->create($extra + ['his_asset_id' => null]);
    }

    private function repairOn(Asset $asset, string $status): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create(['asset_id' => $asset->id, 'status' => $status]);
    }

    public function test_an_asset_with_a_repair_still_open_is_not_deleted(): void
    {
        $asset = $this->asset();
        $this->repairOn($asset, 'in_progress');

        Sanctum::actingAs($this->admin);
        $this->deleteJson('/api/assets/'.$asset->id)
            ->assertStatus(409)
            ->assertJsonPath('message', Asset::DELETE_BLOCKED);

        $this->actingAs($this->admin)->delete(route('assets.destroy', $asset))
            ->assertRedirect()
            ->assertSessionHas('toast', fn ($t) => $t['type'] === 'warning' && $t['message'] === Asset::DELETE_BLOCKED);

        $this->assertNotSoftDeleted($asset);
    }

    public function test_an_asset_whose_repairs_are_all_over_can_be_deleted(): void
    {
        $asset = $this->asset();
        $this->repairOn($asset, 'closed');
        $this->repairOn($asset, 'cancelled');

        Sanctum::actingAs($this->admin);
        $this->deleteJson('/api/assets/'.$asset->id)->assertOk();

        $this->assertSoftDeleted($asset);
    }

    public static function uniqueFields(): array
    {
        return [
            'asset code' => ['asset_code', 'GONE-1'],
            'serial' => ['serial_number', 'SN-GONE'],
            'HIS number' => ['his_asset_id', 'HIS-GONE'],
        ];
    }

    #[DataProvider('uniqueFields')]
    public function test_a_value_held_by_a_deleted_asset_is_named_as_such(string $field, string $value): void
    {
        $this->asset([$field => $value])->delete();
        Sanctum::actingAs($this->admin);

        $res = $this->postJson('/api/assets', ['asset_code' => 'NEW-1', 'name' => 'ใหม่', $field => $value])->assertStatus(422);

        $this->assertStringContainsString('ถูกลบไปแล้ว', $res->json("errors.{$field}.0"));
        $this->assertCount(1, $res->json("errors.{$field}"), 'one reason, not "deleted" and "already used" together');
    }

    public function test_a_value_held_by_a_live_asset_is_still_just_taken(): void
    {
        $this->asset(['asset_code' => 'LIVE-1']);
        Sanctum::actingAs($this->admin);

        $res = $this->postJson('/api/assets', ['asset_code' => 'LIVE-1', 'name' => 'ใหม่'])->assertStatus(422);

        $this->assertStringNotContainsString('ถูกลบไปแล้ว', $res->json('errors.asset_code.0'));
    }

    public function test_a_deleted_asset_comes_back_with_the_restore_command(): void
    {
        $asset = $this->asset(['asset_code' => 'GONE-1']);
        $asset->delete();

        $this->artisan('assets:restore', ['code' => 'GONE-1'])->expectsOutputToContain('GONE-1')->assertSuccessful();

        $this->assertNotSoftDeleted($asset);
    }

    public function test_the_restore_command_says_so_when_there_is_nothing_to_restore(): void
    {
        $this->asset(['asset_code' => 'LIVE-1']);

        $this->artisan('assets:restore', ['code' => 'LIVE-1'])->assertFailed();
        $this->artisan('assets:restore', ['code' => 'NOPE'])->assertFailed();
    }

}

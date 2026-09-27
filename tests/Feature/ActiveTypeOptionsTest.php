<?php

namespace Tests\Feature;

use App\Models\MaintenanceRequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The request-type dropdown was cached for an hour with nothing ever clearing it, so a type an admin had just
 * added, renamed or disabled kept showing its old state on the request forms and list filter.
 */
class ActiveTypeOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_renamed_or_disabled_type_shows_up_immediately(): void
    {
        $names = fn () => MaintenanceRequestType::activeForSelect()->pluck('name')->all();

        $this->assertSame([], $names()); // warms the cache

        $type = MaintenanceRequestType::create(['name' => 'Network', 'is_active' => true, 'sort_order' => 1]);
        $this->assertSame(['Network'], $names());

        $type->update(['name' => 'Networking']);
        $this->assertSame(['Networking'], $names());

        MaintenanceRequestType::create(['name' => 'Printers', 'is_active' => true, 'sort_order' => 2]);
        $this->assertSame(['Networking', 'Printers'], $names());

        $type->update(['is_active' => false]);
        $this->assertSame(['Printers'], $names());

        MaintenanceRequestType::where('name', 'Printers')->first()->delete();
        $this->assertSame([], $names());
    }

    public function test_the_create_page_offers_a_type_added_after_the_page_was_first_opened(): void
    {
        $user = User::factory()->create(['role' => 'member']);
        $this->actingAs($user)->get(route('maintenance.requests.create'))->assertOk();

        MaintenanceRequestType::create(['name' => 'Brand New Type', 'is_active' => true]);

        $this->actingAs($user)->get(route('maintenance.requests.create'))->assertOk()->assertSee('Brand New Type');
    }
}

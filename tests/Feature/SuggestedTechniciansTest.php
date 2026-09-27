<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The assign-team picker on a request: the type's default person first, then people matching the type's department /
 * role, then the rest of the active team. Suspended accounts are never offered.
 */
class SuggestedTechniciansTest extends TestCase
{
    use RefreshDatabase;

    private function names($response): array
    {
        return $response->viewData('techUsers')->pluck('name')->all();
    }

    private function requestOfType(?MaintenanceRequestType $type): MaintenanceRequest
    {
        return MaintenanceRequest::factory()->create([
            'asset_id' => Asset::factory()->create()->id,
            'type_id' => $type?->id,
        ]);
    }

    public function test_order_is_default_person_then_matching_then_everyone_else_without_duplicates(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Zed Admin']);
        $default = User::factory()->create(['role' => 'supervisor', 'name' => 'Default Person']);
        $match1 = User::factory()->create(['role' => 'network', 'department' => 'IT', 'name' => 'Bea Match']);
        $match2 = User::factory()->create(['role' => 'network', 'department' => 'IT', 'name' => 'Ann Match']);
        User::factory()->create(['role' => 'network', 'department' => 'HR', 'name' => 'Cy Other Dept']);
        User::factory()->create(['role' => 'programmer', 'department' => 'IT', 'name' => 'Di Other Role']);
        User::factory()->create(['role' => 'member', 'name' => 'Not Team']);

        $type = MaintenanceRequestType::create([
            'name' => 'Network', 'is_active' => true,
            'default_user_id' => $default->id, 'default_department_code' => 'IT', 'default_role_code' => 'NETWORK',
        ]);

        $names = $this->names($this->actingAs($admin)->get(route('maintenance.requests.show', $this->requestOfType($type)))->assertOk());

        $this->assertSame(
            ['Default Person', 'Ann Match', 'Bea Match', 'Cy Other Dept', 'Di Other Role', 'Zed Admin'],
            $names
        );
    }

    public function test_without_a_type_or_a_filter_result_the_whole_active_team_is_offered(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'B Admin']);
        User::factory()->create(['role' => 'it_support', 'name' => 'A Tech']);
        User::factory()->create(['role' => 'member', 'name' => 'Member']);

        $noType = $this->names($this->actingAs($admin)->get(route('maintenance.requests.show', $this->requestOfType(null))));
        $this->assertSame(['A Tech', 'B Admin'], $noType);

        $type = MaintenanceRequestType::create(['name' => 'Nobody matches', 'is_active' => true, 'default_role_code' => 'network']);
        $fallback = $this->names($this->actingAs($admin)->get(route('maintenance.requests.show', $this->requestOfType($type))));
        $this->assertSame(['A Tech', 'B Admin'], $fallback);
    }

    public function test_a_suspended_account_is_never_offered_not_even_as_the_types_default_person(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin']);
        $suspendedDefault = User::factory()->create(['role' => 'it_support', 'name' => 'Suspended Default', 'suspended_at' => now()]);
        User::factory()->create(['role' => 'it_support', 'name' => 'Suspended Other', 'suspended_at' => now()]);
        User::factory()->create(['role' => 'it_support', 'name' => 'Active Tech']);

        $type = MaintenanceRequestType::create(['name' => 'T', 'is_active' => true, 'default_user_id' => $suspendedDefault->id]);

        $names = $this->names($this->actingAs($admin)->get(route('maintenance.requests.show', $this->requestOfType($type)))->assertOk());

        $this->assertSame(['Active Tech', 'Admin'], $names);
    }
}

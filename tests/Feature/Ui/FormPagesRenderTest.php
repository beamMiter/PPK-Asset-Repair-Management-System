<?php

namespace Tests\Feature\Ui;

use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every create / edit / detail page was moved onto <x-ui.button>, <x-ui.form-actions> and the shared
 * .ui-input classes. This renders each one as an admin so a typo in a component call (unknown prop,
 * undefined variable, un-rendered <x-ui…> tag) fails here instead of on a page nobody opened.
 */
class FormPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    private const BUTTON_MARK = 'whitespace-nowrap select-none transition-all active:scale-95'; // only <x-ui.button> emits this

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    /** @return array<string, array{0: string}> route names (route() cannot run before the app boots) */
    public static function pages(): array
    {
        return [
            'asset create' => ['assets.create'],
            'request create' => ['maintenance.requests.create'],
            'user create' => ['admin.users.create'],
            'maintenance type create' => ['settings.maintenance-types.create'],
            'profile' => ['profile.show'],
            'profile edit' => ['profile.edit'],
        ];
    }

    #[DataProvider('pages')]
    public function test_static_pages_render_with_the_shared_components(string $routeName): void
    {
        $html = $this->actingAs($this->admin())->get(route($routeName))->assertOk()->getContent();

        $this->assertStringNotContainsString('<x-ui', $html, 'a component tag was left un-rendered');
        $this->assertStringContainsString(self::BUTTON_MARK, $html, 'expected at least one <x-ui.button>');
    }

    public function test_pages_that_need_a_record_render(): void
    {
        $admin = $this->admin();
        $asset = Asset::factory()->create();
        $req = MaintenanceRequest::factory()->create(['status' => MaintenanceRequest::STATUS_PENDING]);
        $other = User::factory()->create(['role' => 'member']);

        foreach ([
            route('assets.show', $asset),
            route('assets.edit', $asset),
            route('maintenance.requests.show', $req),
            route('maintenance.requests.edit', $req),
            route('admin.users.edit', $other),
        ] as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('<x-ui', $html, $url);
            $this->assertStringContainsString(self::BUTTON_MARK, $html, $url);
        }
    }

    public function test_form_fields_share_one_input_height_and_buttons_match_it(): void
    {
        $html = $this->actingAs($this->admin())->get(route('assets.create'))->assertOk()->getContent();

        $this->assertStringContainsString('ui-input', $html);
        // the retired per-form copies of the input recipe must not come back
        $this->assertStringNotContainsString('mt-2 w-full h-11 rounded-md border', $html);
        // page-level buttons are the same 44px as the fields
        $this->assertDoesNotMatchRegularExpression(
            '/<(?:a|button)\b[^>]*\bclass="[^"]*(?<![\w-])h-9(?![\w-])/',
            $html,
            'a hand-made 36px button crept back in — use <x-ui.button>'
        );
    }
}

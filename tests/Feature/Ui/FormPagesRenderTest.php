<?php

namespace Tests\Feature\Ui;

use App\Models\Asset;
use App\Models\MaintenanceLog;
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

    public function test_request_history_button_is_labelled_with_a_count_and_the_action_row_ends_with_back(): void
    {
        $admin = $this->admin();
        $req = MaintenanceRequest::factory()->create(['status' => MaintenanceRequest::STATUS_PENDING]);
        foreach ([MaintenanceRequest::STATUS_ACKNOWLEDGED, MaintenanceRequest::STATUS_ACCEPTED] as $to) {
            MaintenanceLog::create([
                'request_id' => $req->id,
                'user_id' => $admin->id,
                'action' => MaintenanceLog::ACTION_TRANSITION,
                'note' => 'x',
                'from_status' => MaintenanceRequest::STATUS_PENDING,
                'to_status' => $to,
            ]);
        }

        $html = $this->actingAs($admin)->get(route('maintenance.requests.show', $req))->assertOk()->getContent();

        // it used to be an unlabelled clock icon in the action row — now a labelled button with the number of entries
        $this->assertMatchesRegularExpression(
            '/id="openHistoryModalBtn".*?ประวัติการดำเนินงาน\s*<span[^>]*>\s*' . $req->logs()->count() . '\s*<\/span>/s',
            $html
        );
        $this->assertDoesNotMatchRegularExpression('/openHistoryModalBtn"[^>]*aria-label/', $html);

        // action row order: …พิมพ์ PDF, then กลับ last (as on the asset pages) — and the history button sits after it, with the progress bar
        $print = strpos($html, 'พิมพ์ PDF');
        $back = strpos($html, 'กลับ', $print);
        $history = strpos($html, 'id="openHistoryModalBtn"');
        $this->assertNotFalse($print);
        $this->assertTrue($print < $back && $back < $history, 'expected: พิมพ์ PDF → กลับ → (progress bar) ประวัติการดำเนินงาน');
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

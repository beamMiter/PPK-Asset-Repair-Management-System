<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Browser-tab titles follow one pattern: "<English page name> • PPK Asset Repair". The suffix lives in
 * config('app.title_suffix'); each view only sets @section('title', 'Assets').
 */
class PageTitleTest extends TestCase
{
    use RefreshDatabase;

    private const SUFFIX = ' • PPK Asset Repair';

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function titleOf(string $html): string
    {
        $this->assertSame(1, preg_match_all('/<title>(.*?)<\/title>/s', $html, $m), 'exactly one <title>');

        return html_entity_decode(trim($m[1][0]), ENT_QUOTES);
    }

    private function assertPattern(string $expectedName, string $html, string $where): void
    {
        $title = $this->titleOf($html);
        $this->assertSame($expectedName.self::SUFFIX, $title, $where);
        $this->assertDoesNotMatchRegularExpression('/\p{Thai}/u', $title, "$where: Thai text in the tab title");
    }

    /** @return array<string, array{0: string, 1: string}> route name => page name */
    public static function staticPages(): array
    {
        return [
            'dashboard' => ['repair.dashboard', 'Dashboard'],
            'my jobs' => ['repairs.my_jobs', 'My Jobs'],
            'requests' => ['maintenance.requests.index', 'Maintenance Requests'],
            'new request' => ['maintenance.requests.create', 'New Request'],
            'satisfaction' => ['maintenance.requests.rating.evaluate', 'Satisfaction Ratings'],
            'technician ratings' => ['maintenance.requests.rating.technicians', 'Technician Ratings'],
            'sla' => ['maintenance.sla.index', 'SLA Dashboard'],
            'assets' => ['assets.index', 'Assets'],
            'new asset' => ['assets.create', 'New Asset'],
            'users' => ['admin.users.index', 'Users'],
            'new user' => ['admin.users.create', 'New User'],
            'request types' => ['settings.maintenance-types.index', 'Request Types'],
            'new request type' => ['settings.maintenance-types.create', 'New Request Type'],
            'notifications' => ['settings.notifications.index', 'Notifications'],
            'manual' => ['help.manual', 'User Manual'],
            'profile' => ['profile.show', 'Profile'],
            'edit profile' => ['profile.edit', 'Edit Profile'],
            'livechat' => ['chat.index', 'Livechat'],
        ];
    }

    #[DataProvider('staticPages')]
    public function test_each_page_uses_the_pattern(string $route, string $name): void
    {
        $html = $this->actingAs($this->admin())->get(route($route))->assertOk()->getContent();

        $this->assertPattern($name, $html, $route);
    }

    public function test_pages_with_a_record_put_its_identifier_after_an_english_name(): void
    {
        $admin = $this->admin();
        $req = MaintenanceRequest::factory()->create(['status' => MaintenanceRequest::STATUS_PENDING]);
        $asset = Asset::factory()->create();
        $other = User::factory()->create(['role' => 'member']);
        $tech = User::factory()->create(['role' => 'it_support', 'name' => 'Somchai Jaidee']);
        $type = MaintenanceRequestType::create(['name' => 'Software', 'is_active' => true, 'sort_order' => 1]);
        $no = $req->request_no ?? $req->id;
        $code = $asset->asset_code ?: '#'.$asset->id;

        $cases = [
            route('maintenance.requests.show', $req) => "Request #$no",
            route('maintenance.requests.edit', $req) => "Edit Request #$no",
            route('assets.show', $asset) => "Asset $code",
            route('assets.edit', $asset) => "Edit Asset $code",
            route('admin.users.edit', $other) => 'Edit User #'.$other->id,
            route('settings.maintenance-types.edit', $type->id) => 'Edit Request Type: Software',
            route('technicians.rating.summary', $tech) => 'Technician Rating: Somchai Jaidee',
        ];

        foreach ($cases as $url => $name) {
            $this->assertPattern($name, $this->actingAs($admin)->get($url)->assertOk()->getContent(), $url);
        }
    }

    public function test_sign_in_pages_use_the_same_suffix(): void
    {
        $this->assertPattern('Sign in', $this->get(route('login'))->assertOk()->getContent(), 'login');
        $this->assertPattern('Create account', $this->get(route('register'))->assertOk()->getContent(), 'register');
        $this->assertPattern('Forgot password', $this->get(route('password.request'))->assertOk()->getContent(), 'forgot');
        $this->assertPattern('Reset password', $this->get(route('password.reset', ['token' => 'abc']))->assertOk()->getContent(), 'reset');

        $unverified = User::factory()->unverified()->create();
        $this->assertPattern('Verify email', $this->actingAs($unverified)->get(route('verification.notice'))->assertOk()->getContent(), 'verify');
    }

    public function test_a_view_without_a_title_still_gets_the_product_name_only(): void
    {
        $html = view('layouts.app')->render();

        $this->assertSame('PPK Asset Repair', $this->titleOf($html));
    }

    /** assets.print is streamed as a PDF, so the sheet's <title> is the PDF's document title (shown by the PDF viewer tab) */
    public function test_the_printable_asset_sheet_follows_the_pattern(): void
    {
        $asset = Asset::factory()->create();
        $data = ['asset' => $asset, 'hospital' => ['name_th' => 'โรงพยาบาลพระปกเกล้า', 'name_en' => 'PHRAPOKKLAO HOSPITAL', 'subtitle' => 'Asset Repair Management', 'logo' => asset('images/logoppk1.png')]];

        $html = view('assets.print', $data)->render();

        $this->assertPattern('Asset Sheet '.$asset->asset_code, $html, 'assets.print');
    }

    /** New pages cannot drift back: every literal page name in a view is English, and no layout hard-codes a brand. */
    public function test_no_view_sets_a_thai_page_name_and_layouts_use_the_shared_suffix(): void
    {
        foreach (glob(resource_path('views/**/*.blade.php'), GLOB_BRACE) + $this->recursiveViews() as $file) {
            $src = file_get_contents($file);
            if (preg_match_all("/@section\\('title',\\s*(.+?)\\)\\s*$/m", $src, $m)) {
                foreach ($m[1] as $expr) {
                    $this->assertDoesNotMatchRegularExpression('/\p{Thai}/u', $expr, "$file: Thai page name $expr");
                }
            }
        }

        foreach (['app', 'auth', 'guest'] as $layout) {
            $src = file_get_contents(resource_path("views/layouts/$layout.blade.php"));
            $this->assertStringContainsString("config('app.title_suffix')", $src, $layout);
            $this->assertStringNotContainsString('PPK Hospital System •', $src, "$layout still hard-codes a brand in <title>");
        }
    }

    /** @return array<int, string> */
    private function recursiveViews(): array
    {
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (str_ends_with($f->getFilename(), '.blade.php')) {
                $files[] = $f->getPathname();
            }
        }

        return $files;
    }
}

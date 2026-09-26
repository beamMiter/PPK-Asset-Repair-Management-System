<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\ChatThread;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Support\Like;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Two ways a search box could misbehave, both closed at one point each:
 *  - `?q[]=x` (an array where a word is expected) answered 500 on the chat, the assets, the requests, my jobs and two API lists;
 *    IgnoreArrayQuery drops array-valued query parameters on GET/HEAD;
 *  - `%` and `_` typed into a search were LIKE wildcards ("_" matched every row); App\Support\Like turns them into text.
 */
class ArrayQueryAndLikeTest extends TestCase
{
    use RefreshDatabase;

    // ---- arrays in the query string -----------------------------------------------------------------------------

    /** @return array<string,array{0:string}> */
    public static function pages(): array
    {
        return [
            'chat q' => ['/chat?q[]=x'],
            'chat scope' => ['/chat?scope[]=x'],
            'assets q' => ['/assets?q[]=x'],
            'assets nested' => ['/assets?q[a][b]=x'],
            'requests q' => ['/maintenance/requests?q[]=x'],
            'requests status' => ['/maintenance/requests?status[]=x'],
            'my jobs' => ['/repair/my-jobs?q[]=x'],
            'users' => ['/admin/users?q[]=x&s[]=y'],
            'evaluate' => ['/maintenance/requests/rating/evaluate?q[]=x&tab[]=y'],
            'types' => ['/settings/maintenance-types?q[]=x'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_an_array_where_a_word_is_expected_is_the_default_list_not_a_500(string $url): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->get($url)->assertOk();
    }

    public function test_the_api_lists_too(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        foreach (['/api/threads?q[]=x', '/api/assets?q[]=x', '/api/repair-requests?q[]=x&status[]=y', '/api/threads?scope[]=mine'] as $url) {
            $this->getJson($url)->assertOk();
        }
    }

    public function test_the_word_beside_an_array_still_works(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        ChatThread::create(['title' => 'เครื่องพิมพ์', 'author_id' => $admin->id, 'is_locked' => false]);
        ChatThread::create(['title' => 'เน็ตช้า', 'author_id' => $admin->id, 'is_locked' => false]);

        $titles = $this->actingAs($admin)->get('/chat?q=' . urlencode('เน็ต') . '&junk[]=1')->assertOk()->viewData('threads')->pluck('title')->all();

        $this->assertSame(['เน็ตช้า'], $titles);
    }

    public function test_posts_are_left_alone_their_arrays_are_real(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // a POST with an array (a file list, checkboxes) is not touched: this one fails validation, it does not lose its array
        $this->actingAs($admin)->post('/maintenance/requests', ['files' => ['not-a-file']])->assertSessionHasErrors();
    }

    // ---- LIKE ---------------------------------------------------------------------------------------------------

    public function test_the_helper_turns_wildcards_into_text(): void
    {
        $this->assertSame('%50\%\_a\\\\b%', Like::contains('50%_a\\b'));
        $this->assertSame('ab\_%', Like::startsWith('ab_'));
        $this->assertSame('%%', Like::contains(''));
    }

    public function test_a_percent_or_underscore_in_a_search_is_matched_as_text(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        ChatThread::create(['title' => 'ลดราคา 50% ทุกรายการ', 'author_id' => $admin->id, 'is_locked' => false]);
        ChatThread::create(['title' => 'เครื่องพิมพ์ชั้น 2', 'author_id' => $admin->id, 'is_locked' => false]);
        ChatThread::create(['title' => 'a_b', 'author_id' => $admin->id, 'is_locked' => false]);

        $find = fn (string $q) => $this->actingAs($admin)->get('/chat?q=' . urlencode($q))->viewData('threads')->pluck('title')->all();

        $this->assertSame(['ลดราคา 50% ทุกรายการ'], $find('50%'));
        $this->assertSame(['a_b'], $find('a_b'));
        $this->assertSame(['a_b'], $find('_'), 'a lone underscore is one character of text, not "any character"');
        $this->assertSame([], $find('%%%'), 'not "everything"');
    }

    public function test_the_assets_and_requests_and_users_searches_take_them_as_text_too(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Asset::factory()->create(['asset_code' => 'PC-50%', 'name' => 'เครื่องหนึ่ง', 'serial_number' => 'S1', 'his_asset_id' => null]);
        Asset::factory()->create(['asset_code' => 'PC-500', 'name' => 'เครื่องสอง', 'serial_number' => 'S2', 'his_asset_id' => null]);

        $codes = fn (string $q) => $this->actingAs($admin)->get('/assets?q=' . urlencode($q))->assertOk()->viewData('assets')->pluck('asset_code')->all();
        $this->assertSame(['PC-50%'], $codes('PC-50%'));
        $this->assertSame([], $codes('%%'));

        User::factory()->create(['role' => 'member', 'name' => 'สมชาย_ใจดี']);
        User::factory()->create(['role' => 'member', 'name' => 'สมหญิง ใจงาม']);
        $names = fn (string $s) => $this->actingAs($admin)->get('/admin/users?s=' . urlencode($s))->assertOk()->viewData('list')->pluck('name')->all();
        $this->assertSame(['สมชาย_ใจดี'], $names('ชาย_ใจ'));
        $this->assertSame(['สมชาย_ใจดี'], $names('_'), 'only the name that really has an underscore, not every name');
    }
}

<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * "Nothing found" looks and is used the same on every list page: one component, a 40px icon and 13px words. Only the icon and the
 * sentence change from page to page. The evaluate page had its own (48px icon, 14px bold heading), the technician list a bare
 * sentence, the job list a 48px icon and 14px words, the chat a 12px sentence with a paler icon, and two of the list pages carried
 * a copy of the document icon with a broken path.
 */
class EmptyStateTest extends TestCase
{
    use RefreshDatabase;

    private const ICON = 'h-10 w-10';
    private const TEXT = 'text-[13px]';

    private function render(string $template): string
    {
        return Blade::render($template);
    }

    public function test_the_default_is_the_document_icon_and_one_line_of_words(): void
    {
        $html = $this->render('<x-ui.empty-state>ไม่พบผู้ใช้</x-ui.empty-state>');

        $this->assertStringContainsString('<svg class="' . self::ICON . ' text-slate-300"', $html);
        $this->assertStringContainsString('<p class="' . self::TEXT . '">ไม่พบผู้ใช้</p>', $html);
        $this->assertStringContainsString('text-slate-600', $html);
        $this->assertStringNotContainsString('material-symbols-outlined', $html);
    }

    public function test_another_icon_is_the_same_size(): void
    {
        $html = $this->render('<x-ui.empty-state icon="search_off">ไม่พบรายการ</x-ui.empty-state>');

        $this->assertStringContainsString('material-symbols-outlined', $html);
        $this->assertStringContainsString('>search_off</span>', $html);
        $this->assertStringContainsString(self::ICON, $html);
        $this->assertStringContainsString('text-[40px]', $html);   // 40px, the size of h-10 w-10 above
        $this->assertStringContainsString('text-slate-300', $html);
        $this->assertStringContainsString('<p class="' . self::TEXT . '">ไม่พบรายการ</p>', $html);
        $this->assertStringNotContainsString('<svg', $html);
    }

    public function test_a_hint_and_an_action_are_optional(): void
    {
        $plain = $this->render('<x-ui.empty-state>ไม่พบรายการ</x-ui.empty-state>');
        $this->assertStringNotContainsString('text-[12px]', $plain);

        $full = $this->render('<x-ui.empty-state icon="task_alt" hint="ทำครบแล้ว">ไม่มีงานค้าง<x-slot:action><a href="/x">ล้างค่า</a></x-slot:action></x-ui.empty-state>');
        $this->assertStringContainsString('<p class="-mt-1 text-[12px] text-slate-500">ทำครบแล้ว</p>', $full);
        $this->assertStringContainsString('<a href="/x">ล้างค่า</a>', $full);
        $this->assertStringContainsString('<p class="' . self::TEXT . '">ไม่มีงานค้าง', $full);
    }

    public function test_the_words_are_escaped(): void
    {
        $html = Blade::render('<x-ui.empty-state hint="<i>h</i>">{{ $t }}</x-ui.empty-state>', ['t' => '<script>alert(1)</script>']);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<i>', $html);
    }

    /** @return array<string,array{0:string,1:int}> a view => how many times it says nothing was found */
    public static function pages(): array
    {
        return [
            'requests list' => ['maintenance/requests/index.blade.php', 2],
            'assets list (table + cards)' => ['assets/index.blade.php', 4],
            'users list (table + cards)' => ['admin/users/index.blade.php', 2],
            'maintenance types' => ['settings/maintenance-types/index.blade.php', 1],
            'evaluate' => ['maintenance/rating/evaluate.blade.php', 3],
            'technician scores' => ['maintenance/rating/technicians-dashboard.blade.php', 1],
            'my jobs' => ['repair/my-jobs.blade.php', 1],
            'chat threads' => ['chat/index.blade.php', 1],
            'assign dialog' => ['maintenance/requests/partials/_modal_assign.blade.php', 1],
            'edit page assign dialog' => ['maintenance/requests/edit.blade.php', 1],
        ];
    }

    #[DataProvider('pages')]
    public function test_the_list_page_uses_the_component(string $view, int $uses): void
    {
        $source = file_get_contents(resource_path('views/' . $view));

        $this->assertSame($uses, substr_count($source, '<x-ui.empty-state'), $view);
    }

    /** A copy of the 40px icon made by hand is how the sizes drifted: the component is the only place it lives. */
    public function test_no_page_draws_its_own_empty_state_icon(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            $name = $file->getRelativePathname();
            if ($name === 'components/ui/empty-state.blade.php') {
                continue;
            }
            if (preg_match('/<svg class="(w-10 h-10|h-10 w-10) text-slate-(200|300)"/', $file->getContents())) {
                $offenders[] = $name;
            }
        }

        $this->assertSame([], $offenders, 'a 40px pale icon over a sentence is <x-ui.empty-state>');
    }

    /** Two pages had copied the document icon with a slip in its outline (`V5a2 2 0 01-2-2h5.586`, a corner that never closes). */
    public function test_the_document_icon_has_its_outline_right_everywhere(): void
    {
        foreach (File::allFiles(resource_path('views')) as $file) {
            $this->assertStringNotContainsString('V5a2 2 0 01-2-2h5.586', $file->getContents(), $file->getRelativePathname());
        }
    }

    // ---- the evaluate page, rendered ---------------------------------------------------------------------------

    public function test_the_evaluate_page_says_nothing_found_at_the_shared_size(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'member']));

        foreach (['?q=ไม่มีชื่อแบบนี้' => 'ไม่พบรายการที่ตรงกับคำค้นหาหรือตัวกรอง', '' => 'ไม่มีงานค้างประเมิน', '?tab=rated' => 'ยังไม่มีประวัติการให้คะแนน'] as $query => $words) {
            $html = $this->get('/maintenance/requests/rating/evaluate' . $query)->assertOk()->getContent();

            $this->assertStringContainsString('<p class="' . self::TEXT . '">' . $words, $html, $words);
            $this->assertStringContainsString('text-[40px]', $html, $words);
            $this->assertStringNotContainsString('text-[48px]', $html, 'the old, larger icon');
        }
    }
}

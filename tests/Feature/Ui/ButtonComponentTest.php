<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * <x-ui.button> and friends are the one place button size / colour / radius live. These tests pin the
 * contract the pages rely on, so a change to a size or a variant is a deliberate edit in one file
 * (and one test), not a silent drift on some page.
 */
class ButtonComponentTest extends TestCase
{
    private function render(string $template): string
    {
        return Blade::render($template);
    }

    public function test_default_is_a_plain_44px_secondary_button(): void
    {
        $html = $this->render('<x-ui.button>ยกเลิก</x-ui.button>');

        $this->assertStringStartsWith('<button type="button"', trim($html));
        $this->assertStringContainsString('h-11', $html);
        $this->assertStringContainsString('rounded-md', $html);
        $this->assertStringContainsString('border-slate-200 bg-white', $html);
        $this->assertStringContainsString('ยกเลิก', $html);
    }

    public function test_href_renders_a_link_with_the_same_look(): void
    {
        $button = $this->render('<x-ui.button variant="primary">x</x-ui.button>');
        $link = $this->render('<x-ui.button href="/assets" variant="primary">x</x-ui.button>');

        $this->assertStringStartsWith('<a href="/assets"', trim($link));
        $this->assertStringContainsString('</a>', $link);
        $this->assertStringNotContainsString('<button', $link);
        // same classes → a link and a button of the same variant can never differ in size
        preg_match('/class="([^"]*)"/', $button, $b);
        preg_match('/class="([^"]*)"/', $link, $l);
        $this->assertSame($b[1], $l[1]);
    }

    public function test_every_page_button_size_shares_the_field_height(): void
    {
        foreach (['md' => 'h-11', 'sm' => 'h-8', 'square' => 'h-11 w-11', 'icon' => 'h-8 w-8'] as $size => $expected) {
            $this->assertStringContainsString($expected, $this->render("<x-ui.button size=\"$size\">x</x-ui.button>"), $size);
        }
        // an unknown size falls back to the standard one instead of rendering an unstyled button
        $this->assertStringContainsString('h-11', $this->render('<x-ui.button size="huge">x</x-ui.button>'));
    }

    public function test_variants_map_to_distinct_colours_and_unknown_falls_back_to_secondary(): void
    {
        $expected = [
            'primary' => 'bg-emerald-600',
            'danger' => 'bg-rose-600',
            'danger-outline' => 'border-rose-200',
            'info' => 'bg-blue-600',
            'warning' => 'bg-amber-600',
            'neutral' => 'bg-slate-600',
            'brand' => 'bg-[#0F2D5C]',
            'ghost' => 'text-slate-400',
        ];
        foreach ($expected as $variant => $class) {
            $this->assertStringContainsString($class, $this->render("<x-ui.button variant=\"$variant\">x</x-ui.button>"), $variant);
        }
        $this->assertStringContainsString('border-slate-200 bg-white', $this->render('<x-ui.button variant="nope">x</x-ui.button>'));
    }

    public function test_split_puts_the_icon_in_its_own_block_only_when_an_icon_is_given(): void
    {
        $split = $this->render('<x-ui.button variant="primary" icon="send" split>ส่ง</x-ui.button>');
        $this->assertStringContainsString('bg-black/10', $split);
        $this->assertStringContainsString('overflow-hidden', $split);

        $noIcon = $this->render('<x-ui.button variant="primary" split>ส่ง</x-ui.button>');
        $this->assertStringNotContainsString('bg-black/10', $noIcon);
        $this->assertStringContainsString('px-[16px]', $noIcon);
    }

    public function test_type_and_arbitrary_attributes_pass_straight_through(): void
    {
        $html = $this->render(<<<'BLADE'
<x-ui.button type="submit" form="main-form" id="go" @click="open = false" x-data aria-label="ปิด" onclick="return confirm('x')" disabled>ส่ง</x-ui.button>
BLADE);

        foreach (['type="submit"', 'form="main-form"', 'id="go"', '@click="open = false"', 'aria-label="ปิด"', "onclick=\"return confirm('x')\"", 'disabled'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }
    }

    public function test_layout_classes_are_merged_not_replaced(): void
    {
        $html = $this->render('<x-ui.button class="shrink-0 mt-2 hidden">x</x-ui.button>');

        $this->assertStringContainsString('shrink-0 mt-2 hidden', $html);
        $this->assertStringContainsString('h-11', $html);
    }

    /**
     * The pages also load Bootstrap from a CDN. Its !important utilities .p{t,b,x,y}-{0..5}, .m*-{0..5} and
     * .gap-{0..5} beat Tailwind's same-named classes with different values (px-4 = 24px, px-5 = 48px…), which made
     * every button far wider than designed. The button must not use any of those names.
     */
    public function test_no_class_that_bootstrap_would_override(): void
    {
        $templates = [
            '<x-ui.button>x</x-ui.button>',
            '<x-ui.button size="sm" icon="add">x</x-ui.button>',
            '<x-ui.button size="square" icon="add" />',
            '<x-ui.button variant="primary" icon="send" split>x</x-ui.button>',
            '<x-ui.form-actions cancel-href="/a" />',
        ];

        foreach ($templates as $template) {
            $html = $this->render($template);
            preg_match_all('/class="([^"]*)"/', $html, $all);
            foreach (explode(' ', implode(' ', $all[1])) as $token) {
                $this->assertDoesNotMatchRegularExpression(
                    '/^(?:[mp][tbxy]?|gap)-[0-5]$/',
                    $token,
                    "`$token` collides with Bootstrap's !important utility in $template"
                );
            }
        }
    }

    public function test_form_actions_renders_cancel_and_submit_at_the_same_height(): void
    {
        $html = $this->render('<x-ui.form-actions cancel-href="/assets" submit-label="บันทึกการแก้ไข" submit-icon="check" form="main-form" />');

        $this->assertStringContainsString('<a href="/assets"', $html);
        $this->assertStringContainsString('ยกเลิก', $html);
        $this->assertStringContainsString('type="submit"', $html);
        $this->assertStringContainsString('form="main-form"', $html);
        $this->assertStringContainsString('บันทึกการแก้ไข', $html);
        $this->assertSame(2, substr_count($html, ' h-11 '), 'cancel and submit must be the same height');
        // a button is as wide as its label — no fixed / stretched width on the footer row
        $this->assertDoesNotMatchRegularExpression('/\b(?:sm:|md:)?(?:min-w|w-full|flex-1)\b/', $html);
    }

    public function test_form_actions_without_cancel_renders_only_the_submit(): void
    {
        $html = $this->render('<x-ui.form-actions />');

        $this->assertStringNotContainsString('<a ', $html);
        $this->assertStringContainsString('บันทึกข้อมูล', $html);
        $this->assertStringContainsString('>save<', $html);
    }

    public function test_back_button_uses_the_fallback_when_there_is_no_other_page_to_go_back_to(): void
    {
        $html = $this->render('<x-ui.back-button fallback="/assets" />');

        $this->assertStringContainsString('href="/assets"', $html);
        $this->assertStringContainsString('กลับ', $html);
        $this->assertStringContainsString('>chevron_left<', $html);
    }

    public function test_section_head_numbers_and_subtitle_are_optional(): void
    {
        $full = $this->render('<x-ui.section-head no="2" title="รายละเอียด" subtitle="อาการเสีย" />');
        $this->assertStringContainsString('>2<', $full);
        $this->assertStringContainsString('รายละเอียด', $full);
        $this->assertStringContainsString('อาการเสีย', $full);

        $bare = $this->render('<x-ui.section-head title="รายละเอียด" />');
        $this->assertStringNotContainsString('rounded-full border border-emerald-600', $bare);
        $this->assertStringNotContainsString('leading-snug', $bare);
    }
}

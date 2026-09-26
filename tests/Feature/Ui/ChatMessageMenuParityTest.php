<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * The "⋮" of a message is drawn twice: by chat/_message_menu.blade.php for the messages the server draws, and by resources/js/chat/page.js for the
 * ones that arrive while the page is open. A row that looked different depending on how it got there would be a bug nobody could see in a test of
 * either alone, so the class lists are compared.
 */
class ChatMessageMenuParityTest extends TestCase
{
    private function constant(string $js, string $name): string
    {
        $this->assertSame(1, preg_match("/export const {$name} = '([^']+)';/", $js, $m), "{$name} in page.js");

        return $m[1];
    }

    private function variable(string $blade, string $name): string
    {
        $this->assertSame(1, preg_match("/\\\${$name} = '([^']+)'/", $blade, $m), "{$name} in the partial");

        return $m[1];
    }

    private function sorted(string $classes): array
    {
        $list = preg_split('/\s+/', trim($classes));
        sort($list);

        return $list;
    }

    public function test_the_two_drawings_use_the_same_classes(): void
    {
        $js = file_get_contents(resource_path('js/chat/page.js'));
        $blade = file_get_contents(resource_path('views/chat/_message_menu.blade.php'));

        $this->assertSame($this->sorted($this->variable($blade, 'menuButton')), $this->sorted($this->constant($js, 'MENU_BUTTON')), 'the dots');
        $this->assertSame($this->sorted($this->variable($blade, 'menuItem')), $this->sorted($this->constant($js, 'MENU_ITEM')), 'an item');
        $this->assertSame($this->sorted($this->variable($blade, 'menuItemDanger')), $this->sorted($this->constant($js, 'MENU_ITEM_DANGER')), 'delete');

        // the box: the partial adds the side, the script adds it too
        $box = $this->variable($blade, 'menuBox');
        $this->assertSame($this->sorted($this->constant($js, 'MENU_BOX')), $this->sorted($box), 'the box (the side is added after it)');
    }

    public function test_the_dots_have_no_circle_or_box_only_the_icon_lights_up(): void
    {
        $js = file_get_contents(resource_path('js/chat/page.js'));
        $classes = $this->sorted($this->constant($js, 'MENU_BUTTON'));

        foreach ($classes as $class) {
            $this->assertDoesNotMatchRegularExpression('/^(rounded-full|rounded-(md|lg|xl)|bg-|hover:bg-|ring-|border)/', $class, "{$class}: a shape or a fill around the dots");
        }
        $this->assertContains('hover:text-slate-700', $classes, 'the icon lights up under the pointer');
        $this->assertContains('aria-expanded:text-slate-700', $classes, 'and stays lit while its menu is open');
        $this->assertContains('text-slate-400', $classes);
    }

    public function test_delete_in_the_menu_has_its_own_icon_not_the_plain_bin(): void
    {
        $blade = file_get_contents(resource_path('views/chat/_message_menu.blade.php'));
        $js = file_get_contents(resource_path('js/chat/page.js'));

        $this->assertStringContainsString('aria-hidden="true">delete_forever</span><span>ลบ</span>', $blade);
        $this->assertStringContainsString("'delete_forever', 'ลบ'", $js);
        $this->assertStringNotContainsString('>delete</span><span>ลบ', $blade);
    }
}

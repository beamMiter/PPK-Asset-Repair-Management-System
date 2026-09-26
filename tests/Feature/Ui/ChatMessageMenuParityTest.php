<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The "⋮" of a message is drawn twice: by chat/_message_menu.blade.php for the messages the server draws, and by resources/js/chat/page.js for the
 * ones that arrive while the page is open. A row that looked different depending on how it got there would be a bug nobody could see in a test of
 * either alone, so the class lists are compared. The button is also the standard ghost icon button (what every dialog closes with).
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

    public function test_the_dots_are_the_standard_ghost_icon_button(): void
    {
        $standard = Blade::render('<x-ui.button variant="ghost" size="icon" icon="more_vert" aria-label="x" />');
        $this->assertSame(1, preg_match('/class="([^"]*)"/', $standard, $m));
        $js = file_get_contents(resource_path('js/chat/page.js'));

        $ours = $this->sorted(str_replace('chat-msg-menu-btn ', '', $this->constant($js, 'MENU_BUTTON')));
        $this->assertSame($this->sorted($m[1]), $ours, 'the very classes of the button every dialog closes with (plus our own marker)');
    }
}

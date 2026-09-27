<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The close (X) of the chat widget was a hand-drawn SVG in its own grey box; every dialog of the app closes with
 * <x-ui.button variant="ghost" size="icon" icon="close" aria-label="ปิด">. The widget uses that one, so a change to the close button is one
 * change - and so does the widget's bell (desktop notifications), which sits beside it.
 */
class ChatWidgetCloseTest extends TestCase
{
    use RefreshDatabase;

    private function widget(): string
    {
        return $this->actingAs(User::factory()->create(['role' => 'member']))->get(route('maintenance.requests.index'))->assertOk()->getContent();
    }

    /** the class list of the tag that carries $needle */
    private function classesOf(string $html, string $needle): array
    {
        $at = strpos($html, $needle);
        $this->assertNotFalse($at, $needle);
        $start = strrpos(substr($html, 0, $at), '<');
        $tag = substr($html, $start, strpos($html, '>', $at) - $start);
        preg_match('/class="([^"]*)"/', $tag, $m);
        $classes = preg_split('/\s+/', trim($m[1] ?? ''));
        sort($classes);

        return $classes;
    }

    public function test_the_widgets_close_is_the_dialog_close_button(): void
    {
        $reference = Blade::render('<x-ui.button variant="ghost" size="icon" icon="close" aria-label="ปิด" />');

        $this->assertSame(
            $this->classesOf($reference, 'aria-label="ปิด"'),
            $this->classesOf($this->widget(), 'id="chatClose"'),
            'the very classes of a dialog\'s close button',
        );
    }

    public function test_it_is_a_material_close_icon_not_a_hand_drawn_svg(): void
    {
        $html = $this->widget();
        $tag = substr($html, strpos($html, 'id="chatClose"'), 600);

        $this->assertStringContainsString('>close</span>', $tag);
        $this->assertStringContainsString('aria-label="ปิด"', $tag);
        $this->assertStringNotContainsString('<svg', substr($tag, 0, strpos($tag, '</button>') ?: 600));
        $this->assertStringNotContainsString('M18.3 5.7a1 1 0 0 0-1.4-1.4', $html, 'the old hand-drawn X is gone');
    }

    public function test_the_bell_beside_it_is_the_same_kind_of_button_and_starts_hidden(): void
    {
        $classes = $this->classesOf($this->widget(), 'id="chatNotifyAsk"');

        $this->assertContains('hidden', $classes, 'shown by the script only while the browser has not been asked');
        foreach (['h-8', 'w-8', 'rounded-full'] as $class) {
            $this->assertContains($class, $classes, $class);
        }
    }
}

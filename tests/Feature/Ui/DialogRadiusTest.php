<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every popup card in the app has the same corner: rounded-md (6px) — the radius of the buttons and fields inside it, so a dialog
 * reads as slightly square, not as a pill. They had drifted apart: the job dialogs were rounded-2xl (16px), the "closed" dialog
 * rounded-3xl (24px), then all of them rounded-xl (12px) and the rating dialog rounded-sm (2px). Now assign team (the job page's
 * own copy and the edit page's), the history log, the four confirm dialogs, the "closed" dialog, the rating dialog, the shared
 * confirm dialog, the SLA print dialog, the three chat dialogs, the profile photo cropper and the chat widget's drawer are all
 * one value; to change it, change this one word in those places and here.
 */
class DialogRadiusTest extends TestCase
{
    private const RADIUS = 'rounded-md';

    /** @return array<string,array{0:string,1:string}> a name => [view path, the exact class attribute of its popup card] */
    public static function cards(): array
    {
        return [
            'modal_assign' => [
                'maintenance/requests/partials/_modal_assign.blade.php',
                'class="relative z-[10000] w-full max-w-4xl overflow-hidden rounded-md border {{ $line }} bg-white ">',
            ],
            'modal_history' => [
                'maintenance/requests/partials/_modal_history.blade.php',
                'class="relative z-[10000] w-full max-w-2xl rounded-md border {{ $line }} bg-white overflow-hidden animate-in fade-in zoom-in duration-200">',
            ],
            'modal_post_close' => [
                'maintenance/requests/partials/_modal_post_close.blade.php',
                'class="overflow-hidden rounded-md border border-slate-200 bg-white ">',
            ],
            'modal_rating' => [
                'maintenance/requests/partials/_modal_rating.blade.php',
                'class="relative z-[10000] w-full max-w-lg max-h-[92vh] overflow-y-auto rounded-md border border-slate-200 bg-white ">',
            ],
            'edit_page_assign' => [
                'maintenance/requests/edit.blade.php',
                'class="relative z-[10000] w-full max-w-4xl overflow-hidden rounded-md border {{ $line }} bg-white ">',
            ],
            'profile_cropper' => [
                'profile/edit.blade.php',
                'class="relative w-full max-w-xl rounded-md bg-white overflow-hidden">',
            ],
            'confirm_dialog' => [
                'components/confirm-dialog.blade.php',
                'class="w-full max-w-md bg-white rounded-md overflow-hidden border border-slate-200"',
            ],
            'sla_print_dialog' => [
                'maintenance/sla/index.blade.php',
                'class="bg-white rounded-md w-full max-w-2xl max-h-[92vh] flex flex-col overflow-hidden border border-slate-200"',
            ],
            'chat_new_thread' => [
                'chat/index.blade.php',
                'class="bg-white rounded-md w-full max-w-md overflow-hidden border border-slate-200"',
            ],
            'chat_small_dialogs' => [
                'chat/index.blade.php',
                'class="bg-white rounded-md w-full max-w-sm overflow-hidden border border-slate-200"',
            ],
            'chat_drawer' => [
                'partials/chat-fab.blade.php',
                'rounded-md border border-zinc-200 bg-white ">',
            ],
        ];
    }

    #[DataProvider('cards')]
    public function test_the_popup_card_has_the_one_radius(string $view, string $card): void
    {
        $source = file_get_contents(resource_path('views/' . $view));

        $this->assertStringContainsString($card, $source, "$view: the popup card");
        $this->assertStringContainsString(self::RADIUS, $card);
        $this->assertDoesNotMatchRegularExpression('/rounded-(sm|lg|xl|2xl|3xl)\b/', $card, "$view: a different radius");
    }

    public function test_the_four_status_confirm_dialogs_all_match(): void
    {
        $source = file_get_contents(resource_path('views/maintenance/requests/partials/_modal_status_actions.blade.php'));

        $this->assertSame(4, substr_count($source, 'class="relative z-[10000] w-full max-w-xl rounded-md border {{ $line }} bg-white ">'), 'reject, cancel, hold and close');
        $this->assertDoesNotMatchRegularExpression('/rounded-(sm|lg|xl|2xl|3xl)\b/', $source);
    }

    /** A popup added later with a corner of its own: any card in a view that holds an overlay says `bg-white … max-w-…` and must say rounded-md. */
    public function test_no_popup_card_anywhere_has_another_radius(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            $source = $file->getContents();
            if (! str_contains($source, 'fixed inset-0')) {
                continue;   // no overlay in this view, so no popup
            }

            foreach (preg_split('/\R/', $source) as $n => $line) {
                if (preg_match('/max-w-(sm|md|lg|xl|2xl|3xl|4xl|5xl)\b/', $line)
                    && str_contains($line, 'bg-white')
                    && preg_match('/rounded-(sm|lg|xl|2xl|3xl)\b/', $line)) {
                    $offenders[] = $file->getRelativePathname() . ':' . ($n + 1) . ' ' . trim($line);
                }
            }
        }

        $this->assertSame([], $offenders, 'a popup card with a radius other than ' . self::RADIUS);
    }
}

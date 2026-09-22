<?php

namespace Tests\Feature\Ui;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every popup card — assign team (the job page's own copy and the edit page's), the history log, the four confirm
 * dialogs, the "closed" dialog, the shared confirm dialog, the profile photo cropper, and the chat widget's drawer —
 * used rounded-2xl (16px) or, for the "closed" dialog, rounded-3xl (24px). All of them are rounded-xl (12px) now, less
 * than either and the same as each other. The rating dialog (rounded-sm, 2px) is not part of this: it was already
 * smaller than the new size, and the ask was to reduce the ones that were too round, not to round up the one that
 * wasn't.
 */
class DialogRadiusTest extends TestCase
{
    /** @return array<string,array{0:string,1:string}> view path => [view path, the exact class attribute its popup card had before] */
    public static function cards(): array
    {
        return [
            'modal_assign' => [
                'maintenance/requests/partials/_modal_assign.blade.php',
                'class="relative z-[10000] w-full max-w-4xl overflow-hidden rounded-2xl border {{ $line }} bg-white ">',
            ],
            'modal_history' => [
                'maintenance/requests/partials/_modal_history.blade.php',
                'class="relative z-[10000] w-full max-w-2xl rounded-2xl border {{ $line }} bg-white overflow-hidden animate-in fade-in zoom-in duration-200">',
            ],
            'modal_post_close' => [
                'maintenance/requests/partials/_modal_post_close.blade.php',
                'class="overflow-hidden rounded-3xl border border-slate-200 bg-white ">',
            ],
            'edit_page_assign' => [
                'maintenance/requests/edit.blade.php',
                'class="relative z-[10000] w-full max-w-4xl overflow-hidden rounded-2xl border {{ $line }} bg-white ">',
            ],
            'profile_cropper' => [
                'profile/edit.blade.php',
                'class="relative w-full max-w-xl rounded-2xl bg-white overflow-hidden">',
            ],
            'confirm_dialog' => [
                'components/confirm-dialog.blade.php',
                'class="w-full max-w-md bg-white rounded-2xl overflow-hidden border border-slate-200"',
            ],
            'chat_drawer' => [
                'partials/chat-fab.blade.php',
                'rounded-2xl border border-zinc-200 bg-white ">',
            ],
        ];
    }

    #[DataProvider('cards')]
    public function test_the_popup_card_is_rounded_xl_not_2xl_or_3xl(string $view, string $was): void
    {
        $source = file_get_contents(resource_path('views/'.$view));
        $now = str_replace(['rounded-2xl', 'rounded-3xl'], 'rounded-xl', $was);

        $this->assertStringContainsString($now, $source, "$view: the popup card is rounded-xl");
        $this->assertStringNotContainsString($was, $source, "$view: the old, bigger radius is gone");
    }

    public function test_the_four_status_confirm_dialogs_are_all_rounded_xl(): void
    {
        $source = file_get_contents(resource_path('views/maintenance/requests/partials/_modal_status_actions.blade.php'));

        $this->assertSame(4, substr_count($source, 'class="relative z-[10000] w-full max-w-xl rounded-xl border {{ $line }} bg-white ">'), 'reject, cancel, hold and close all match');
        $this->assertStringNotContainsString('rounded-2xl', $source);
    }

    public function test_the_rating_dialog_is_untouched(): void
    {
        // already the smallest radius of any dialog on the page; the ask was to shrink the ones that were too round
        $this->assertStringContainsString('rounded-sm', file_get_contents(resource_path('views/maintenance/requests/partials/_modal_rating.blade.php')));
    }
}

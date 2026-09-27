{{--
  The "⋮" beside a message: edit and delete. resources/js/chat/page.js (messageMenu) draws the same thing for a message that arrives while the page is
  open, so a change here is a change there (ChatMessageMenuParityTest compares the class lists).

  The menu is as wide as its longest item, not a fixed box; an item's icon has a box of its own (20px, clipped), so an icon font that has not loaded can never push the label out of view.
  The dots have no circle or box around them: the icon just lights up (darkens) under the pointer, and stays lit while its menu is open.
  Icons: rate_review (a bubble with a pencil) for edit, chat_error (a bubble with a cross) for delete - both say "this message", not just "pencil" / "bin".

  $canEdit       its author may edit it (the thread is open)
  $canDelete     this person may delete it (its author while the thread is open, or a moderator)
  $canModerate   this person is a moderator: delete stays open to them when the thread is locked (data-when="always"); every other item is
                 data-when="open" and the page hides it, without a reload, the moment the thread is locked
  $side          'right' for my own rows (the menu opens toward the left edge of the bubble), 'left' for the others
--}}
@php
    $menuButton = 'chat-msg-menu-btn inline-flex h-8 w-6 shrink-0 items-center justify-center rounded text-slate-400 transition-colors hover:text-slate-700 aria-expanded:text-slate-700 focus:outline-none focus-visible:text-slate-700 focus-visible:ring-2 focus-visible:ring-slate-300';
    $menuBox = 'chat-msg-menu hidden absolute z-20 top-full mt-[4px] w-max min-w-[96px] overflow-hidden rounded-md border border-slate-200 bg-white py-[4px] ' . ($side === 'right' ? 'right-0' : 'left-0');
    $menuItem = 'flex w-full items-center gap-[8px] px-[12px] py-[7px] text-left text-[13.5px] text-slate-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none';
    $menuItemDanger = 'flex w-full items-center gap-[8px] px-[12px] py-[7px] text-left text-[13.5px] text-rose-600 hover:bg-rose-50 focus:bg-rose-50 focus:outline-none';
@endphp
@if ($canEdit || $canDelete)
    <div class="chat-msg-menu-wrap relative shrink-0">
        <button type="button" class="{{ $menuButton }}" aria-haspopup="menu" aria-expanded="false" title="ตัวเลือกข้อความ" aria-label="ตัวเลือกข้อความ">
            <span class="material-symbols-outlined text-[24px]" aria-hidden="true">more_vert</span>
        </button>
        <div class="{{ $menuBox }}" role="menu">
            @if ($canEdit)
                <button type="button" role="menuitem" class="chat-msg-edit {{ $menuItem }}" data-when="open">
                    <span class="material-symbols-outlined text-[20px] h-[20px] w-[20px] shrink-0 overflow-hidden" aria-hidden="true">rate_review</span><span>แก้ไข</span>
                </button>
            @endif
            @if ($canDelete)
                <button type="button" role="menuitem" class="chat-msg-delete {{ $menuItemDanger }}" data-when="{{ ($canModerate ?? false) ? 'always' : 'open' }}">
                    <span class="material-symbols-outlined text-[20px] h-[20px] w-[20px] shrink-0 overflow-hidden" aria-hidden="true">chat_error</span><span>ลบ</span>
                </button>
            @endif
        </div>
    </div>
@endif

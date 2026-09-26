{{--
  The "⋮" beside a message: edit and delete. resources/js/chat/page.js (messageMenu) draws the same thing for a message that arrives while the page is
  open, so a change here is a change there (ChatMessageMenuParityTest compares the class lists).

  $canEdit       its author may edit it (the thread is open)
  $canDelete     this person may delete it (its author while the thread is open, or a moderator)
  $canModerate   this person is a moderator: delete stays open to them when the thread is locked (data-when="always"); every other item is
                 data-when="open" and the page hides it, without a reload, the moment the thread is locked
  $side          'right' for my own rows (the menu opens toward the left edge of the bubble), 'left' for the others
--}}
@php
    $menuButton = 'chat-msg-menu-btn inline-flex items-center justify-center font-semibold whitespace-nowrap select-none transition-all active:scale-95 focus:outline-none focus:ring-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100 h-8 w-8 shrink-0 rounded-full text-[13px] gap-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 focus:ring-slate-200';
    $menuBox = 'chat-msg-menu hidden absolute z-20 top-full mt-[4px] w-[140px] overflow-hidden rounded-md border border-slate-200 bg-white py-[4px] ' . ($side === 'right' ? 'right-0' : 'left-0');
    $menuItem = 'flex w-full items-center gap-[8px] px-[12px] py-[8px] text-left text-[13px] text-slate-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none';
    $menuItemDanger = 'flex w-full items-center gap-[8px] px-[12px] py-[8px] text-left text-[13px] text-rose-600 hover:bg-rose-50 focus:bg-rose-50 focus:outline-none';
@endphp
@if ($canEdit || $canDelete)
    <div class="chat-msg-menu-wrap relative shrink-0">
        <button type="button" class="{{ $menuButton }}" aria-haspopup="menu" aria-expanded="false" title="ตัวเลือกข้อความ" aria-label="ตัวเลือกข้อความ">
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">more_vert</span>
        </button>
        <div class="{{ $menuBox }}" role="menu">
            @if ($canEdit)
                <button type="button" role="menuitem" class="chat-msg-edit {{ $menuItem }}" data-when="open">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">edit</span><span>แก้ไข</span>
                </button>
            @endif
            @if ($canDelete)
                <button type="button" role="menuitem" class="chat-msg-delete {{ $menuItemDanger }}" data-when="{{ ($canModerate ?? false) ? 'always' : 'open' }}">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span><span>ลบ</span>
                </button>
            @endif
        </div>
    </div>
@endif

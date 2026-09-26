{{--
  One message of an open thread, as the server draws it. resources/js/chat/page.js (buildMessageRow) draws the same row for a message that
  arrives while the page is open, so a change here is a change there.

  $m            the message (may be soft-deleted: it is drawn as "ข้อความนี้ถูกลบ", the text is never sent)
  $isMe         written by the person looking
  $isConsecutive the same person wrote the message before it (no avatar and name again)
  $first        the first row of the list
  $canDelete    this person may delete this message (its author while the thread is open, or a moderator)
--}}
@php
    $deleted = $m->trashed();
    $gap = $first ? 'mt-0' : ($isConsecutive ? 'mt-1' : 'mt-4');
    $when = \App\Support\ThaiDate::chatTime($m->created_at);
    $deleteButton = 'chat-msg-delete shrink-0 rounded-full p-1 text-gray-300 hover:bg-red-50 hover:text-red-500 focus:outline-none focus:ring-2 focus:ring-red-200';
@endphp

@if ($isMe)
    {{-- RIGHT SIDE (ME) --}}
    <div class="chat-msg-row flex flex-col items-end w-full {{ $gap }}" data-user-id="{{ $m->user_id }}" data-message-id="{{ $m->id }}"
        @if ($deleted) data-deleted="1" @endif>
        <div class="flex items-center gap-2 mb-1">
            <span class="text-xs text-gray-500">{{ $when }}</span>
            <span class="text-[13px] font-semibold text-gray-900">คุณ</span>
        </div>
        <div class="flex items-center justify-end gap-1 max-w-[85%] sm:max-w-[70%]">
            @if ($canDelete && ! $deleted)
                <button type="button" class="{{ $deleteButton }}" title="ลบข้อความนี้" aria-label="ลบข้อความนี้">
                    <span class="material-symbols-outlined text-[16px] leading-none" aria-hidden="true">delete</span>
                </button>
            @endif
            @if ($deleted)
                <div class="rounded-2xl rounded-tr-none border border-gray-100 bg-gray-50 py-2.5 px-4 text-[14px] italic text-gray-400">
                    <div class="msg-body">ข้อความนี้ถูกลบ</div>
                </div>
            @else
                <div class="bg-blue-600 text-white rounded-2xl rounded-tr-none py-2.5 px-4 text-[15px] leading-relaxed">
                    <div class="whitespace-pre-line break-words msg-body">{{ $m->body }}</div>
                </div>
            @endif
        </div>
    </div>
@else
    {{-- LEFT SIDE (THEM) --}}
    <div class="chat-msg-row flex items-start gap-3 w-full {{ $gap }}" data-user-id="{{ $m->user_id }}" data-message-id="{{ $m->id }}"
        @if ($deleted) data-deleted="1" @endif>
        <div class="relative shrink-0 {{ $isConsecutive ? 'opacity-0 h-0 pointer-events-none' : '' }}">
            @if (! $isConsecutive)
                <img src="{{ $m->user?->avatar_thumb_url ?? \App\Support\InitialsAvatar::url($m->user->name ?? '?', 80) }}"
                    class="h-10 w-10 rounded-full object-cover border border-gray-200" alt="{{ $m->user?->name ?? 'ผู้ใช้' }}">
            @else
                <div class="w-10"></div>
            @endif
        </div>
        <div class="flex flex-col items-start min-w-0 max-w-[85%] sm:max-w-[70%]">
            @if (! $isConsecutive)
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-[13px] font-semibold text-gray-900">{{ $m->user?->name ?? 'ไม่ทราบผู้ใช้งาน' }}</span>
                    <span class="text-xs text-gray-500">{{ $when }}</span>
                </div>
            @endif
            <div class="flex items-center gap-1">
                @if ($deleted)
                    <div class="rounded-2xl {{ ! $isConsecutive ? 'rounded-tl-none' : '' }} border border-gray-100 bg-gray-50 py-2.5 px-4 text-[14px] italic text-gray-400">
                        <div class="msg-body">ข้อความนี้ถูกลบ</div>
                    </div>
                @else
                    <div class="bg-gray-50 border border-gray-100/80 text-gray-900 rounded-2xl {{ ! $isConsecutive ? 'rounded-tl-none' : '' }} py-2.5 px-4 text-[15px] leading-relaxed">
                        <div class="whitespace-pre-line break-words msg-body">{{ $m->body }}</div>
                    </div>
                @endif
                @if ($canDelete && ! $deleted)
                    <button type="button" class="{{ $deleteButton }}" title="ลบข้อความนี้" aria-label="ลบข้อความนี้">
                        <span class="material-symbols-outlined text-[16px] leading-none" aria-hidden="true">delete</span>
                    </button>
                @endif
            </div>
        </div>
    </div>
@endif

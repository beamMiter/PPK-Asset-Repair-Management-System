@extends('layouts.app')
@section('title', 'Livechat')

@section('content')
    @php
        $q = request('q');
        $activeThreadId = request('thread_id');
        // We only highlight if the thread_id is explicitly in the request to prevent "permanent" first item color
        $defaultThreadId = $activeThreadId;
    @endphp

    {{-- Main Container: Unified Pane --}}
    <div id="chat-pane" x-data="{
        showCreateModal: false,
        newThreadTitle: '',
        showLockModal: false,
        showDeleteModal: false,
        submitThread() {
            if (!this.newThreadTitle.trim()) {
                alert('กรุณากรอกหัวข้อกระทู้');
                return;
            }
            document.getElementById('final-thread-title').value = this.newThreadTitle.trim();
            window.Loader?.show();
            document.getElementById('hidden-create-thread').submit();
        },
        submitLock() {
            window.Loader?.show();
            document.getElementById('hidden-lock-thread').submit();
        },
        submitDelete() {
            window.Loader?.show();
            document.getElementById('hidden-delete-thread').submit();
        },
        chatStatus: 'connecting', // 'connecting', 'online', 'offline'
        showEmojiPicker: false,
        curatedEmojis: {
            'Smileys': ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😋', '😛', '😜', '🧐', '😎', '🥳', '😡', '😭', '😱', '🤔', '🤫'],
            'Hands & Hearts': ['👍', '👎', '👌', '✌️', '🤞', '🤟', '👏', '🙌', '🙏', '🤝', '💪', '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '💔', '❣️', '💕', '💞', '💓', '💗', '💖', '✨', '🔥', '💯'],
            'Tasks & Objects': ['✅', '❌', '⚠️', '💡', '📝', '📌', '📎', '📂', '📅', '⏰', '💻', '📱', '🔋', '⚙️', '🛠', '🔧', '🔨', '📦', '📧', '🔔', '🚀', '🏁', '🔒', '🔓']
        },
        insertEmoji(emoji) {
            const el = document.getElementById('msgInput');
            if (!el) return;
            const start = el.selectionStart;
            const end = el.selectionEnd;
            const val = el.value;
            el.value = val.substring(0, start) + emoji + val.substring(end);
            el.selectionStart = el.selectionEnd = start + emoji.length;
            el.focus();
            // Trigger input to resize/re-evaluate textarea
            el.dispatchEvent(new Event('input'));
    
            // Close the picker immediately after choosing
            this.showEmojiPicker = false;
        }
    }"
        @keydown.escape.window="showCreateModal = false; showLockModal = false; showEmojiPicker = false"
        class="flex flex-col lg:flex-row flex-1 w-full min-h-0 bg-white border-t-0">

        {{-- LEFT PANEL: Thread List (Hidden on mobile if a thread is active) --}}
        <div
            class="w-full lg:w-[420px] flex-shrink-0 flex flex-col border-r border-slate-200 bg-white h-full relative z-10 {{ request('thread_id') ? 'hidden lg:flex' : '' }}">
            {{-- Header & Search --}}
            <div class="p-4 border-b border-slate-200 bg-white flex-shrink-0 z-10 relative">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-start gap-3 min-w-0">
                        {{-- Header glyph — same style as My Jobs / users / settings pages --}}
                        <span class="material-symbols-outlined text-[32px] text-[#0F2D5C] mt-0.5"
                            aria-hidden="true">forum</span>
                        <div>
                            <h1 class="text-[17px] font-semibold text-slate-900">กระดานสนทนา</h1>
                            <p class="text-[12px] text-slate-500 mt-0.5">พื้นที่แลกเปลี่ยนข้อมูลองค์กร</p>
                        </div>
                    </div>

                    <button type="button" @click="showCreateModal = true"
                        class="inline-flex items-center gap-2 rounded-md bg-[#0F2D5C] px-4 py-2 text-[13px] font-semibold text-white hover:bg-[#0F2D5C]/90 transition-all focus:outline-none focus:ring-2 focus:ring-[#0F2D5C]/40 active:scale-95"
                        title="สร้างกระทู้ใหม่">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        สร้างกระทู้
                    </button>
                </div>

                <div class="mt-4">
                    <form method="GET" action="{{ route('chat.index') }}" class="flex items-center gap-2">
                        <div class="flex-1">
                            <div class="relative">
                                <input name="q" value="{{ $q }}"
                                    class="w-full rounded-md border border-slate-200 bg-white pl-9 pr-3 py-2 text-[13px] placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#0F2D5C]/35 focus:border-[#0F2D5C]/35 transition-all "
                                    placeholder="ค้นหากระทู้...">
                                <span
                                    class="pointer-events-none absolute inset-y-0 left-0 flex w-9 items-center justify-center text-slate-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M21 21l-4.3-4.3M17 10a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </span>
                            </div>
                        </div>

                        <button type="submit"
                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#0F2D5C] text-white hover:bg-[#0F2D5C]/90 transition-all focus:outline-none focus:ring-2 focus:ring-[#0F2D5C]/45 active:scale-95"
                            title="ค้นหา" aria-label="ค้นหา">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M21 21l-4.3-4.3M17 10a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>

            <div class="px-4 py-2 border-b border-slate-100 bg-slate-50 flex items-center justify-between flex-shrink-0">
                <span class="text-[11px] font-bold text-slate-600 uppercase tracking-widest">รายการอัปเดต</span>
                <span class="text-[11px] font-semibold text-slate-400">
                    {{ number_format($threads->total()) }} รายการ
                </span>
            </div>

            {{-- List --}}
            <div class="flex-1 overflow-y-auto bg-white divide-y divide-slate-100">
                @forelse($threads as $th)
                    @php
                        $isActive = $defaultThreadId == $th->id;
                    @endphp
                    <div
                        class="group relative transition-colors chat-item {{ $isActive ? 'bg-[#F4F7FB]' : 'hover:bg-slate-50' }}">
                        @if ($isActive)
                            <div class="absolute inset-y-0 left-0 w-1 bg-[#0F2D5C] z-20"></div>
                        @endif
                        <a href="{{ route('chat.index', ['thread_id' => $th->id, 'page' => $threads->currentPage()]) }}"
                            class="block px-4 py-3.5 chat-thread-link">

                            <div class="flex flex-col gap-1.5">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex flex-wrap gap-1.5 items-center">
                                        @if ($th->is_locked)
                                            <span
                                                class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-50 text-amber-600 border border-amber-200 uppercase tracking-wide">Locked</span>
                                        @endif
                                        <h3
                                            class="truncate text-[14px] font-medium text-slate-800 {{ $isActive ? 'text-[#0F2D5C] font-semibold' : 'group-hover:text-[#0F2D5C]' }}">
                                            {{ $th->title }}
                                        </h3>
                                    </div>
                                    <div
                                        class="flex items-center gap-1 text-slate-400 text-[10px] bg-slate-50 px-1.5 py-0.5 rounded-full shrink-0">
                                        <span class="material-symbols-outlined text-[12px]">chat_bubble</span>
                                        <span id="thread-count-{{ $th->id }}"
                                            class="font-bold">{{ $th->messages_count ?? 0 }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-x-2 text-[11px] text-slate-500">
                                    <span
                                        class="font-medium text-slate-600 truncate max-w-[120px]">{{ $th->author->name ?? 'Unknown user' }}</span>
                                    <span class="w-1 h-1 rounded-full bg-slate-300"></span>
                                    <span>{{ $th->updated_at->diffForHumans() }}</span>
                                </div>
                            </div>
                        </a>
                    </div>
                @empty
                    <div class="py-16 bg-white">
                        <x-ui.empty-state icon="forum">ไม่พบข้อมูลกระทู้</x-ui.empty-state>
                    </div>
                @endforelse

                @if ($threads->hasPages())
                    <div
                        class="px-4 py-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between shrink-0 -[0_-2px_6px_-2px_rgba(0,0,0,0.03)] z-10">
                        <a href="{{ $threads->previousPageUrl() ?? '#' }}"
                            class="inline-flex items-center justify-center h-8 px-3 rounded-md text-[11.5px] font-medium bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors {{ $threads->onFirstPage() ? 'opacity-40 pointer-events-none' : '' }}">
                            <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M15 18l-6-6 6-6" />
                            </svg>
                            ก่อนหน้า
                        </a>
                        <span class="text-[11px] text-slate-400 font-semibold tracking-wide">หน้าที่
                            {{ $threads->currentPage() }} / {{ $threads->lastPage() }}</span>
                        <a href="{{ $threads->nextPageUrl() ?? '#' }}"
                            class="inline-flex items-center justify-center h-8 px-3 rounded-md text-[11.5px] font-medium bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition-colors {{ !$threads->hasMorePages() ? 'opacity-40 pointer-events-none' : '' }}">
                            ถัดไป
                            <svg viewBox="0 0 24 24" class="w-3.5 h-3.5 ml-1" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 18l6-6-6-6" />
                            </svg>
                        </a>
                    </div>
                @endif
            </div>
        </div>

        {{-- RIGHT PANEL: Chat Container --}}
        <div class="hidden lg:flex flex-1 flex-col bg-slate-50 relative h-full {{ request('thread_id') ? '!flex' : '' }}">
            {{-- Localized Loader for Right Panel (SPA transitions) --}}
            <div id="panelLoader"
                class="hidden absolute inset-0 z-[60] bg-slate-50/60 backdrop-blur-sm items-center justify-center">
                <div class="loader-spinner"></div>
            </div>
            @if ($activeThread)
                @php $thread = $activeThread; @endphp
                {{-- UNTITLED UI HEADER LAYOUT --}}
                <header class="shrink-0 w-full bg-white border-b border-gray-200 z-20">
                    <div
                        class="flex flex-col sm:flex-row sm:items-center justify-between px-4 py-4 sm:px-6 sm:py-5 gap-2 sm:gap-3">
                        <div class="flex items-start sm:items-center gap-3 w-full sm:flex-1 min-w-0">
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-gray-100 border border-gray-200 overflow-hidden mt-0.5 sm:mt-0">
                                <img src="{{ $thread->author?->avatar_thumb_url ?? \App\Support\InitialsAvatar::url($thread->author?->name ?? '?', 96) }}"
                                    class="h-full w-full object-cover" alt="Author">
                            </div>
                            <div class="flex flex-col min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <h1 class="text-[15px] sm:text-lg font-bold text-gray-900 leading-tight">
                                        {{ $thread->title }}
                                    </h1>
                                    @if ($thread->is_locked)
                                        <span
                                            class="rounded bg-amber-100 px-2 py-0.5 text-[10px] font-bold tracking-wider text-amber-800 uppercase shrink-0">Locked</span>
                                    @endif
                                </div>
                                <div
                                    class="flex items-center gap-1.5 sm:gap-2 text-[11px] sm:text-[12px] text-gray-500 mt-0.5">
                                    <span class="truncate"><span class="hidden sm:inline">ผู้ตั้งกระทู้: </span><span
                                            class="font-medium text-gray-700">{{ $thread->author?->name ?? 'ไม่ทราบผู้ใช้งาน' }}</span></span>
                                    <span class="w-1 h-1 rounded-full bg-gray-300 shrink-0"></span>
                                    <span class="shrink-0">{{ number_format($totalMessages) }} ข้อความ</span>
                                </div>
                            </div>
                        </div>
                        <div
                            class="flex items-center justify-end gap-1.5 sm:gap-3 w-full sm:w-auto shrink-0 pl-[60px] sm:pl-0 mt-1 sm:mt-0">

                            {{-- Header tools: icon only, no button background (the words live in title / aria-label) --}}
                            @if ($canManageLock)
                                @php $lockLabel = $thread->is_locked ? 'ปลดล็อกกระทู้' : 'ล็อกกระทู้'; @endphp
                                <x-ui.button variant="ghost-warning" size="icon-lg" :icon="$thread->is_locked ? 'lock_open' : 'lock'"
                                    @click="showLockModal = true" class="hidden sm:inline-flex" :title="$lockLabel"
                                    :aria-label="$lockLabel" />
                            @endif

                            @if (Auth::user()->role === 'admin')
                                <x-ui.button variant="ghost-danger" size="icon-lg" icon="delete"
                                    @click="showDeleteModal = true" title="ลบกระทู้" aria-label="ลบกระทู้" />
                            @endif

                            <x-ui.button id="btnHeaderRefresh" variant="ghost" size="icon-lg" icon="refresh"
                                @click="if(typeof window.forceChatPoll === 'function') window.forceChatPoll()"
                                class="hidden sm:inline-flex" title="รีเฟรช" aria-label="รีเฟรช" />

                            <a href="{{ route('chat.index') }}"
                                class="lg:hidden inline-flex items-center justify-center h-9 gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 sm:px-4 text-[13px] font-medium text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-200 transition-all">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2">
                                    <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <span class="hidden sm:inline">กลับ</span>
                            </a>
                        </div>
                    </div>
                </header>

                {{-- Floating Go-To-Bottom Button --}}
                <div class="relative w-full h-0 z-20">
                    <button id="btnScrollBottom" type="button"
                        class="hidden absolute top-4 right-4 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 flex items-center gap-1.5 transition-all">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            class="w-3.5 h-3.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                        </svg>
                        ท้ายแชท
                    </button>
                </div>

                {{-- CHAT SCROLL AREA --}}
                <div id="chatBox" data-thread-id="{{ $activeThread->id }}" data-my-id="{{ $me->id ?? 0 }}"
                    data-last-id="{{ $messages->last()?->id ?? 0 }}"
                    data-last-user-id="{{ $messages->last()?->user_id ?? 0 }}"
                    data-chat-url="{{ route('chat.messages', $activeThread) }}"
                    class="flex-1 overflow-y-auto w-full px-4 pt-3 pb-4 md:px-6 md:pt-5 md:pb-6 bg-slate-50 min-h-0 relative">
                    @if ($messages->isEmpty())
                        {{-- Empty State --}}
                        <div class="flex h-full items-center justify-center" id="emptyStateMsg">
                            <x-ui.empty-state icon="forum" hint="Send a message to start.">เริ่มการสนทนา</x-ui.empty-state>
                        </div>
                    @else
                        {{-- Message List (Untitled UI Design) --}}
                        <div class="pb-2 flex flex-col">
                            @php $lastUserId = null; @endphp
                            @foreach ($messages as $m)
                                @php
                                    $isMe = $me && $m->user_id === $me->id;
                                    $isConsecutive = $lastUserId === $m->user_id;
                                    $lastUserId = $m->user_id;
                                    $intl = mb_substr($m->user->name, 0, 1);
                                @endphp

                                @if ($isMe)
                                    {{-- RIGHT SIDE (ME) --}}
                                    <div class="chat-msg-row flex flex-col items-end w-full {{ $loop->first ? 'mt-0' : ($isConsecutive ? 'mt-1' : 'mt-4') }}"
                                        data-user-id="{{ $m->user_id }}">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span
                                                class="text-xs text-gray-500">{{ $m->created_at->format('l g:ia') }}</span>
                                            <span class="text-[13px] font-semibold text-gray-900">You</span>
                                        </div>
                                        <div
                                            class="bg-blue-600 text-white rounded-2xl rounded-tr-none py-2.5 px-4 max-w-[85%] sm:max-w-[70%] text-[15px] leading-relaxed ">
                                            <div class="whitespace-pre-line break-words">{{ $m->body }}</div>
                                        </div>
                                    </div>
                                @else
                                    {{-- LEFT SIDE (THEM) --}}
                                    <div class="chat-msg-row flex items-start gap-3 w-full {{ $loop->first ? 'mt-0' : ($isConsecutive ? 'mt-1' : 'mt-4') }}"
                                        data-user-id="{{ $m->user_id }}">
                                        <div
                                            class="relative shrink-0 {{ $isConsecutive ? 'opacity-0 h-0 pointer-events-none' : '' }}">
                                            @if (!$isConsecutive)
                                                <img src="{{ $m->user?->avatar_thumb_url ?? \App\Support\InitialsAvatar::url($m->user->name ?? '?', 80) }}"
                                                    class="h-10 w-10 rounded-full object-cover border border-gray-200 "
                                                    alt="{{ $m->user?->name ?? 'User' }}">
                                            @else
                                                <div class="w-10"></div>
                                            @endif
                                        </div>
                                        <div class="flex flex-col items-start min-w-0 max-w-[85%] sm:max-w-[70%]">
                                            @if (!$isConsecutive)
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span
                                                        class="text-[13px] font-semibold text-gray-900">{{ $m->user->name }}</span>
                                                    <span
                                                        class="text-xs text-gray-500">{{ $m->created_at->format('l g:ia') }}</span>
                                                </div>
                                            @endif
                                            <div
                                                class="bg-gray-50 border border-gray-100/80 text-gray-900 rounded-2xl {{ !$isConsecutive ? 'rounded-tl-none' : '' }} py-2.5 px-4 text-[15px] leading-relaxed">
                                                <div class="whitespace-pre-line break-words">{{ $m->body }}</div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- BOTTOM INPUT FORM --}}
                <div class="shrink-0 w-full bg-white px-4 py-4 sm:px-6 border-t border-gray-100">
                    @if (!$thread->is_locked)
                        <div class="mx-auto w-full max-w-screen-lg">
                            <form method="POST" action="{{ route('chat.messages.store', $thread) }}" id="chatForm">
                                @csrf
                                <div
                                    class="relative rounded-2xl border border-gray-200 bg-white focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500 transition-all flex flex-col">
                                    <label for="msgInput" class="sr-only">พิมพ์ข้อความ</label>
                                    <textarea id="msgInput" name="body" required maxlength="3000" placeholder="Send a message" rows="1"
                                        class="block w-full resize-none border-0 bg-transparent py-3.5 px-4 text-[14.5px] text-gray-900 placeholder:text-gray-400 focus:ring-0 min-h-[52px] max-h-[160px] scrollbar-thin scrollbar-thumb-gray-200"></textarea>

                                    <div
                                        class="flex items-center justify-between px-3 pb-2 pt-1 border-t border-transparent">
                                        <div class="flex items-center gap-1">
                                            <div class="relative" @click.away="showEmojiPicker = false">
                                                <button type="button" @click="showEmojiPicker = !showEmojiPicker"
                                                    class="rounded-full p-2 text-gray-400 hover:bg-gray-50 hover:text-gray-500 transition-colors {{ $thread->is_locked ? 'opacity-50 pointer-events-none' : '' }}"
                                                    title="Emoji">
                                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </button>

                                                {{-- Emoji Picker Popover --}}
                                                <div x-show="showEmojiPicker"
                                                    x-transition:enter="transition ease-out duration-200"
                                                    x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                                                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                                    x-transition:leave="transition ease-in duration-100"
                                                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                                    x-transition:leave-end="opacity-0 translate-y-2 scale-95"
                                                    class="absolute bottom-full left-0 mb-3 w-[280px] sm:w-[320px] bg-white rounded-xl -[0_10px_40px_-10px_rgba(0,0,0,0.15)] border border-slate-200 z-[100] overflow-hidden"
                                                    x-cloak style="display: none;">

                                                    <div
                                                        class="p-3 max-h-[300px] overflow-y-auto scrollbar-thin scrollbar-thumb-slate-200">
                                                        <template x-for="(list, category) in curatedEmojis"
                                                            :key="category">
                                                            <div class="mb-4 last:mb-0">
                                                                <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2 px-1"
                                                                    x-text="category"></h4>
                                                                <div class="grid grid-cols-7 sm:grid-cols-8 gap-1">
                                                                    <template x-for="emoji in list" :key="emoji">
                                                                        <button type="button" @click="insertEmoji(emoji)"
                                                                            class="flex items-center justify-center p-1.5 text-xl hover:bg-slate-100 rounded-lg transition-colors transform hover:scale-110 active:scale-90">
                                                                            <span x-text="emoji"></span>
                                                                        </button>
                                                                    </template>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="submit"
                                            class="inline-flex items-center justify-center rounded-full bg-blue-600 p-2.5 text-white hover:bg-blue-500 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-1 active:scale-95"
                                            title="ส่งข้อความ">
                                            <svg viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current"
                                                stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    @else
                        <div class="w-full py-3 text-center text-gray-500 bg-gray-50 rounded-xl flex items-center justify-center gap-2 border border-gray-100"
                            id="lockedNotice">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            <span class="text-[13px] font-medium">กระทู้นี้ถูกล็อก ไม่สามารถส่งข้อความใหม่ได้</span>
                        </div>
                    @endif
                </div>
            @else
                <div class="absolute inset-0 flex items-center justify-center bg-slate-50">
                    <x-ui.empty-state icon="forum" hint="คลิกเลือกหัวข้อทางด้านซ้ายเพื่อเปิดอ่าน หรือสร้างกระทู้ใหม่">ยินดีต้อนรับสู่กระดานสนทนา</x-ui.empty-state>
                </div>
            @endif
        </div>

        {{-- Form for actually submitting the new thread --}}
        <form id="hidden-create-thread" method="POST" action="{{ route('chat.store') }}" class="hidden">
            @csrf
            <input type="hidden" name="title" id="final-thread-title">
        </form>

        @if (isset($activeThread))
            {{-- Form for actually locking/unlocking the thread --}}
            <form id="hidden-lock-thread" method="POST"
                action="{{ $activeThread->is_locked ? route('chat.unlock', $activeThread) : route('chat.lock', $activeThread) }}"
                class="hidden">
                @csrf
            </form>

            @if (Auth::user()->role === 'admin')
                {{-- Form for deleting the thread --}}
                <form id="hidden-delete-thread" method="POST" action="{{ route('chat.destroy', $activeThread) }}"
                    class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        @endif

        {{-- Create Thread Modal --}}
        <template x-teleport="body">
            <div x-show="showCreateModal"
                class="fixed inset-0 z-[3000] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" x-cloak style="display: none;">

                <div class="bg-white rounded-md w-full max-w-md overflow-hidden border border-slate-200"
                    @click.away="showCreateModal = false">

                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                        <h3 class="text-lg font-semibold text-slate-900 font-manrope">สร้างกระทู้ใหม่</h3>
                        <button @click="showCreateModal = false"
                            class="text-slate-400 hover:text-slate-600 transition-colors">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>

                    <div class="p-6">
                        <div class="mb-4">
                            <label for="modal-thread-title"
                                class="block text-[13px] font-medium text-slate-700 mb-2">หัวข้อกระทู้</label>
                            <input type="text" id="modal-thread-title" x-model="newThreadTitle" x-ref="titleInput"
                                @keydown.enter="submitThread()" x-init="$watch('showCreateModal', value => { if (value) { $nextTick(() => $refs.titleInput.focus()); } })"
                                placeholder="กรุณากรอกหัวข้อกระทู้..."
                                class="w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-[15px] focus:outline-none focus:ring-2 focus:ring-[#0F2D5C]/35 focus:border-[#0F2D5C]/35 transition-all" maxlength="180">
                            <p class="mt-2 text-[11px] text-slate-500 italic">*
                                หัวข้อนี้จะปรากฏให้ผู้ใช้อื่นเห็นในรายการกระทู้</p>
                        </div>
                    </div>

                    <div class="px-6 py-4 bg-slate-50 flex justify-end gap-3 border-t border-slate-100">
                        <button @click="showCreateModal = false"
                            class="px-4 py-2 text-[13px] font-bold text-slate-600 hover:text-slate-800 transition-colors">ยกเลิก</button>
                        <button @click="submitThread()"
                            class="px-6 py-2 bg-[#0F2D5C] text-white rounded-md text-[13px] font-bold hover:bg-[#0F2D5C]/90 transition-all active:scale-95">สร้างกระทู้</button>
                    </div>
                </div>
            </div>
        </template>

        @if (isset($activeThread))
            {{-- Lock/Unlock Thread Modal --}}
            <template x-teleport="body">
                <div x-show="showLockModal"
                    class="fixed inset-0 z-[3000] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                    x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" x-cloak
                    style="display: none;">

                    <div class="bg-white rounded-md w-full max-w-sm overflow-hidden border border-slate-200"
                        @click.away="showLockModal = false">

                        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                            <h3 class="text-[15px] font-semibold text-amber-700 font-manrope">ยืนยันการดำเนินการ</h3>
                            <button @click="showLockModal = false"
                                class="text-slate-400 hover:text-slate-600 transition-colors">
                                <span class="material-symbols-outlined text-[20px]">close</span>
                            </button>
                        </div>

                        <div class="p-5 text-center">
                            <div
                                class="w-14 h-14 rounded-full bg-amber-50 text-amber-600 ring-1 ring-amber-200 flex items-center justify-center mx-auto mb-3">
                                <span
                                    class="material-symbols-outlined text-3xl">{{ $activeThread->is_locked ? 'lock_open' : 'lock' }}</span>
                            </div>
                            <p class="text-[14px] text-slate-700">
                                คุณต้องการ <strong>{{ $activeThread->is_locked ? 'ปลดล็อก' : 'ล็อก' }}</strong>
                                กระทู้นี้ใช่หรือไม่?
                            </p>
                            @if (!$activeThread->is_locked)
                                <p class="text-[14px] text-slate-500 mt-2">เมื่อล็อกแล้ว
                                    ผู้ใช้อื่นจะไม่สามารถส่งข้อความใหม่ได้</p>
                            @endif
                        </div>

                        <div class="px-5 py-3 bg-slate-50 flex justify-center gap-2 border-t border-slate-100">
                            <button @click="showLockModal = false"
                                class="flex-1 px-4 py-2 text-[13px] font-semibold text-slate-600 hover:bg-slate-200 bg-slate-100 border border-slate-200 rounded-md transition-colors">ยกเลิก</button>
                            <button @click="submitLock()"
                                class="flex-1 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-md text-[13px] font-semibold transition-all focus:outline-none active:scale-95">ยืนยัน</button>
                        </div>
                    </div>
                </div>
            </template>

            @if (Auth::user()->role === 'admin')
                {{-- Delete Thread Modal --}}
                <template x-teleport="body">
                    <div x-show="showDeleteModal"
                        class="fixed inset-0 z-[3000] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" x-cloak
                        style="display: none;">

                        <div class="bg-white rounded-md w-full max-w-sm overflow-hidden border border-slate-200"
                            @click.away="showDeleteModal = false">

                            <div
                                class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                                <h3 class="text-[15px] font-semibold text-red-600 font-manrope">ยืนยันการลบกระทู้</h3>
                                <button @click="showDeleteModal = false"
                                    class="text-slate-400 hover:text-slate-600 transition-colors">
                                    <span class="material-symbols-outlined text-[20px]">close</span>
                                </button>
                            </div>

                            <div class="p-5 text-center">
                                <div
                                    class="w-14 h-14 rounded-full bg-red-50 text-red-500 flex items-center justify-center mx-auto mb-3">
                                    <span class="material-symbols-outlined text-3xl">delete_forever</span>
                                </div>
                                <p class="text-[14px] text-slate-700">
                                    คุณต้องการ <strong>ลบ</strong> กระทู้นี้ออกจากระบบใช่หรือไม่?
                                </p>
                                <p class="text-[13px] text-red-500 mt-2 font-medium">กระทู้และข้อความทั้งหมดจะถูกซ่อนทันที
                                </p>
                            </div>

                            <div class="px-5 py-3 bg-slate-50 flex justify-center gap-2 border-t border-slate-100">
                                <button @click="showDeleteModal = false"
                                    class="flex-1 px-4 py-2 text-[13px] font-semibold text-slate-600 hover:bg-slate-200 bg-slate-100 border border-slate-200 rounded-md transition-colors">ยกเลิก</button>
                                <button @click="submitDelete()"
                                    class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md text-[13px] font-semibold transition-all focus:outline-none active:scale-95">ลบทิ้ง</button>
                            </div>
                        </div>
                    </div>
                </template>
            @endif
        @endif
    </div>
@endsection

@section('scripts')
    @vite(['resources/js/chat/boot.js'])
@endsection

@section('after-content')
    <div id="loaderOverlay" class="loader-overlay">
        <div class="loader-spinner"></div>
    </div>

    <style>
        .loader-overlay {
            position: fixed;
            inset: 0;
            background: rgba(255, 255, 255, .6);
            backdrop-filter: blur(2px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 99999;
            visibility: hidden;
            opacity: 0;
            transition: opacity .2s, visibility .2s
        }

        .loader-overlay.show {
            visibility: visible;
            opacity: 1
        }

        .loader-spinner {
            width: 36px;
            height: 36px;
            border: 3.5px solid #0F2D5C;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin .7s linear infinite
        }

        @keyframes spin {
            to {
                transform: rotate(360deg)
            }
        }

        .animate-bubble-in {
            transition: all 300ms cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        /* Send Button Hover Animation - Scoped to avoid affecting sidebar */
        #chatForm .group:hover svg {
            animation: plane-ready 1.5s ease-in-out infinite;
        }

        @keyframes plane-ready {

            0%,
            100% {
                transform: translate(2px, -2px) rotate(12deg);
            }

            50% {
                transform: translate(3px, -3px) rotate(15deg);
            }
        }

        html,
        body {
            height: 100%;
            overflow: hidden !important;
        }

        #main.content {
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
            padding: 0 !important;
            padding-top: var(--topbar-h) !important;
        }
    </style>
@endsection

@auth
    <div id="chatWidgetRoot" class="fixed z-50 right-4 bottom-4 sm:right-6 sm:bottom-6"
        {{-- read by resources/js/layout/chat-fab.js --}}
        data-updates-url="{{ route('chat.my_updates') }}" data-notify-icon="{{ asset('images/logoppk.png') }}">

        {{-- FAB ปุ่มกลมลอย --}}
        <button id="chatFab"
            class="relative grid h-14 w-14 place-items-center rounded-full bg-[#0E2B51] text-white ring-4 ring-[#0E2B51]/10 hover:brightness-110 focus:outline-none focus:ring-4 focus:ring-[#0E2B51]/30 transition-transform active:scale-95"
            aria-label="เปิดรายการกระทู้ที่มีส่วนร่วม" title="กระทู้ที่มีส่วนร่วม">
            <div class="animate-bounce-slow">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M4 5a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v9a3 3 0 0 1-3 3H9.83l-3.9 3.9A1 1 0 0 1 4 20.9V5z" />
                </svg>
            </div>
            {{-- a fixed height equal to the min-width, and flex centring: without a height, text-[11px] with no line-height
                 gave the span a taller line box than its 20px width, so `rounded-full` drew an oval, not a circle. `font-sans`
                 (not the page's default Sarabun) because Sarabun's ascent is unusually tall — headroom for Thai marks
                 stacked above a vowel — so even at leading-none a plain digit's ink sits low in the line box; the badge
                 never shows Thai, so a normal-metrics font keeps a numeral centred instead. A digit still optically sits a
                 little low even in a normal font (its ink is baseline-up, so the em-box's centre sits a touch above the
                 ink's own centre): `pb-0.5` eats 2px off the bottom of the box only (height stays a fixed 20px — Tailwind's
                 preflight makes every box border-box — so the circle itself doesn't move), nudging the centred content up
                 about 1px without moving the circle around it. --}}
            <span id="chatBadge"
                class="absolute -top-1 -right-1 hidden h-5 min-w-5 inline-flex items-center justify-center rounded-full bg-rose-500 px-1 pb-0.5 font-sans text-[11px] font-semibold leading-none text-white">
            </span>
        </button>

        {{-- Drawer รายการกระทู้ --}}
        <div id="chatDrawer"
            class="pointer-events-none fixed right-4 bottom-24 sm:bottom-28 sm:right-6 w-[92vw] max-w-[420px] translate-y-4 opacity-0 transition-all duration-200
              rounded-md border border-zinc-200 bg-white ">
            <div class="pointer-events-auto flex max-h-[70vh] flex-col">

                {{-- Header --}}
                <div class="flex items-center gap-2 border-b px-4 py-3">
                    <img src="{{ auth()->user()->avatar_thumb_url }}"
                        class="h-8 w-8 rounded-full object-cover border border-zinc-200" alt="รูปโปรไฟล์">
                    <div class="mr-auto min-w-0">
                        <div class="truncate font-medium">กระทู้ที่มีส่วนร่วม</div>
                        <div class="text-xs text-zinc-500">ที่คุณตั้งหรือเคยตอบ</div>
                    </div>
                    {{-- Desktop notifications are asked for HERE, when the person chooses (a browser that is asked on its own, on every page,
                         mostly ends up refusing for good). Shown only while the browser has not been asked yet. --}}
                    <button id="chatNotifyAsk" type="button" class="hidden rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100"
                        title="เปิดการแจ้งเตือนบนเดสก์ท็อป" aria-label="เปิดการแจ้งเตือนบนเดสก์ท็อป">
                        <span class="material-symbols-outlined text-[20px] leading-none" aria-hidden="true">notifications</span>
                    </button>
                    <button id="chatClose" class="rounded-md p-1.5 text-zinc-500 hover:bg-zinc-100" aria-label="ปิด">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                            <path
                                d="M18.3 5.7a1 1 0 0 0-1.4-1.4L12 9.17 7.1 4.3a1 1 0 1 0-1.4 1.4L10.83 12l-5.13 4.9a1 1 0 1 0 1.4 1.4L12 14.83l4.9 5.13a1 1 0 0 0 1.4-1.4L13.17 12l5.13-4.9Z" />
                        </svg>
                    </button>
                </div>

                {{-- Search --}}
                <div class="px-4 py-2 border-b">
                    <input id="chatSearch" type="search" placeholder="ค้นหาหัวข้อของฉัน..."
                        class="w-full rounded-md border border-zinc-200 px-3 py-2 text-[14px] focus:outline-none focus:ring-2 focus:ring-[#0E2B51]/10 ">
                </div>

                {{-- List --}}
                <div id="chatList" class="overflow-y-auto p-2 space-y-1">
                    {{-- แทรกรายการด้วย JS --}}
                </div>

                {{-- Footer --}}
                <div class="border-t px-3 py-2 text-right">
                    <a href="{{ route('chat.index') }}" data-no-loader class="text-[13px] text-[#0E2B51] hover:underline">ไปที่กระทู้ทั้งหมด</a>
                </div>
            </div>
        </div>

        {{-- Notification Sound --}}
        <audio id="chatNotifySound" preload="none">
            <source src="https://assets.mixkit.co/active_storage/sfx/2358/2358-preview.mp3" type="audio/mpeg">
        </audio>
    </div>
@endauth

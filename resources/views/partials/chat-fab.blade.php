@auth
    <div id="chatWidgetRoot" class="fixed z-50 right-4 bottom-4 sm:right-6 sm:bottom-6">

        {{-- FAB ปุ่มกลมลอย --}}
        <button id="chatFab"
            class="relative grid h-14 w-14 place-items-center rounded-full bg-[#0E2B51] text-white ring-4 ring-[#0E2B51]/10 hover:brightness-110 focus:outline-none focus:ring-4 focus:ring-[#0E2B51]/30 transition-transform active:scale-95"
            aria-label="เปิดรายการกระทู้ของฉัน" title="กระทู้ของฉัน">
            <div class="animate-bounce-slow">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M4 5a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v9a3 3 0 0 1-3 3H9.83l-3.9 3.9A1 1 0 0 1 4 20.9V5z" />
                </svg>
            </div>
            <span id="chatBadge"
                class="absolute -top-1 -right-1 hidden min-w-5 rounded-full bg-rose-500 px-1.5 text-center text-[11px] font-semibold text-white">
            </span>
        </button>

        {{-- Drawer รายการกระทู้ --}}
        <div id="chatDrawer"
            class="pointer-events-none fixed right-4 bottom-24 sm:bottom-28 sm:right-6 w-[92vw] max-w-[420px] translate-y-4 opacity-0 transition-all duration-200
              rounded-2xl border border-zinc-200 bg-white ">
            <div class="pointer-events-auto flex max-h-[70vh] flex-col">

                {{-- Header --}}
                <div class="flex items-center gap-2 border-b px-4 py-3">
                    <img src="{{ auth()->user()->avatar_thumb_url }}"
                        class="h-8 w-8 rounded-full object-cover border border-zinc-200" alt="Avatar">
                    <div class="mr-auto min-w-0">
                        <div class="truncate font-medium">My Topics</div>
                        <div class="text-xs text-zinc-500">จากกระทู้ที่คุณมีส่วนร่วม</div>
                    </div>
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
                    <a href="{{ route('chat.index') }}" data-no-loader class="text-[13px] text-[#0E2B51] hover:underline">Go
                        All topics</a>
                </div>
            </div>
        </div>

        {{-- Notification Sound --}}
        <audio id="chatNotifySound" preload="auto">
            <source src="https://assets.mixkit.co/active_storage/sfx/2358/2358-preview.mp3" type="audio/mpeg">
        </audio>
    </div>

    <script>
        (() => {
            const fab = document.getElementById('chatFab');
            const drawer = document.getElementById('chatDrawer');
            const closeBt = document.getElementById('chatClose');
            const badge = document.getElementById('chatBadge');
            const listEl = document.getElementById('chatList');
            const search = document.getElementById('chatSearch');

            let isOpen = false;
            let unreadTotal = 0; // ตัวเลขบนป้าย (เคลียร์เป็น 0 ตอนเปิด drawer)
            let allItems = []; // เก็บ items ทั้งหมดสำหรับ filter
            let firstLoaded = false;

            // Baseline สำหรับ "มีข้อความใหม่จริงไหม" — ค่า unread รวมล่าสุดที่ server รายงาน
            // เก็บไว้ต่อแท็บ (sessionStorage) เพื่อไม่ให้การโหลดหน้าใหม่ (Turbo) หรือการเปิด drawer
            // ถูกมองเป็น "ข้อความใหม่" แล้วส่งเสียง/แจ้งเตือนทุกครั้งที่คลิก
            const BASELINE_KEY = 'chatFab.serverUnread';
            let serverUnread = null; // null = ยังไม่เคยโพลในแท็บนี้ → ครั้งแรกไม่ส่งเสียง
            try {
                const saved = sessionStorage.getItem(BASELINE_KEY);
                if (saved !== null) serverUnread = Number(saved);
            } catch (e) {}

            function openDrawer() {
                isOpen = true;
                drawer.removeAttribute('inert');
                drawer.setAttribute('aria-hidden', 'false');
                drawer.classList.remove('translate-y-4', 'opacity-0', 'pointer-events-none');
                drawer.classList.add('translate-y-0', 'opacity-100');
                unreadTotal = 0;
                renderBadge();
            }

            function closeDrawer() {
                isOpen = false;
                drawer.setAttribute('inert', '');
                drawer.setAttribute('aria-hidden', 'true');
                drawer.classList.add('translate-y-4', 'opacity-0', 'pointer-events-none');
                drawer.classList.remove('translate-y-0', 'opacity-100');
            }

            function toggleDrawer() {
                isOpen ? closeDrawer() : openDrawer();
            }

            function renderBadge() {
                if (unreadTotal > 0) {
                    badge.textContent = unreadTotal > 99 ? '99+' : String(unreadTotal);
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                    badge.textContent = '';
                }
            }

            function fmtTime(iso) {
                if (!iso) return '';
                try {
                    return new Date(iso).toLocaleString();
                } catch {
                    return '';
                }
            }

            function renderEmpty() {
                listEl.innerHTML = `
      <div class="px-3 py-5 text-center text-sm text-zinc-500">
        ยังไม่มีกระทู้ที่คุณมีส่วนร่วม<br>
        <span class="text-[12px] text-zinc-400">
          เริ่มต้นสร้างกระทู้หรือคอมเมนต์ในห้องแชต แล้วรายการจะมาปรากฏที่นี่
        </span>
      </div>`;
            }

            function renderList(items) {
                listEl.innerHTML = '';

                if (!items.length) {
                    renderEmpty();
                    return;
                }

                // Limit display to 10 items
                const displayItems = items.slice(0, 10);

                for (const it of displayItems) {
                    const a = document.createElement('a');
                    a.href = it.show_url;
                    a.setAttribute('data-no-loader', '');
                    a.className = 'group flex items-start gap-3 rounded-xl px-3 py-2 hover:bg-zinc-50';

                    const hasUnread = (it.unread || 0) > 0;

                    a.innerHTML = `
        <div class="mt-0.5 h-8 w-8 shrink-0 rounded-full overflow-hidden bg-slate-100 border border-zinc-100 flex items-center justify-center">
          ${
            it.last_user_avatar
              ? `<img src="${it.last_user_avatar}" class="h-full w-full object-cover" alt="">`
              : `<span class="text-xs font-bold text-slate-500">${ (it.title || '?').slice(0,1).toUpperCase() }</span>`
          }
        </div>
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <div class="truncate font-medium text-[14px]">
              ${ it.title || 'Untitled' }
            </div>
            ${
              hasUnread
                ? `<span class="ml-auto inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 ring-1 ring-emerald-200">
                         ใหม่ ${ it.unread > 99 ? '99+' : it.unread }
                       </span>`
                : ''
            }
          </div>
          <div class="mt-0.5 text-[12px] text-zinc-500 truncate">
            ${ it.last_user_name ? `<span class="font-medium text-zinc-700">${ it.last_user_name}</span>: ` : '' }
            ${ (it.last_body || '').replace(/\s+/g, ' ').slice(0, 120) }
          </div>
          <div class="mt-0.5 text-[11px] text-zinc-400">${ fmtTime(it.last_created_at) }</div>
        </div>
      `;
                    listEl.appendChild(a);
                }
            }

            function applyFilter() {
                const q = (search.value || '').toLowerCase().trim();
                if (!q) return renderList(allItems);
                const filtered = allItems.filter(it =>
                    (it.title || '').toLowerCase().includes(q) ||
                    (it.last_body || '').toLowerCase().includes(q) ||
                    (it.last_user_name || '').toLowerCase().includes(q)
                );
                renderList(filtered);
            }

            async function poll() {
                try {
                    if (!firstLoaded) {
                        listEl.innerHTML = `
          <div class="px-3 py-4 text-sm text-zinc-500">
            กำลังโหลดกระทู้ที่คุณมีส่วนร่วม...
          </div>`;
                    }

                    const res = await fetch(`{{ url('/chat/my-updates') }}`, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    if (!res.ok) {
                        console.error('chat.my_updates error', res.status);
                        if (!firstLoaded) {
                            listEl.innerHTML = `
            <div class="px-3 py-5 text-center text-sm text-rose-500">
              โหลดข้อมูลไม่สำเร็จ (${res.status})<br>
              <span class="text-[12px] text-zinc-400">
                ลองรีเฟรชหน้าหรือเข้าสู่ระบบใหม่อีกครั้ง
              </span>
            </div>`;
                        }
                        return;
                    }

                    const data = await res.json();
                    if (!Array.isArray(data)) {
                        console.error('chat.my_updates expected array but got', data);
                        if (!firstLoaded) renderEmpty();
                        return;
                    }

                    firstLoaded = true;

                    allItems = data;
                    renderList(allItems);

                    const sumUnread = data.reduce((n, x) => n + (x.unread || 0), 0);

                    // Notification Logic — เฉพาะเมื่อ unread ที่ server รายงาน "เพิ่มขึ้น" จริง
                    // เทียบกับโพลก่อนหน้า (ไม่ใช่ตอนโหลดหน้า / ตอนเปิด drawer)
                    if (serverUnread !== null && sumUnread > serverUnread) {
                        // 1. Play Sound (if enabled in global settings)
                        const soundEnabled = localStorage.getItem('myjobs.notify.sound.enabled') === '1';
                        if (soundEnabled) {
                            const audio = document.getElementById('chatNotifySound');
                            if (audio) {
                                audio.currentTime = 0;
                                audio.play().catch(e => console.warn('Chat sound blocked:', e));
                            }
                        }

                        // 2. Browser Notification
                        if (!isOpen && document.hidden && Notification.permission === 'granted') {
                            const lastItem = data.find(it => it.unread > 0);
                            new Notification('ข้อความใหม่จาก Live Chat', {
                                body: lastItem ? `${lastItem.last_user_name}: ${lastItem.last_body}` :
                                    'คุณมีข้อความใหม่ที่ยังไม่ได้อ่าน',
                                icon: '{{ asset('images/logoppk.png') }}'
                            });
                        }
                    }

                    serverUnread = sumUnread;
                    try { sessionStorage.setItem(BASELINE_KEY, String(sumUnread)); } catch (e) {}

                    unreadTotal = sumUnread;
                    renderBadge();
                } catch (e) {
                    console.error('chat.my_updates exception', e);
                    if (!firstLoaded) {
                        listEl.innerHTML = `
          <div class="px-3 py-5 text-center text-sm text-rose-500">
            เกิดข้อผิดพลาดในการเชื่อมต่อเครือข่าย<br>
            <span class="text-[12px] text-zinc-400">กรุณาลองใหม่อีกครั้ง</span>
          </div>`;
                    }
                }
            }

            fab.addEventListener('click', toggleDrawer);
            closeBt.addEventListener('click', closeDrawer);
            search.addEventListener('input', applyFilter);

            // init
            closeDrawer();

            // Request Notification Permission
            if (Notification.permission === 'default') {
                Notification.requestPermission();
            }

            poll();
            setInterval(poll, 5000);
        })
        ();
    </script>
@endauth

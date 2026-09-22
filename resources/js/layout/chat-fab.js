// The floating chat button and its "My Topics" drawer (partials/chat-fab.blade.php, on every page of the app layout).
// It used to be an inline <script> that Turbo Drive re-ran on every visit; each run started ANOTHER `setInterval`, so after
// N page visits the browser polled `/chat/my-updates` N times every interval. Now the timer is created once, and each page
// load only wires the freshly rendered button, drawer and list.
//
// What the page passes in, as data attributes of #chatWidgetRoot:
//   data-updates-url   the endpoint to poll (route chat.my_updates)
//   data-notify-icon   the icon of the desktop notification
//
// The list is built with DOM nodes and `textContent`: thread titles, senders and message text are typed by other users,
// so none of it may reach `innerHTML` (the inline script did exactly that).

export const POLL_MS = 30000;               // how often the widget asks the server for new messages
const BASELINE_KEY = 'chatFab.serverUnread'; // the last unread total the server reported, per tab (sessionStorage)
const SOUND_KEY = 'myjobs.notify.sound.enabled';

const LOADING_HTML = '<div class="px-3 py-4 text-sm text-zinc-500">กำลังโหลดกระทู้ที่คุณมีส่วนร่วม...</div>';
const EMPTY_HTML = `
      <div class="px-3 py-5 text-center text-sm text-zinc-500">
        ยังไม่มีกระทู้ที่คุณมีส่วนร่วม<br>
        <span class="text-[12px] text-zinc-400">
          เริ่มต้นสร้างกระทู้หรือคอมเมนต์ในห้องแชต แล้วรายการจะมาปรากฏที่นี่
        </span>
      </div>`;
const failedHtml = (status) => `
            <div class="px-3 py-5 text-center text-sm text-rose-500">
              โหลดข้อมูลไม่สำเร็จ (${Number(status)})<br>
              <span class="text-[12px] text-zinc-400">
                ลองรีเฟรชหน้าหรือเข้าสู่ระบบใหม่อีกครั้ง
              </span>
            </div>`;
const OFFLINE_HTML = `
          <div class="px-3 py-5 text-center text-sm text-rose-500">
            เกิดข้อผิดพลาดในการเชื่อมต่อเครือข่าย<br>
            <span class="text-[12px] text-zinc-400">กรุณาลองใหม่อีกครั้ง</span>
          </div>`;

function fmtTime(iso) {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleString();
    } catch {
        return '';
    }
}

function el(doc, tag, className, text) {
    const node = doc.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}

/** One row of the drawer. Every piece of text that came from the server is set as text. */
export function buildItem(doc, it) {
    const a = el(doc, 'a', 'group flex items-start gap-3 rounded-xl px-3 py-2 hover:bg-zinc-50');
    a.href = it.show_url;
    a.setAttribute('data-no-loader', '');

    const avatar = el(doc, 'div', 'mt-0.5 h-8 w-8 shrink-0 rounded-full overflow-hidden bg-slate-100 border border-zinc-100 flex items-center justify-center');
    if (it.last_user_avatar) {
        const img = el(doc, 'img', 'h-full w-full object-cover');
        img.src = it.last_user_avatar;
        img.alt = '';
        avatar.append(img);
    } else {
        avatar.append(el(doc, 'span', 'text-xs font-bold text-slate-500', (it.title || '?').slice(0, 1).toUpperCase()));
    }

    const top = el(doc, 'div', 'flex items-center gap-2');
    top.append(el(doc, 'div', 'truncate font-medium text-[14px]', it.title || 'Untitled'));
    if ((it.unread || 0) > 0) {
        // just blue text — no pill, no border: a box around every unread row was too loud for a list where most rows have
        // one; blue (the system's "info" tone) so it reads as "new", not as a warning the way the FAB's own red dot does
        top.append(el(doc, 'span', 'ml-auto shrink-0 text-[11px] font-semibold text-blue-600',
            `ใหม่ ${it.unread > 99 ? '99+' : it.unread}`));
    }

    const last = el(doc, 'div', 'mt-0.5 text-[12px] text-zinc-500 truncate');
    if (it.last_user_name) last.append(el(doc, 'span', 'font-medium text-zinc-700', it.last_user_name), ': ');
    last.append((it.last_body || '').replace(/\s+/g, ' ').slice(0, 120));

    const body = el(doc, 'div', 'min-w-0 flex-1');
    body.append(top, last, el(doc, 'div', 'mt-0.5 text-[11px] text-zinc-400', fmtTime(it.last_created_at)));

    a.append(avatar, body);
    return a;
}

const INSTALLED = Symbol.for('ppk.chatFab.installed');

/** Call once. Wires the widget on every page load and polls on one timer for the whole session. */
export function installChatFab(win = window) {
    if (win[INSTALLED]) return win[INSTALLED];

    const doc = win.document;
    let page = null;        // the widget of the page on screen (its elements are replaced by every Turbo visit)
    let timer = null;
    let serverUnread = null; // null = never polled in this tab, so the first poll does not ring

    try {
        const saved = win.sessionStorage.getItem(BASELINE_KEY);
        if (saved !== null) serverUnread = Number(saved);
    } catch { /* storage blocked */ }

    function wire(root) {
        const fab = doc.getElementById('chatFab');
        const drawer = doc.getElementById('chatDrawer');
        const closeBt = doc.getElementById('chatClose');
        const badge = doc.getElementById('chatBadge');
        const listEl = doc.getElementById('chatList');
        const search = doc.getElementById('chatSearch');
        if (!fab || !drawer || !closeBt || !badge || !listEl || !search) return null;

        const p = { root, isOpen: false, unreadTotal: 0, allItems: [], firstLoaded: false };

        const renderBadge = () => {
            if (p.unreadTotal > 0) {
                badge.textContent = p.unreadTotal > 99 ? '99+' : String(p.unreadTotal);
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
                badge.textContent = '';
            }
        };

        const openDrawer = () => {
            p.isOpen = true;
            drawer.removeAttribute('inert');
            drawer.setAttribute('aria-hidden', 'false');
            drawer.classList.remove('translate-y-4', 'opacity-0', 'pointer-events-none');
            drawer.classList.add('translate-y-0', 'opacity-100');
            p.unreadTotal = 0;
            renderBadge();
        };

        const closeDrawer = () => {
            p.isOpen = false;
            drawer.setAttribute('inert', '');
            drawer.setAttribute('aria-hidden', 'true');
            drawer.classList.add('translate-y-4', 'opacity-0', 'pointer-events-none');
            drawer.classList.remove('translate-y-0', 'opacity-100');
        };

        p.renderEmpty = () => { listEl.innerHTML = EMPTY_HTML; };
        p.renderList = (items) => {
            listEl.innerHTML = '';
            if (!items.length) { p.renderEmpty(); return; }
            for (const it of items.slice(0, 10)) listEl.appendChild(buildItem(doc, it)); // at most 10 rows
        };
        p.applyFilter = () => {
            const q = (search.value || '').toLowerCase().trim();
            if (!q) return p.renderList(p.allItems);
            p.renderList(p.allItems.filter((it) =>
                (it.title || '').toLowerCase().includes(q) ||
                (it.last_body || '').toLowerCase().includes(q) ||
                (it.last_user_name || '').toLowerCase().includes(q)));
        };
        p.renderBadge = renderBadge;
        p.setListHtml = (html) => { listEl.innerHTML = html; };

        fab.addEventListener('click', () => (p.isOpen ? closeDrawer() : openDrawer()));
        closeBt.addEventListener('click', closeDrawer);
        search.addEventListener('input', p.applyFilter);
        closeDrawer();

        return p;
    }

    function notify(data) {
        // 1. sound, if the user turned it on in the top bar
        let soundOn = false;
        try { soundOn = win.localStorage.getItem(SOUND_KEY) === '1'; } catch { /* storage blocked */ }
        if (soundOn) {
            const audio = doc.getElementById('chatNotifySound');
            if (audio) {
                audio.currentTime = 0;
                audio.play().catch((e) => win.console?.warn('Chat sound blocked:', e));
            }
        }

        // 2. desktop notification, only while the tab is in the background and the drawer is closed
        const N = win.Notification;
        if (page && !page.isOpen && doc.hidden && N && N.permission === 'granted') {
            const lastItem = data.find((it) => it.unread > 0);
            new N('ข้อความใหม่จาก Live Chat', {
                body: lastItem ? `${lastItem.last_user_name}: ${lastItem.last_body}` : 'คุณมีข้อความใหม่ที่ยังไม่ได้อ่าน',
                icon: page.root.dataset.notifyIcon,
            });
        }
    }

    async function poll() {
        const p = page;
        if (!p) return; // no widget on this page (login …)

        try {
            if (!p.firstLoaded) p.setListHtml(LOADING_HTML);

            const res = await win.fetch(p.root.dataset.updatesUrl, { headers: { Accept: 'application/json' } });

            if (!res.ok) {
                win.console?.error('chat.my_updates error', res.status);
                if (!p.firstLoaded) p.setListHtml(failedHtml(res.status));
                return;
            }

            const data = await res.json();
            if (!Array.isArray(data)) {
                win.console?.error('chat.my_updates expected array but got', data);
                if (!p.firstLoaded) p.renderEmpty();
                return;
            }

            p.firstLoaded = true;
            p.allItems = data;
            p.renderList(data);

            const sumUnread = data.reduce((n, x) => n + (x.unread || 0), 0);

            // ring only when the unread total the server reports really went UP since the last poll — not on a page
            // load or when the drawer opens (the baseline survives Turbo visits in sessionStorage)
            if (serverUnread !== null && sumUnread > serverUnread) notify(data);

            serverUnread = sumUnread;
            try { win.sessionStorage.setItem(BASELINE_KEY, String(sumUnread)); } catch { /* storage blocked */ }

            p.unreadTotal = sumUnread;
            p.renderBadge();
        } catch (e) {
            win.console?.error('chat.my_updates exception', e);
            if (!p.firstLoaded) p.setListHtml(OFFLINE_HTML);
        }
    }

    // A page was rendered: wire its widget (once per rendered widget) and load the list.
    function pageLoaded() {
        const root = doc.getElementById('chatWidgetRoot');
        if (!root) { page = null; return; }
        if (page && page.root === root) return; // turbo:load / DOMContentLoaded / the immediate call all land here
        page = wire(root);
        if (!page) return;

        const N = win.Notification;
        if (N && N.permission === 'default') N.requestPermission();

        poll();
        if (timer === null) timer = win.setInterval(poll, POLL_MS); // one timer for the whole session
    }

    doc.addEventListener('turbo:load', pageLoaded);
    doc.addEventListener('DOMContentLoaded', pageLoaded);

    const handle = (win[INSTALLED] = { pageLoaded, poll });
    if (doc.readyState !== 'loading') pageLoaded();
    return handle;
}

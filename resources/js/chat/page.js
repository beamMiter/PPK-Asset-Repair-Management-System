// The live chat page (chat/index.blade.php): keeps the open thread up to date — realtime through Echo, a polling fallback,
// appending new messages, the composer (auto-height, Enter to send) and the refresh button.
//
// It used to be an inline <script> that only started on `DOMContentLoaded` and on Livewire's `livewire:navigated`. Neither
// fires on a Turbo visit, so opening the chat from the menu left the page without live updates until a full reload; and
// nothing ever stopped the poll timer, the Echo channel or the Pusher `state_change` handler when you left the page, so they
// kept running (and, for the handler, piled up) on every other page.
//
// `installChatPage()` registers its document listeners once. Each page load mounts the thread on screen (if any), and the
// page it replaces is torn down first (`turbo:before-render`): timer stopped, channel left, handler unbound.
//
// The page hands over everything through `#chatBox` data attributes (thread id, my id, last message id, messages URL).
// Messages that arrive live are built from DOM nodes: a sender's name is chosen by the sender (profile page), so it must never
// go through `innerHTML`.

import { initialsAvatarUrl } from '../avatar.js';
import { chatTime } from './time.js';

export const POLL_MS = 5000;               // polling fallback, alongside the realtime channel
export const STATUS_TIMEOUT_MS = 10000;    // still "connecting" after this long → show the polling (offline) state


function el(doc, tag, className, text) {
    const node = doc.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}

const DELETED_TEXT = 'ข้อความนี้ถูกลบ';
const DELETE_BUTTON = 'chat-msg-delete shrink-0 rounded-full p-1 text-gray-300 hover:bg-red-50 hover:text-red-500 focus:outline-none focus:ring-2 focus:ring-red-200';

/** the little bin beside a bubble; one click handler on the message list serves every row (see mount) */
function deleteButton(doc) {
    const btn = el(doc, 'button', DELETE_BUTTON);
    btn.type = 'button';
    btn.setAttribute('title', 'ลบข้อความนี้');
    btn.setAttribute('aria-label', 'ลบข้อความนี้');
    const icon = el(doc, 'span', 'material-symbols-outlined text-[16px] leading-none', 'delete');
    icon.setAttribute('aria-hidden', 'true');
    btn.append(icon);
    return btn;
}

/** A bubble for a message that is not there any more: "ข้อความนี้ถูกลบ", muted, whoever wrote it. */
function deletedBubble(doc, tail) {
    const bubble = el(doc, 'div', `rounded-2xl ${tail} border border-gray-100 bg-gray-50 py-2.5 px-4 text-[14px] italic text-gray-400`);
    bubble.append(el(doc, 'div', 'msg-body', DELETED_TEXT));
    return bubble;
}

/**
 * A message row, as chat/_message.blade.php renders it, for a message that arrived while the page was open.
 * m: { id, user_id, body, deleted?, created_at, user }.  canDelete: this person may delete this one.
 */
export function buildMessageRow(doc, m, { isMe, isConsecutive, timeStr, canDelete = false }) {
    const gap = isConsecutive ? 'mt-1' : 'mt-4';
    const row = el(doc, 'div');
    row.dataset.userId = m.user_id;
    row.setAttribute('data-message-id', String(m.id));        // attributes, not dataset: the delete handler finds a row by this selector
    if (m.deleted) row.setAttribute('data-deleted', '1');
    const showDelete = canDelete && !m.deleted;

    const body = el(doc, 'div', 'whitespace-pre-line break-words msg-body', m.body);

    if (isMe) {
        row.className = `chat-msg-row flex flex-col items-end w-full animate-bubble-in opacity-0 translate-y-2 ${gap}`;
        const head = el(doc, 'div', 'flex items-center gap-2 mb-1');
        head.append(el(doc, 'span', 'text-xs text-gray-500', timeStr), el(doc, 'span', 'text-[13px] font-semibold text-gray-900', 'คุณ'));
        const line = el(doc, 'div', 'flex items-center justify-end gap-1 max-w-[85%] sm:max-w-[70%]');
        if (showDelete) line.append(deleteButton(doc));
        if (m.deleted) {
            line.append(deletedBubble(doc, 'rounded-tr-none'));
        } else {
            const bubble = el(doc, 'div', 'bg-blue-600 text-white rounded-2xl rounded-tr-none py-2.5 px-4 text-[15px] leading-relaxed');
            bubble.append(body);
            line.append(bubble);
        }
        row.append(head, line);
        return row;
    }

    row.className = `chat-msg-row flex items-start gap-3 w-full animate-bubble-in opacity-0 translate-y-2 ${gap}`;

    let avatar;
    if (isConsecutive) {
        avatar = el(doc, 'div', 'relative shrink-0 opacity-0 h-0 pointer-events-none w-10');
    } else {
        avatar = el(doc, 'div', 'relative shrink-0');
        const img = el(doc, 'img', 'h-10 w-10 rounded-full object-cover border border-gray-200');
        img.src = m.user?.avatar_thumb_url || initialsAvatarUrl(m.user?.name);
        img.alt = 'รูปผู้ใช้';
        avatar.append(img);
    }

    const column = el(doc, 'div', 'flex flex-col items-start min-w-0 max-w-[85%] sm:max-w-[70%]');
    if (!isConsecutive) {
        const head = el(doc, 'div', 'flex items-center gap-2 mb-1');
        head.append(el(doc, 'span', 'text-[13px] font-semibold text-gray-900', m.user?.name || 'ไม่ทราบผู้ใช้งาน'), el(doc, 'span', 'text-xs text-gray-500', timeStr));
        column.append(head);
    }
    const line = el(doc, 'div', 'flex items-center gap-1');
    if (m.deleted) {
        line.append(deletedBubble(doc, !isConsecutive ? 'rounded-tl-none' : ''));
    } else {
        const bubble = el(doc, 'div', `bg-gray-50 border border-gray-100/80 text-gray-900 rounded-2xl ${!isConsecutive ? 'rounded-tl-none' : ''} py-2.5 px-4 text-[15px] leading-relaxed`);
        bubble.append(body);
        line.append(bubble);
    }
    if (showDelete) line.append(deleteButton(doc));
    column.append(line);

    row.append(avatar, column);
    return row;
}

/** Wire the thread on screen. Returns null when the page shows no thread (the list only). */
function mount(win) {
    const doc = win.document;
    const box = doc.getElementById('chatBox');
    if (!box) return null;

    const btnScrollBottom = doc.getElementById('btnScrollBottom');
    const msgInput = doc.getElementById('msgInput');
    const chatPane = doc.getElementById('chat-pane');

    const threadId = parseInt(box.dataset.threadId) || 0;
    const myId = parseInt(box.dataset.myId) || 0;
    let lastId = parseInt(box.dataset.lastId) || 0;
    let lastAppendedUserId = parseInt(box.dataset.lastUserId) || 0;
    const chatUrl = box.dataset.chatUrl;
    const canModerate = box.dataset.canModerate === '1';   // admins and the IT / repair team: they may delete any message
    let autoScroll = true;
    const undo = []; // everything to take back when the page is left

    box.scrollTop = box.scrollHeight;
    box.addEventListener('scroll', () => {
        autoScroll = box.scrollTop + box.clientHeight >= box.scrollHeight - 30;
        if (autoScroll && btnScrollBottom) btnScrollBottom.classList.add('hidden');
    });

    function appendMessage(m) {
        const isMe = parseInt(m.user_id) === myId;
        const isConsecutive = parseInt(m.user_id) === lastAppendedUserId;
        lastAppendedUserId = parseInt(m.user_id);
        box.dataset.lastUserId = lastAppendedUserId;

        const emptyState = doc.getElementById('emptyStateMsg');
        if (emptyState) emptyState.style.display = 'none';

        let wrapper = box.querySelector('.space-y-6');
        if (!wrapper) {
            wrapper = el(doc, 'div', 'space-y-6 pb-2');
            box.appendChild(wrapper);
        }

        // the time the SERVER stamped it (not this browser's clock), on the Thai clock; a moderator may delete any, a person their own while it is open
        const canDelete = canModerate || (isMe && !isLocked());
        const row = buildMessageRow(doc, m, { isMe, isConsecutive, timeStr: chatTime(m.created_at ?? new Date()), canDelete });
        wrapper.appendChild(row);
        win.setTimeout(() => row.classList.remove('translate-y-2', 'opacity-0'), 10);
    }

    // ── the thread's own state: locked, unlocked, deleted — heard live, or read from the poll's answer ──
    // The page's Alpine state (`locked`) drives the composer, the badges and the lock button, so a change is one assignment.
    function stop() { undo.splice(0).forEach((fn) => fn()); }
    const alpineOf = () => { try { return chatPane && win.Alpine.$data(chatPane); } catch { return null; } };
    function isLocked() { const alpine = alpineOf(); return !!(alpine && alpine.locked); }

    const setLocked = (locked) => {
        const alpine = alpineOf();
        if (!alpine || alpine.locked === locked) return;   // whoever did it was shown at once (Alpine flipped before the request)
        alpine.locked = locked;
        win.showToast?.({ type: 'info', message: locked ? 'กระทู้นี้ถูกล็อกแล้ว ไม่สามารถส่งข้อความใหม่ได้' : 'กระทู้นี้เปิดให้ส่งข้อความได้อีกครั้งแล้ว' });
    };

    let gone = false;
    const threadDeleted = () => {
        const alpine = alpineOf();
        if (gone || (alpine && alpine.deleting)) return;   // the admin who deleted it is already on the way to the list
        gone = true;
        stop();
        win.showToast?.({ type: 'warning', message: 'กระทู้นี้ถูกลบแล้ว ระบบกำลังพากลับไปที่รายการกระทู้' });
        const url = box.dataset.listUrl;
        if (url) win.setTimeout(() => (win.Turbo ? win.Turbo.visit(url) : win.location.assign(url)), 1200);
    };

    // ── deleting one message ──
    // A row keeps "ข้อความนี้ถูกลบ" in place of its words, for everybody (a broadcast tells the other pages).
    const rowOf = (id) => box.querySelector(`[data-message-id="${id}"]`);
    function markDeleted(id) {
        const row = rowOf(id);
        if (!row || row.hasAttribute('data-deleted')) return;
        row.setAttribute('data-deleted', '1');
        row.querySelectorAll('.chat-msg-delete').forEach((btn) => btn.remove());
        const body = row.querySelector('.msg-body');
        if (body) {
            body.textContent = DELETED_TEXT;
            body.className = 'msg-body';
            const bubble = body.parentElement;
            if (bubble) bubble.className = bubble.className
                .replace(/\bbg-blue-600\b|\btext-white\b|\bbg-gray-50\b|\btext-gray-900\b|\bborder-gray-100\/80\b|\btext-\[15px\]\b|\bleading-relaxed\b/g, '')
                .trim() + ' border border-gray-100 bg-gray-50 text-[14px] italic text-gray-400';
        }
        bumpCounter(-1);
    }

    box.addEventListener('click', async (e) => {
        const btn = e.target.closest?.('.chat-msg-delete');
        if (!btn) return;
        const row = btn.closest('[data-message-id]');
        if (!row || !win.confirm('ลบข้อความนี้ใช่หรือไม่? ข้อความจะหายไปจากกระทู้สำหรับทุกคน')) return;

        try {
            const messageId = row.getAttribute('data-message-id');
            const res = await win.fetch(`${chatUrl}/${messageId}`, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': doc.querySelector('meta[name=csrf-token]')?.getAttribute('content') ?? '',
                },
            });
            if (res.ok || res.status === 404) {          // 404: somebody else deleted it a moment ago
                markDeleted(messageId);
            } else if (res.status === 403) {
                win.showToast?.({ type: 'error', message: 'คุณไม่มีสิทธิ์ลบข้อความนี้ (ผู้เขียนลบได้ขณะที่กระทู้ยังไม่ล็อก และผู้ดูแลลบได้ทุกข้อความ)' });
            } else {
                win.showToast?.({ type: 'error', message: 'ลบข้อความไม่สำเร็จ กรุณาลองใหม่อีกครั้ง' });
            }
        } catch {
            win.showToast?.({ type: 'error', message: 'ลบข้อความไม่สำเร็จ กรุณาลองใหม่อีกครั้ง' });
        }
    });

    // the thread's message counter in the list on the left
    const bumpCounter = (by) => {
        const badge = doc.getElementById('thread-count-' + threadId);
        if (badge) badge.textContent = (parseInt(badge.textContent) || 0) + by;
    };

    // ── realtime ──
    if (win.Echo) {
        const conn = win.Echo.connector.pusher.connection;

        const setStatus = (status) => {
            try {
                const alpine = chatPane && win.Alpine.$data(chatPane);
                if (alpine) alpine.chatStatus = status;
            } catch (e) {
                win.console?.warn('[Chat] Alpine component not fully initialized:', e.message);
            }
        };
        const updateStatus = (state) => {
            if (state === 'connected') setStatus('online');
            else if (state === 'unavailable' || state === 'failed' || state === 'disconnected') setStatus('offline');
            else setStatus('connecting');
        };

        updateStatus(conn.state);
        const onState = (states) => updateStatus(states.current);
        conn.bind('state_change', onState);
        undo.push(() => conn.unbind('state_change', onState));

        // still "connecting" after 10 s: fall back to the polling (offline) state
        const statusTimer = win.setTimeout(() => {
            try {
                const alpine = chatPane && win.Alpine.$data(chatPane);
                if (alpine && alpine.chatStatus === 'connecting') {
                    win.console?.warn('[Chat] Connection timeout, falling back to Polling UI');
                    alpine.chatStatus = 'offline';
                }
            } catch { /* Alpine not started yet */ }
        }, STATUS_TIMEOUT_MS);
        undo.push(() => win.clearTimeout(statusTimer));

        const channel = 'chat.' + threadId;
        win.Echo.leave(channel);
        win.Echo.private(channel).listen('.message.sent', (e) => {   // a PRIVATE channel: the server checks who is listening
            if (e.message && e.message.id > lastId) {
                appendMessage(e.message);
                lastId = Math.max(lastId, e.message.id);
                box.dataset.lastId = lastId;
                bumpCounter(1);
                if (autoScroll) box.scrollTop = box.scrollHeight;
            }
        }).listen('.message.deleted', (e) => {
            if (e && e.message_id) markDeleted(e.message_id);
        }).listen('.thread.lock', (e) => {
            if (e && typeof e.is_locked === 'boolean') setLocked(e.is_locked);
        }).listen('.thread.deleted', () => threadDeleted());
        undo.push(() => win.Echo.leave(channel));
    }

    // ── polling fallback ──
    async function poll() {
        try {
            const r = await win.fetch(`${chatUrl}?after_id=${lastId}`);
            if (r.status === 404) { threadDeleted(); return; }     // a deleted thread is gone from the route
            if (!r.ok) return;
            const held = r.headers?.get?.('X-Thread-Locked');       // the polling fallback learns of a lock here
            if (held === '1' || held === '0') setLocked(held === '1');
            const data = await r.json();
            const msgs = data.data ?? data;
            if (Array.isArray(msgs) && msgs.length) {
                msgs.forEach((m) => {
                    appendMessage(m);
                    lastId = Math.max(lastId, m.id);
                });
                box.dataset.lastId = lastId;
                bumpCounter(msgs.length);
                if (autoScroll) box.scrollTop = box.scrollHeight;
            }
        } catch { /* the next tick tries again */ }
    }

    // the refresh button of the header (Alpine calls window.forceChatPoll)
    const forceChatPoll = async () => {
        const btn = doc.querySelector('#btnHeaderRefresh');
        const icon = btn?.querySelector('.material-symbols-outlined');
        const panelLoader = doc.getElementById('panelLoader');

        if (btn) {
            btn.disabled = true;
            btn.classList.add('opacity-70');
        }
        if (icon) icon.classList.add('animate-spin');
        if (panelLoader) {
            panelLoader.classList.remove('hidden');
            panelLoader.classList.add('flex');
        }

        await poll();
        await new Promise((resolve) => win.setTimeout(resolve, 600)); // long enough to feel like something happened

        if (panelLoader) panelLoader.classList.add('hidden');
        if (icon) icon.classList.remove('animate-spin');
        if (btn) {
            btn.disabled = false;
            btn.classList.remove('opacity-70');
        }
    };
    win.forceChatPoll = forceChatPoll;
    undo.push(() => { if (win.forceChatPoll === forceChatPoll) delete win.forceChatPoll; });

    const timer = win.setInterval(poll, POLL_MS);
    undo.push(() => win.clearInterval(timer));

    // ── composer ──
    // A message is sent with fetch: the page does not reload, the box empties, and the message is drawn from the server's answer
    // (the broadcast and the poll that follow carry the same id, so it is never drawn twice). A refusal or a failure keeps what was typed.
    const form = msgInput ? msgInput.closest('form') : null;
    let sending = false;
    const sendButton = form ? form.querySelector('button[type=submit]') : null;

    async function sendMessage() {
        const text = msgInput.value;
        if (!form || !text.trim() || sending) return;
        sending = true;
        if (sendButton) { sendButton.disabled = true; sendButton.classList.add('opacity-60'); }

        try {
            const res = await win.fetch(form.getAttribute('action'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': doc.querySelector('meta[name=csrf-token]')?.getAttribute('content') ?? '',
                },
                body: JSON.stringify({ body: text }),
            });

            if (res.status === 429) {                     // sending too fast: the wait is in Retry-After, the words stay
                const wait = Math.max(1, parseInt(res.headers?.get?.('Retry-After')) || 10);
                win.showToast?.({ type: 'warning', message: `ส่งข้อความถี่เกินไป กรุณารอ ${wait} วินาทีแล้วลองใหม่ ข้อความของคุณยังอยู่ในช่อง` });
            } else if (res.status === 403) {                     // locked while they were typing
                setLocked(true);
                win.showToast?.({ type: 'warning', message: 'กระทู้นี้ถูกล็อกแล้ว ไม่สามารถส่งข้อความใหม่ได้' });
            } else if (res.status === 404) {              // deleted while they were typing
                threadDeleted();
            } else if (!res.ok) {
                win.showToast?.({ type: 'error', message: 'ส่งข้อความไม่สำเร็จ ข้อความของคุณยังอยู่ในช่อง กรุณาลองใหม่อีกครั้ง' });
            } else {
                const m = await res.json();
                msgInput.value = '';
                msgInput.style.height = '48px';
                if (m && m.id > lastId) {                 // the broadcast may have drawn it already
                    appendMessage(m);
                    lastId = Math.max(lastId, m.id);
                    box.dataset.lastId = lastId;
                    bumpCounter(1);
                }
                autoScroll = true;
                box.scrollTop = box.scrollHeight;
                if (btnScrollBottom) btnScrollBottom.classList.add('hidden');
                msgInput.focus?.();
            }
        } catch {
            win.showToast?.({ type: 'error', message: 'ส่งข้อความไม่สำเร็จ ข้อความของคุณยังอยู่ในช่อง กรุณาลองใหม่อีกครั้ง' });
        } finally {
            sending = false;
            if (sendButton) { sendButton.disabled = false; sendButton.classList.remove('opacity-60'); }
        }
    }

    if (form) {
        form.addEventListener('submit', (e) => { e.preventDefault(); sendMessage(); });   // the send button
    }

    if (msgInput) {
        msgInput.addEventListener('input', () => {
            msgInput.style.height = '48px';
            const h = Math.min(msgInput.scrollHeight, 140);
            msgInput.style.height = h + 'px';
            if (autoScroll && h > 48) box.scrollTop = box.scrollHeight;
        });
        msgInput.addEventListener('keydown', (e) => {
            if (win.innerWidth >= 768 && !e.shiftKey && e.key === 'Enter') {
                e.preventDefault();
                sendMessage();
            }
        });
    }

    if (btnScrollBottom) {
        btnScrollBottom.addEventListener('click', () => {
            box.scrollTop = box.scrollHeight;
            autoScroll = true;
            btnScrollBottom.classList.add('hidden');
        });
    }

    return { box, poll, dispose: stop };
}

const INSTALLED = Symbol.for('ppk.chatPage.installed');

/** Call once (the page module loads once per browser session). */
export function installChatPage(win = window) {
    if (win[INSTALLED]) return win[INSTALLED];

    const doc = win.document;
    let page = null;

    // a page was rendered: mount its thread (idempotent — the same rendered thread is mounted once)
    const pageLoaded = () => {
        const box = doc.getElementById('chatBox');
        if (page && page.box === box) return;
        if (page) { page.dispose(); page = null; }
        page = mount(win);
    };

    // the page on screen is about to be replaced: stop what it started
    const leaving = () => {
        if (page) { page.dispose(); page = null; }
    };

    doc.addEventListener('turbo:load', pageLoaded);
    doc.addEventListener('DOMContentLoaded', pageLoaded);
    doc.addEventListener('turbo:before-render', leaving);

    const handle = (win[INSTALLED] = { pageLoaded, leaving, current: () => page });
    if (doc.readyState !== 'loading') pageLoaded(); // this module may load after the page was parsed (first Turbo visit to the chat)
    return handle;
}

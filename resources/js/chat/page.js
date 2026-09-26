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
export const SAFETY_POLL_EVERY = 3;        // ...and while the socket is healthy only every 3rd tick (15 s): a safety net for a message the socket missed, not the delivery path
export const STATUS_TIMEOUT_MS = 10000;    // still "connecting" after this long → show the polling (offline) state


function el(doc, tag, className, text) {
    const node = doc.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
}

const DELETED_TEXT = 'ข้อความนี้ถูกลบ';
const EDITED_TEXT = 'แก้ไขแล้ว';
const EDITED_CLASS = 'msg-edited mt-[2px] text-[11px] opacity-70';

// The "⋮" beside a bubble and its menu. The class lists are the ones chat/_message_menu.blade.php prints (ChatMessageMenuParityTest compares them).
// The dots have no circle or box: the icon lights up under the pointer and while its menu is open.
export const MENU_BUTTON = 'chat-msg-menu-btn inline-flex h-8 w-6 shrink-0 items-center justify-center rounded text-slate-400 transition-colors hover:text-slate-700 aria-expanded:text-slate-700 focus:outline-none focus-visible:text-slate-700 focus-visible:ring-2 focus-visible:ring-slate-300';
export const MENU_BOX = 'chat-msg-menu hidden absolute z-20 top-full mt-[4px] w-[140px] overflow-hidden rounded-md border border-slate-200 bg-white py-[4px]';
export const MENU_ITEM = 'flex w-full items-center gap-[8px] px-[12px] py-[8px] text-left text-[13px] text-slate-700 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none';
export const MENU_ITEM_DANGER = 'flex w-full items-center gap-[8px] px-[12px] py-[8px] text-left text-[13px] text-rose-600 hover:bg-rose-50 focus:bg-rose-50 focus:outline-none';

function menuItem(doc, className, when, icon, label) {
    const item = el(doc, 'button', className);
    item.type = 'button';
    item.setAttribute('role', 'menuitem');
    item.setAttribute('data-when', when);      // 'open': only while the thread is open (hidden the moment it is locked); 'always': a moderator's delete
    const glyph = el(doc, 'span', 'material-symbols-outlined text-[18px]', icon);
    glyph.setAttribute('aria-hidden', 'true');
    item.append(glyph, el(doc, 'span', '', label));
    return item;
}

/**
 * The "⋮" and its menu (edit, delete) - null when this person may do neither. One click handler on the message list serves every row (see mount).
 * canEdit: its author, while the thread is open. canDelete: its author while it is open, or a moderator (whose delete has data-when="always").
 */
function messageMenu(doc, { canEdit, canDelete, canModerate, side }) {
    if (!canEdit && !canDelete) return null;
    const wrap = el(doc, 'div', 'chat-msg-menu-wrap relative shrink-0');
    const btn = el(doc, 'button', MENU_BUTTON);
    btn.type = 'button';
    btn.setAttribute('aria-haspopup', 'menu');
    btn.setAttribute('aria-expanded', 'false');
    btn.setAttribute('title', 'ตัวเลือกข้อความ');
    btn.setAttribute('aria-label', 'ตัวเลือกข้อความ');
    const dots = el(doc, 'span', 'material-symbols-outlined text-[18px]', 'more_vert');
    dots.setAttribute('aria-hidden', 'true');
    btn.append(dots);
    const menu = el(doc, 'div', `${MENU_BOX} ${side === 'right' ? 'right-0' : 'left-0'}`);
    menu.setAttribute('role', 'menu');
    if (canEdit) menu.append(menuItem(doc, `chat-msg-edit ${MENU_ITEM}`, 'open', 'edit', 'แก้ไข'));
    if (canDelete) menu.append(menuItem(doc, `chat-msg-delete ${MENU_ITEM_DANGER}`, canModerate ? 'always' : 'open', 'delete_forever', 'ลบ'));
    wrap.append(btn, menu);
    return wrap;
}

/** A bubble for a message that is not there any more: "ข้อความนี้ถูกลบ", muted, whoever wrote it. */
function deletedBubble(doc, tail) {
    const bubble = el(doc, 'div', `rounded-2xl ${tail} border border-gray-100 bg-gray-50 py-2.5 px-4 text-[14px] italic text-gray-400`);
    bubble.append(el(doc, 'div', 'msg-body', DELETED_TEXT));
    return bubble;
}

/**
 * A message row, as chat/_message.blade.php renders it, for a message that arrived while the page was open.
 * m: { id, user_id, body, deleted?, edited?, created_at, user }.  canDelete / canEdit: this person may delete / edit this one; canModerate: they are a moderator.
 */
export function buildMessageRow(doc, m, { isMe, isConsecutive, timeStr, canDelete = false, canEdit = false, canModerate = false, animate = true }) {
    const gap = isConsecutive ? 'mt-1' : 'mt-4';
    const enter = animate ? 'animate-bubble-in opacity-0 translate-y-2 ' : '';   // only a message that has just arrived slides in
    const row = el(doc, 'div');
    row.dataset.userId = m.user_id;
    row.setAttribute('data-message-id', String(m.id));        // attributes, not dataset: the delete handler finds a row by this selector
    if (m.deleted) row.setAttribute('data-deleted', '1');
    const menuFor = (side) => (m.deleted ? null : messageMenu(doc, { canEdit, canDelete, canModerate, side }));

    const body = el(doc, 'div', 'whitespace-pre-line break-words msg-body', m.body);
    const editedMark = () => (m.edited && !m.deleted ? el(doc, 'div', EDITED_CLASS, EDITED_TEXT) : null);

    if (isMe) {
        row.className = `chat-msg-row flex flex-col items-end w-full ${enter}${gap}`;
        const head = el(doc, 'div', 'flex items-center gap-2 mb-1');
        head.append(el(doc, 'span', 'text-xs text-gray-500', timeStr), el(doc, 'span', 'text-[13px] font-semibold text-gray-900', 'คุณ'));
        const line = el(doc, 'div', 'flex items-center justify-end gap-1 max-w-[85%] sm:max-w-[70%]');
        const mine = menuFor('right');
        if (mine) line.append(mine);
        if (m.deleted) {
            line.append(deletedBubble(doc, 'rounded-tr-none'));
        } else {
            const bubble = el(doc, 'div', 'bg-blue-600 text-white rounded-2xl rounded-tr-none py-2.5 px-4 text-[15px] leading-relaxed');
            bubble.append(body);
            const mark = editedMark();
            if (mark) bubble.append(mark);
            line.append(bubble);
        }
        row.append(head, line);
        return row;
    }

    row.className = `chat-msg-row flex items-start gap-3 w-full ${enter}${gap}`;

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
        const mark = editedMark();
        if (mark) bubble.append(mark);
        line.append(bubble);
    }
    const theirs = menuFor('left');
    if (theirs) line.append(theirs);
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
    let socketUp = false;   // the connection is up AND this thread's channel is subscribed: messages come by push, the poll is only a safety net
    let connectionUp = false;
    let channelUp = false;
    let ticks = 0;          // 5-second ticks since the socket last became healthy (or since the start)
    let ticked = false;     // the interval has run at least once (so "the socket came back" is not the first poll)

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
        doc.getElementById('idleLockWarning')?.remove?.();   // "will be locked if nobody writes": somebody has

        let wrapper = box.querySelector('.space-y-6');
        if (!wrapper) {
            wrapper = el(doc, 'div', 'space-y-6 pb-2');
            box.appendChild(wrapper);
        }

        // the time the SERVER stamped it (not this browser's clock), on the Thai clock; a moderator may delete any, a person their own while it is open
        const row = buildMessageRow(doc, m, { isMe, isConsecutive, timeStr: chatTime(m.created_at ?? new Date()), ...rights(isMe) });
        wrapper.appendChild(row);
        win.setTimeout(() => row.classList.remove('translate-y-2', 'opacity-0'), 10);
    }

    // ── the thread's own state: locked, unlocked, deleted — heard live, or read from the poll's answer ──
    // The page's Alpine state (`locked`) drives the composer, the badges and the lock button, so a change is one assignment.
    function stop() { undo.splice(0).forEach((fn) => fn()); }
    const alpineOf = () => { try { return chatPane && win.Alpine.$data(chatPane); } catch { return null; } };
    function isLocked() { const alpine = alpineOf(); return !!(alpine && alpine.locked); }
    // what this person may do with a message: their own while the thread is open (edit, delete); a moderator deletes any, locked or not
    const rights = (isMe) => ({ canEdit: isMe && !isLocked(), canDelete: canModerate || (isMe && !isLocked()), canModerate });

    const setLocked = (locked) => {
        const alpine = alpineOf();
        if (!alpine || alpine.locked === locked) return;   // whoever did it was shown at once (Alpine flipped before the request)
        alpine.locked = locked;
        syncMenus();
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

    // ── one message: its "⋮" menu, editing it, deleting it ──
    // A deleted row keeps "ข้อความนี้ถูกลบ" in place of its words, for everybody; an edited one keeps its words and says "แก้ไขแล้ว" (a broadcast tells
    // the other pages of both).
    const rowOf = (id) => box.querySelector(`[data-message-id="${id}"]`);
    const csrf = () => doc.querySelector('meta[name=csrf-token]')?.getAttribute('content') ?? '';
    const jsonHeaders = () => ({ Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() });

    // While a menu is open the document hears a click elsewhere, or Escape, and closes it; nothing listens to the document otherwise.
    let openMenu = null;     // { btn, menu } - only one menu is open at a time
    const outside = (e) => { if (openMenu && !e.target.closest?.('.chat-msg-menu-wrap')) closeMenu(); };
    const escape = (e) => { if (e.key === 'Escape') closeMenu(); };
    function closeMenu() {
        doc.removeEventListener('click', outside);
        doc.removeEventListener('keydown', escape);
        if (!openMenu) return;
        openMenu.menu.classList.add('hidden');
        openMenu.btn.setAttribute('aria-expanded', 'false');
        openMenu = null;
    }
    undo.push(closeMenu);
    function toggleMenu(btn) {
        const menu = btn.parentElement?.querySelector('.chat-msg-menu');
        if (!menu) return;
        const wasOpen = openMenu && openMenu.menu === menu;
        closeMenu();
        if (wasOpen) return;
        // near the bottom of the list the menu opens upward, so it is not cut off by the edge of the scrolling box
        const at = btn.getBoundingClientRect?.();
        const edge = box.getBoundingClientRect?.();
        const up = !!(at && edge && at.bottom + 96 > edge.bottom);
        menu.classList.toggle('top-full', !up);
        menu.classList.toggle('mt-[4px]', !up);
        menu.classList.toggle('bottom-full', up);
        menu.classList.toggle('mb-[4px]', up);
        menu.classList.remove('hidden');
        btn.setAttribute('aria-expanded', 'true');
        openMenu = { btn, menu };
        doc.addEventListener('click', outside);
        doc.addEventListener('keydown', escape);
    }

    // The menu follows the lock at once: while it is locked an author can neither edit nor delete their own words, a moderator can still delete.
    function syncMenus() {
        const locked = isLocked();
        box.querySelectorAll('.chat-msg-menu-wrap').forEach((wrap) => {
            let shown = 0;
            wrap.querySelectorAll('[data-when]').forEach((item) => {
                const hide = locked && item.getAttribute('data-when') === 'open';
                item.classList.toggle('hidden', hide);
                if (!hide) shown++;
            });
            wrap.classList.toggle('hidden', shown === 0);
        });
        if (locked) { closeMenu(); cancelEdit(); }
    }

    function markDeleted(id) {
        const row = rowOf(id);
        if (!row || row.hasAttribute('data-deleted')) return;
        if (editing && editing.id === String(id)) cancelEdit();
        row.setAttribute('data-deleted', '1');
        row.querySelectorAll('.chat-msg-menu-wrap, .msg-edited').forEach((node) => node.remove());
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

    // ── editing: the bubble turns into a text box, right where it is (no dialog) ──
    let editing = null;      // { id, row, bodyEl, mark, editor, box, save, cancel }
    function cancelEdit() {
        if (!editing) return;
        const { editor, bodyEl, mark } = editing;
        editor.remove();
        bodyEl.style.display = '';
        if (mark) mark.style.display = '';
        editing = null;
    }

    /** the message's text in place: the row swaps its words and says "แก้ไขแล้ว" (its own page after saving, every other page by the broadcast) */
    function applyEdit(id, text) {
        const row = rowOf(id);
        if (!row || row.hasAttribute('data-deleted')) return;
        if (editing && editing.id === String(id)) cancelEdit();
        const body = row.querySelector('.msg-body');
        if (!body) return;
        body.textContent = text;
        if (!row.querySelector('.msg-edited')) body.parentElement?.append(el(doc, 'div', EDITED_CLASS, EDITED_TEXT));
    }

    function startEdit(row) {
        const id = row.getAttribute('data-message-id');
        const bodyEl = row.querySelector('.msg-body');
        if (!bodyEl || row.hasAttribute('data-deleted')) return;
        cancelEdit();

        const original = bodyEl.textContent;
        const mark = row.querySelector('.msg-edited');
        const editor = el(doc, 'div', 'msg-editor w-full min-w-[240px] sm:min-w-[360px] space-y-[8px]');
        const field = el(doc, 'textarea', 'w-full resize-none rounded-md border border-slate-300 bg-white px-[12px] py-[8px] text-[14.5px] leading-relaxed text-gray-900 focus:border-[#0F2D5C]/50 focus:outline-none focus:ring-2 focus:ring-[#0F2D5C]/35');
        field.value = original;
        field.rows = Math.min(8, Math.max(2, original.split('\n').length));
        field.setAttribute('maxlength', '3000');
        field.setAttribute('aria-label', 'แก้ไขข้อความ');
        const buttons = el(doc, 'div', 'flex justify-end gap-[8px]');
        const cancel = el(doc, 'button', 'msg-edit-cancel inline-flex h-8 items-center justify-center rounded-md border border-slate-200 bg-white px-[12px] text-[13px] font-semibold text-slate-600 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-200', 'ยกเลิก');
        cancel.type = 'button';
        const save = el(doc, 'button', 'msg-edit-save inline-flex h-8 items-center justify-center rounded-md bg-[#0F2D5C] px-[12px] text-[13px] font-semibold text-white hover:bg-[#0F2D5C]/90 focus:outline-none focus:ring-2 focus:ring-[#0F2D5C]/40 disabled:opacity-50', 'บันทึก');
        save.type = 'button';
        buttons.append(cancel, save);
        editor.append(field, buttons);

        bodyEl.style.display = 'none';
        if (mark) mark.style.display = 'none';
        bodyEl.parentElement.append(editor);
        editing = { id, row, bodyEl, mark, editor };
        field.focus?.();

        let saving = false;
        async function submit() {
            if (saving) return;
            const text = field.value.trim();
            if (!text) { win.showToast?.({ type: 'warning', message: 'ข้อความต้องไม่ว่าง' }); return; }
            if (text === original.trim()) { cancelEdit(); return; }      // nothing changed: nothing to send, nothing to mark
            saving = true; save.disabled = true;
            try {
                const res = await win.fetch(`${chatUrl}/${id}`, { method: 'PATCH', headers: jsonHeaders(), body: JSON.stringify({ body: text }) });
                if (res.ok) {
                    const saved = await res.json().catch(() => ({}));
                    applyEdit(id, saved.body ?? text);
                } else if (res.status === 404) {                          // deleted a moment ago
                    markDeleted(id);
                    win.showToast?.({ type: 'warning', message: 'ข้อความนี้ถูกลบไปแล้ว' });
                } else if (res.status === 403) {
                    cancelEdit();
                    win.showToast?.({ type: 'error', message: 'แก้ไขข้อความนี้ไม่ได้ (แก้ได้เฉพาะข้อความของตนเอง และขณะที่กระทู้ยังไม่ล็อก)' });
                } else if (res.status === 422) {
                    win.showToast?.({ type: 'warning', message: 'ข้อความต้องไม่ว่าง และยาวไม่เกิน 3,000 ตัวอักษร' });
                } else if (res.status === 429) {
                    const wait = parseInt(res.headers?.get?.('Retry-After')) || 10;
                    win.showToast?.({ type: 'warning', message: `แก้ไขถี่เกินไป กรุณารอ ${wait} วินาทีแล้วลองใหม่` });
                } else {
                    win.showToast?.({ type: 'error', message: 'บันทึกการแก้ไขไม่สำเร็จ กรุณาลองใหม่อีกครั้ง' });
                }
            } catch {
                win.showToast?.({ type: 'error', message: 'บันทึกการแก้ไขไม่สำเร็จ กรุณาลองใหม่อีกครั้ง' });
            } finally {
                saving = false; save.disabled = false;
            }
        }
        save.addEventListener('click', submit);
        cancel.addEventListener('click', cancelEdit);
        field.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { e.preventDefault(); cancelEdit(); return; }
            // Enter saves, Shift+Enter is a new line; not while a Thai / Japanese input method is still composing
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing && e.keyCode !== 229) { e.preventDefault(); submit(); }
        });
    }

    // ── deleting: the app's own confirmation dialog (like every other page), not the browser's ──
    function confirmDelete() {
        const words = { title: 'ลบข้อความนี้', message: 'ข้อความจะหายไปจากกระทู้สำหรับทุกคน', confirmText: 'ลบข้อความ', cancelText: 'ยกเลิก', variant: 'danger' };
        if (win.Confirm?.show) return win.Confirm.show(words);
        return Promise.resolve(win.confirm(`${words.title}ใช่หรือไม่? ${words.message}`));    // the dialog is not there (yet): the browser's
    }

    async function deleteMessage(row) {
        if (!(await confirmDelete())) return;
        const messageId = row.getAttribute('data-message-id');
        try {
            const res = await win.fetch(`${chatUrl}/${messageId}`, {
                method: 'DELETE',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf() },
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
    }

    // one handler for every row (the ones that arrive live too)
    box.addEventListener('click', (e) => {
        const target = e.target;
        const menuBtn = target.closest?.('.chat-msg-menu-btn');
        if (menuBtn) { toggleMenu(menuBtn); return; }
        const edit = target.closest?.('.chat-msg-edit');
        if (edit) { closeMenu(); const row = edit.closest('[data-message-id]'); if (row) startEdit(row); return; }
        const del = target.closest?.('.chat-msg-delete');
        if (del) { closeMenu(); const row = del.closest('[data-message-id]'); if (row) deleteMessage(row); }
    });

    // ── earlier messages, like scrolling up in any messenger: the batch before the first one drawn (cursor = its id, not a page number) ──
    const list = doc.getElementById('chatList');
    const earlierWrap = doc.getElementById('loadEarlierWrap');
    let firstId = parseInt(box.dataset.firstId) || 0;
    let hasMore = box.dataset.hasMore === '1';
    let loadingOlder = false;

    async function loadOlder() {
        if (!hasMore || loadingOlder || !list || !firstId) return;
        loadingOlder = true;
        box.setAttribute('aria-busy', 'true');
        box.setAttribute('aria-live', 'off');          // a screen reader must not read the whole batch as if it were new
        try {
            const res = await win.fetch(`${chatUrl}?before_id=${firstId}&limit=30`, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const batch = await res.json();
            hasMore = res.headers?.get?.('X-Has-More') === '1';

            if (Array.isArray(batch) && batch.length) {
                const oldFirst = list.firstElementChild;
                const rows = [];
                let previousUser = 0;
                batch.forEach((m, i) => {
                    const isMe = parseInt(m.user_id) === myId;
                    const row = buildMessageRow(doc, m, {
                        isMe, isConsecutive: parseInt(m.user_id) === previousUser, timeStr: chatTime(m.created_at ?? new Date()),
                        ...rights(isMe), animate: false,
                    });
                    previousUser = parseInt(m.user_id);
                    if (i === 0) { row.classList.remove('mt-1', 'mt-4'); row.classList.add('mt-0'); }
                    rows.push(row);
                });
                if (oldFirst) { oldFirst.classList.remove('mt-0'); oldFirst.classList.add('mt-4'); }

                const heightBefore = box.scrollHeight;
                list.prepend(...rows);
                box.scrollTop += box.scrollHeight - heightBefore;   // what the reader was looking at stays where it was
                firstId = batch[0].id;
            } else {
                hasMore = false;
            }
        } catch {
            win.showToast?.({ type: 'error', message: 'โหลดข้อความก่อนหน้าไม่สำเร็จ กรุณาลองใหม่อีกครั้ง' });
        } finally {
            loadingOlder = false;
            box.removeAttribute('aria-busy');
            box.setAttribute('aria-live', 'polite');
            if (earlierWrap) earlierWrap.classList.toggle('hidden', !hasMore);
        }
    }
    doc.getElementById('btnLoadEarlier')?.addEventListener('click', loadOlder);
    box.addEventListener('scroll', () => { if (box.scrollTop < 120) loadOlder(); });

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
        // healthy = connected AND subscribed to this thread's channel: a connection can be up while the subscription was refused (the private
        // channel's authorisation failed), and then nothing arrives by push - the poll must stay at 5 s
        const refreshHealth = () => {
            const wasUp = socketUp;
            socketUp = connectionUp && channelUp;
            if (socketUp && !wasUp) {
                if (ticked) poll();                          // it was down: ask what it missed at once
                ticks = 0;                                   // and the safety net counts from now
            }
        };
        const updateStatus = (state) => {
            connectionUp = state === 'connected';
            refreshHealth();
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
        const subscription = win.Echo.private(channel);   // a PRIVATE channel: the server checks who is listening
        subscription.subscribed?.(() => { channelUp = true; refreshHealth(); });
        subscription.error?.(() => { channelUp = false; refreshHealth(); win.console?.warn('[Chat] the channel was refused; polling instead'); });
        subscription.listen('.message.sent', (e) => {
            if (e.message && e.message.id > lastId) {
                appendMessage(e.message);
                lastId = Math.max(lastId, e.message.id);
                box.dataset.lastId = lastId;
                bumpCounter(1);
                if (autoScroll) box.scrollTop = box.scrollHeight;
            }
        }).listen('.message.deleted', (e) => {
            if (e && e.message_id) markDeleted(e.message_id);
        }).listen('.message.updated', (e) => {
            if (e && e.message_id && typeof e.body === 'string') applyEdit(e.message_id, e.body);
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

    // every 5 s while the socket is down or still connecting; while it is healthy only every 3rd tick (15 s, SAFETY_POLL_EVERY) - polling every 5 s beside a
    // working websocket costs the server a request per open thread per 5 s for nothing (what the socket carries is not asked for again)
    const timer = win.setInterval(() => {
        ticked = true;
        ticks += 1;
        if (socketUp && ticks % SAFETY_POLL_EVERY !== 0) return;
        poll();
    }, POLL_MS);
    undo.push(() => win.clearInterval(timer));

    // ── composer ──
    // A message is sent with fetch: the page does not reload, the box empties, and the message is drawn from the server's answer
    // (the broadcast and the poll that follow carry the same id, so it is never drawn twice). A refusal or a failure keeps what was typed.
    const form = msgInput ? msgInput.closest('form') : null;
    let sending = false;
    let attempt = null;     // { text, id }: the same words sent again after a failure keep the same id, so the server can tell it is a retry
    const newId = () => win.crypto?.randomUUID?.() ?? 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = Math.floor(Math.random() * 16);
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
    });
    const sendButton = form ? form.querySelector('button[type=submit]') : null;

    async function sendMessage() {
        const text = msgInput.value;
        if (!form || !text.trim() || sending) return;
        sending = true;
        if (!attempt || attempt.text !== text) attempt = { text, id: newId() };
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
                body: JSON.stringify({ body: text, client_id: attempt.id }),
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
                attempt = null;
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

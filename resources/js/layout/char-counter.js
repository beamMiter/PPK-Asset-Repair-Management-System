// A text area with a `maxlength` shows how much of it is used, and says so when a paste is cut. The browser stops typing at the limit
// and cuts a longer paste to it — both silently, so text the user thought they had written was simply not there.
//
//   <textarea maxlength="1000" data-counter></textarea>      →  "123 / 1000" under it; amber near the limit, red at it
//
// Opt in with `data-counter`: a page's compact text areas (the chat composer) are left as they are.
//
//   installCharCounter()   call once; a second call returns the first handle instead of registering again
const INSTALLED = Symbol.for('ppk.char-counter.installed');

const BASE = 'mt-1 text-right text-[11px]';
const TONES = { ok: 'text-slate-400', near: 'text-amber-600', full: 'text-rose-600 font-semibold' };

const maxOf = (ta) => Number(ta.getAttribute('maxlength')) || 0;

/** the counter under a text area, made on the first need */
function counterOf(ta, doc) {
    let counter = ta.nextElementSibling;
    if (counter && counter.getAttribute('data-char-counter') !== null) return counter;

    counter = doc.createElement('p');
    counter.setAttribute('data-char-counter', '');
    counter.setAttribute('aria-live', 'polite');
    ta.after(counter);

    return counter;
}

export function refreshCounter(ta, doc = ta.ownerDocument) {
    const max = maxOf(ta);
    if (!max) return;

    const used = ta.value.length;
    const counter = counterOf(ta, doc);
    const tone = used >= max ? TONES.full : used >= max * 0.9 ? TONES.near : TONES.ok;

    counter.textContent = `${used} / ${max}`;
    counter.className = `${BASE} ${tone}`;
}

/** Text that does not fit after a paste lands: how much would be left, else null. */
export function pasteOverflow(ta, pasted) {
    const max = maxOf(ta);
    if (!max) return null;

    const replaced = (ta.selectionEnd ?? 0) - (ta.selectionStart ?? 0);
    const after = ta.value.length - replaced + pasted.length;

    return after > max ? max : null;
}

export function installCharCounter(win = window) {
    if (win[INSTALLED]) return win[INSTALLED];

    const doc = win.document;
    const counted = (el) => el && el.tagName === 'TEXTAREA' && el.getAttribute('data-counter') !== null;

    const sweep = () => doc.querySelectorAll('textarea[data-counter]').forEach((ta) => refreshCounter(ta, doc));

    doc.addEventListener('input', (event) => { if (counted(event.target)) refreshCounter(event.target, doc); });

    // a text area that is not in the page yet when it loads (a dialog Alpine builds later) is met when it is first used
    doc.addEventListener('focusin', (event) => { if (counted(event.target)) refreshCounter(event.target, doc); });

    doc.addEventListener('paste', (event) => {
        const ta = event.target;
        if (!counted(ta)) return;

        const kept = pasteOverflow(ta, event.clipboardData?.getData('text') ?? '');
        if (kept === null) return;

        win.dispatchEvent(new CustomEvent('app:toast', {
            detail: { type: 'warning', message: `ข้อความที่วางยาวเกินกำหนด ระบบตัดให้เหลือ ${kept} ตัวอักษร`, timeout: 4500 },
        }));
    }, true);

    doc.addEventListener('DOMContentLoaded', sweep);
    doc.addEventListener('turbo:load', sweep);
    if (doc.readyState !== 'loading') sweep();

    return (win[INSTALLED] = { sweep });
}

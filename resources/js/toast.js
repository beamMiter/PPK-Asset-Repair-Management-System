// Toast notifications. The overlay element lives in components/toast.blade.php, the styles in resources/css/toast.css.
// This used to be an inline <script> in that component, which Turbo Drive re-ran on every visit; it is now a module that is
// evaluated once per browser session.
//
//   window.showToast({ type, message, title, size, timeout })   show one directly
//   window.dispatchEvent(new CustomEvent('app:toast', { detail: { … } }))   the same, from any inline script
//   <script id="session-toast-data" type="application/json">…</script>   a flashed `toast` (layouts/app, layouts/auth):
//   shown on the page that carries it — after the sidebar intro if one is playing (login).
//
// `installToast()` registers every document / window listener once; the elements a toast creates carry their own.

const FORCE_POSITION = 'tr';
const DEFAULT_POSITION = 'tr';
const DEFAULT_SIZE = 'lg';
const TYPES = ['success', 'info', 'warning', 'error'];
const SIZES = ['sm', 'md', 'lg', 'xl'];
const TITLES = { success: 'สำเร็จ', error: 'เกิดข้อผิดพลาด', warning: 'โปรดตรวจสอบ', info: 'แจ้งเตือน' };

// static markup, no user text — safe for innerHTML
const ICONS = {
    success: '<svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>',
    error: '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
    warning: '<svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    info: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
};

function ensurePos(doc, position) {
    const overlay = doc.querySelector('.toast-overlay');
    if (!overlay) return null;
    let posEl = overlay.querySelector('.toast-pos');
    if (!posEl || !posEl.classList.contains(position)) {
        overlay.innerHTML = '';
        posEl = doc.createElement('div');
        posEl.className = 'toast-pos ' + position;
        overlay.appendChild(posEl);
    }
    return posEl;
}

/**
 * Show one toast. `open` is the set of close functions of the toasts on screen (Escape closes them).
 * Nothing the caller passes is treated as HTML: title and message go in as text.
 */
export function showToast(win, options = {}, open = new Set()) {
    const doc = win.document;
    let { type = 'info', message = '', position = DEFAULT_POSITION, timeout = 3800, size = DEFAULT_SIZE, title = null } = options;

    type = TYPES.includes(type) ? type : 'info';
    position = FORCE_POSITION || position || DEFAULT_POSITION;
    timeout = Number.isFinite(Number(timeout)) && Number(timeout) >= 800 ? Number(timeout) : 3800;
    size = SIZES.includes(size) ? size : DEFAULT_SIZE;

    const posEl = ensurePos(doc, position);
    if (!posEl) return;

    const card = doc.createElement('section');
    card.className = `toast-card toast--${size} toast--${type}`;
    card.setAttribute('role', 'status');

    const inner = doc.createElement('div');
    inner.className = 'toast-inner';

    const ico = doc.createElement('div');
    ico.className = 'toast-ico';
    ico.innerHTML = ICONS[type];

    const textWrap = doc.createElement('div');
    textWrap.className = 'toast-text';

    const heading = doc.createElement('div');
    heading.className = 'toast-title';
    heading.textContent = title ?? TITLES[type] ?? TITLES.info;

    const text = doc.createElement('p');
    text.className = 'toast-msg';
    text.textContent = message ?? '';

    textWrap.append(heading, text);

    const btn = doc.createElement('button');
    btn.className = 'toast-close';
    btn.setAttribute('aria-label', 'ปิด');
    btn.innerHTML = '&times;';

    inner.append(ico, textWrap, btn);

    const bar = doc.createElement('div');
    bar.className = 'toast-bar';
    const fill = doc.createElement('div');
    fill.className = 'toast-fill';
    bar.appendChild(fill);

    card.append(inner, bar);
    posEl.appendChild(card);

    win.requestAnimationFrame(() => {
        card.classList.add('show');
        win.requestAnimationFrame(() => {
            fill.style.transition = `width ${timeout}ms linear`;
            fill.style.width = '100%';
        });
    });

    let startAt = Date.now();
    let remain = timeout;
    let timer;
    let closed = false;

    function close() {
        if (closed) return;
        closed = true;
        open.delete(close);
        win.clearTimeout(timer);
        card.classList.remove('show');
        card.classList.add('hide');
        win.setTimeout(() => card.remove(), 200);
    }

    open.add(close);
    timer = win.setTimeout(close, timeout + 60);
    btn.addEventListener('click', close);

    // hovering pauses the countdown
    card.addEventListener('mouseenter', () => {
        win.clearTimeout(timer);
        remain = Math.max(0, remain - (Date.now() - startAt));
        fill.style.transition = 'none';
        fill.style.width = ((1 - remain / timeout) * 100) + '%';
    });

    card.addEventListener('mouseleave', () => {
        startAt = Date.now();
        fill.style.transition = `width ${remain}ms linear`;
        fill.style.width = '100%';
        timer = win.setTimeout(close, remain + 50);
    });
}

/** Show the flashed toast the page carries, once. During the sidebar intro (login) wait until it is over. */
export function checkSessionToast(win, show) {
    const doc = win.document;
    const el = doc.getElementById('session-toast-data');
    if (!el) return;

    try {
        const data = JSON.parse(el.textContent);
        el.remove();

        const html = doc.documentElement;
        if (!html.classList.contains('intro-pending')) {
            show(data);
            return;
        }

        // Two paths can fire this toast (intro finished / 5 s safety fallback). Whichever wins must cancel the other,
        // otherwise the toast is shown twice — which is what happened to "Login successful", the only toast that lands
        // mid-intro.
        let fired = false;
        let fallbackTimer = null;
        const fire = (delay) => {
            if (fired) return;
            fired = true;
            observer.disconnect();
            win.clearTimeout(fallbackTimer);
            win.setTimeout(() => show(data), delay); // a moment for the UI to settle
        };

        // sidebar-intro.js removes the `intro-pending` class from <html> when it is done
        const observer = new win.MutationObserver(() => {
            if (!html.classList.contains('intro-pending')) fire(300);
        });
        observer.observe(html, { attributes: true, attributeFilter: ['class'] });

        fallbackTimer = win.setTimeout(() => fire(0), 5000);
    } catch (e) {
        win.console?.error('Error parsing session toast data:', e);
    }
}

const INSTALLED = Symbol.for('ppk.toast.installed');

export function installToast(win = window) {
    if (win[INSTALLED]) return win[INSTALLED];

    const doc = win.document;
    const open = new Set();
    const show = (options) => showToast(win, options, open);
    const check = () => checkSessionToast(win, show);

    win.showToast = show;
    win.addEventListener('app:toast', (e) => show(e.detail || {}));
    doc.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') [...open].forEach((close) => close());
    });

    doc.addEventListener('turbo:load', check);
    doc.addEventListener('DOMContentLoaded', check);

    const handle = (win[INSTALLED] = { show, check, open });
    if (doc.readyState !== 'loading') check(); // this module may run after the page was parsed
    return handle;
}

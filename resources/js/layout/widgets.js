// Small enhancements applied to whatever a page rendered: Bootstrap dropdowns, TomSelect on `select.ts-basic` /
// `.ts-department`, and auto-growing textareas. Each element is enhanced once, so calling these on every page load
// (and more than once on the first) is safe. Bootstrap and TomSelect come from the CDN <script> tags in the layout.

export function initDropdowns(win) {
    const Dropdown = win.bootstrap?.Dropdown;
    if (!Dropdown) return;
    win.document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach((el) => {
        if (!Dropdown.getInstance(el)) new Dropdown(el, { autoClose: 'outside' });
    });
}

export function initTomSelect(win, root) {
    if (!win.TomSelect) return;
    (root || win.document).querySelectorAll('select.ts-basic, select.ts-department').forEach((el) => {
        if (el.tomselect) return;

        const placeholder = el.getAttribute('data-placeholder') || el.getAttribute('placeholder') || '— ไม่ระบุ —';

        new win.TomSelect(el, {
            create: false,
            allowEmptyOption: true,
            maxOptions: 2000,
            sortField: { field: 'text', direction: 'asc' },
            placeholder,
            searchField: ['text'],
        });
    });
}

export function initAutoResize(win, root) {
    (root || win.document).querySelectorAll('textarea.overflow-hidden').forEach((el) => {
        if (el.dataset.autoresizeBound) return;
        el.dataset.autoresizeBound = '1';

        const resize = () => {
            el.style.height = 'auto';
            el.style.height = el.scrollHeight + 'px';
        };
        el.addEventListener('input', resize);
        win.setTimeout(resize, 0); // once the CSS has been applied
    });
}

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

// The colour the HIS number (เลข รพจ) has on the asset table (assets/index): bold blue.
const HIS_CLASS = 'text-blue-700 font-semibold';

/**
 * An asset option carries its HIS number (`data-his` on the <option>): the text stays whole — "AST-001 - name (รพจ. 6500123)", that
 * is what the picker searches — but the HIS part is drawn in the table's colour, in the list and in the chosen value.
 */
function renderWithHis(data, escape) {
    const text = String(data.text ?? '').trim();
    const his = data.his ? ` (รพจ. ${data.his})` : '';

    if (his && text.endsWith(his)) {
        return `<div>${escape(text.slice(0, -his.length))}<span class="${HIS_CLASS}">${escape(his)}</span></div>`;
    }
    return `<div>${escape(text)}</div>`;
}

/** While the text box has text in it the default ("ไม่ระบุ") value is hidden (layout.css: `.is-typing`), so it never sits in the way. */
function setTyping(ts, typing) {
    if (typing) ts.wrapper.classList.add('is-typing');
    else ts.wrapper.classList.remove('is-typing');
}

export function initTomSelect(win, root) {
    if (!win.TomSelect) return;
    (root || win.document).querySelectorAll('select.ts-basic, select.ts-department').forEach((el) => {
        if (el.tomselect) return;

        // The empty option of a form select ("— ไม่ระบุ —") is its default value: a real option, so picking it from the list gives
        // "not specified" back. It is drawn like a placeholder (muted — layout.css), it is the first row of the list, and it steps
        // aside while you type to search (`is-typing` on the wrapper) — it used to stay in the field as if it were what you typed.
        const emptyOption = el.querySelector('option[value=""]');
        const emptyLabel = emptyOption ? String(emptyOption.textContent || '').trim() : '';
        const placeholder = el.getAttribute('data-placeholder') || el.getAttribute('placeholder') || emptyLabel || '— ไม่ระบุ —';
        if (emptyOption) emptyOption.dataset.noneFirst = '0'; // TomSelect reads data-* of an <option> as its data: sorts it first

        new win.TomSelect(el, {
            create: false,
            allowEmptyOption: true,
            maxOptions: 2000,
            sortField: [{ field: 'noneFirst', direction: 'asc' }, { field: 'text', direction: 'asc' }],
            placeholder,
            searchField: ['text'],
            ...(el.querySelector('option[data-his]') ? { render: { option: renderWithHis, item: renderWithHis } } : {}),
            onInitialize() {
                // on the text box's own `input` event, not TomSelect's `type` (which waits 300 ms): the words go the moment you type
                this.control_input.addEventListener('input', () => setTyping(this, this.control_input.value !== ''));
            },
            onBlur() { setTyping(this, false); },
            onDropdownClose() { setTyping(this, false); },
            onItemAdd() { setTyping(this, false); },
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

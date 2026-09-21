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

export function initTomSelect(win, root) {
    if (!win.TomSelect) return;
    (root || win.document).querySelectorAll('select.ts-basic, select.ts-department').forEach((el) => {
        if (el.tomselect) return;

        // The empty option of a form select ("— ไม่ระบุ —") means "nothing chosen": it is the placeholder — grey, and gone as soon
        // as you type — not a chosen value. (`allowEmptyOption: true` made TomSelect show its words as if they were the value,
        // and they stayed in the field while you searched.) A clear (×) button takes a chosen value back to "not specified".
        const emptyOption = el.querySelector('option[value=""]');
        const emptyLabel = emptyOption ? String(emptyOption.textContent || '').trim() : '';
        const placeholder = el.getAttribute('data-placeholder') || el.getAttribute('placeholder') || emptyLabel || '— ไม่ระบุ —';
        const required = el.required || el.hasAttribute('required');

        new win.TomSelect(el, {
            create: false,
            maxOptions: 2000,
            sortField: { field: 'text', direction: 'asc' },
            placeholder,
            searchField: ['text'],
            plugins: required ? {} : { clear_button: { title: 'ล้างค่าที่เลือก' } },
            ...(el.querySelector('option[data-his]') ? { render: { option: renderWithHis, item: renderWithHis } } : {}),
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

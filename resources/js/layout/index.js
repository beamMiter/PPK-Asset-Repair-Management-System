// Behaviour of the app layout (layouts/app.blade.php) — sidebar, page-load spinner, dropdowns, TomSelect, auto-growing
// textareas and the unsaved-changes guard. It used to be inline <script>s in the layout, which Turbo Drive re-runs on
// every visit: each visit stacked another copy of every document / window listener on top of the last.
//
// Here the document / window listeners are registered ONCE, when this module loads (a module is evaluated once per
// browser session, however many Turbo visits follow), and the per-page work — applying the saved sidebar state,
// enhancing the new page's dropdowns / selects / textareas, watching its forms — runs from a single load handler.
//
//   installLayout()   call once; a second call returns the first handle instead of registering again
import { createLoader, installLoader } from './loader.js';
import { initSidebarState, installSidebar } from './sidebar.js';
import { initAutoResize, initDropdowns, initTomSelect } from './widgets.js';
import { installDirtyCheck } from './dirty-check.js';

const INSTALLED = Symbol.for('ppk.layout.installed');

export function installLayout(win = window) {
    if (win[INSTALLED]) return win[INSTALLED];

    const doc = win.document;
    const loader = createLoader(win);
    win.Loader = loader;
    win.initTomSelect = (root) => initTomSelect(win, root);
    win.initAutoResize = (root) => initAutoResize(win, root);

    installSidebar(win);       // click delegate first, then the loader's, then the capturing dirty-check guard: the order the
    installLoader(win, loader); // inline scripts registered them in
    const dirty = installDirtyCheck(win);

    // One failing enhancement must not stop the others (they used to be separate inline scripts).
    const step = (fn) => { try { fn(); } catch (error) { win.console?.error('[layout]', error); } };

    // Idempotent: it runs on DOMContentLoaded, on every turbo:load, and once now if the page is already parsed.
    const pageLoaded = () => {
        step(() => loader.hide());
        if (!doc.getElementById('layout')) { dirty.reset(); return; } // Turbo rendered a page that is not in this layout (login …)

        step(() => initSidebarState(win));
        step(() => initDropdowns(win));
        step(() => initTomSelect(win, doc));   // before the dirty check, which also watches the TomSelect wrappers
        step(() => win.setTimeout(() => loader.hide(), 600)); // safety net if something left the spinner up
        step(() => initAutoResize(win, doc));
        step(() => dirty.init());
    };

    const handle = (win[INSTALLED] = { pageLoaded, loader, dirty }); // recorded before anything can throw: never install twice

    doc.addEventListener('DOMContentLoaded', pageLoaded);
    doc.addEventListener('turbo:load', pageLoaded);
    if (doc.readyState !== 'loading') pageLoaded(); // this module may run after the page was parsed (first Turbo visit into the layout)

    return handle;
}

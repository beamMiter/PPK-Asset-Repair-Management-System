// The full-page spinner (`#loaderOverlay`, in layouts/app.blade.php): shown when a link or form leaves the page, hidden
// again when a page has loaded. Exposed as `window.Loader` for the rest of the app.

export function createLoader(win = window) {
    const doc = win.document;
    return {
        show() {
            if (doc.documentElement.classList.contains('intro-pending')) return; // not while the sidebar intro plays
            doc.getElementById('loaderOverlay')?.classList.add('show');
        },
        hide() {
            doc.getElementById('loaderOverlay')?.classList.remove('show');
        },
    };
}

/** Document / window listeners — call once. */
export function installLoader(win, loader) {
    const doc = win.document;

    doc.addEventListener('click', (e) => {
        if (e.target.closest('#chatWidgetRoot')) return;
        if (e.defaultPrevented) return;
        const a = e.target.closest('a');
        if (!a) return;
        const href = a.getAttribute('href') || '';
        const noLoader = a.hasAttribute('data-no-loader') || a.getAttribute('target');
        if (!noLoader && href && !href.startsWith('#')) loader.show();
    });

    doc.addEventListener('submit', (e) => {
        const form = e.target;
        if (e.defaultPrevented) return;
        if (form instanceof win.HTMLFormElement && !form.hasAttribute('data-no-loader')) loader.show();
    });

    win.addEventListener('beforeunload', () => loader.show());
    win.addEventListener('pageshow', () => loader.hide()); // back / forward cache restores a page with the spinner still up
}

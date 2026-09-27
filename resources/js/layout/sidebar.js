// The sidebar: an off-canvas drawer on small screens (`openSide` / `closeSide`, called by the buttons in
// components/topbar and components/sidebar) and a collapse toggle on large ones (`toggleSidebarCollapse`, remembered in
// localStorage). The markup is replaced on every Turbo visit, so the saved state is applied again on each page load.

const KEY = 'app.sidebar.collapsed';
const MOBILE = '(max-width: 1024px)';

// blocked storage (private mode, policy) must not stop the rest of the layout from starting
const read = (win) => { try { return win.localStorage.getItem(KEY); } catch { return null; } };
const write = (win, value) => { try { win.localStorage.setItem(KEY, value); } catch { /* not persisted */ } };

function applyCollapsed(doc, collapsed) {
    const side = doc.getElementById('side');
    if (!side) return;

    if (collapsed) {
        side.classList.add('collapsed');
        side.classList.remove('compact');
        doc.body.classList.add('with-collapsed');
        doc.body.classList.remove('with-compact', 'with-expanded');
    } else {
        side.classList.remove('collapsed');
        doc.body.classList.remove('with-collapsed', 'with-compact');
        doc.body.classList.add('with-expanded');
    }
}

function closeSide(win) {
    const doc = win.document;
    const side = doc.getElementById('side');
    const backdrop = doc.getElementById('backdrop');

    side?.classList.remove('open');
    backdrop?.classList.remove('show');
    backdrop?.classList.add('hidden');
    backdrop?.setAttribute('aria-hidden', 'true');

    // a Bootstrap offcanvas, if one happens to be open
    const offcanvas = doc.querySelector('.offcanvas.show');
    if (offcanvas && win.bootstrap && win.bootstrap.Offcanvas) {
        (win.bootstrap.Offcanvas.getInstance(offcanvas) || new win.bootstrap.Offcanvas(offcanvas)).hide();
    }

    doc.body.style.overflow = '';
}

function openSide(win) {
    const doc = win.document;
    doc.getElementById('side')?.classList.add('open');
    const backdrop = doc.getElementById('backdrop');
    backdrop?.classList.remove('hidden');
    backdrop?.classList.add('show');
    backdrop?.setAttribute('aria-hidden', 'false');
    doc.getElementById('btnSidebar')?.setAttribute('aria-expanded', 'true');
    doc.body.style.overflow = 'hidden';
}

function handleResize(win, mql) {
    const doc = win.document;
    if (mql.matches) {
        doc.getElementById('layout')?.classList.remove('with-expanded', 'with-collapsed', 'with-compact');
    } else {
        applyCollapsed(doc, read(win) === '1');
    }
}

/** Globals, document listener and the media-query listener — call once. */
export function installSidebar(win) {
    const doc = win.document;

    win.closeSide = () => closeSide(win);
    win.openSide = () => openSide(win);
    win.toggleSidebarCollapse = () => {
        const side = doc.getElementById('side');
        if (!side) return;
        const wasCollapsed = side.classList.contains('collapsed');
        applyCollapsed(doc, !wasCollapsed);
        write(win, wasCollapsed ? '0' : '1');
    };

    doc.addEventListener('click', (e) => {
        // the close buttons (Bootstrap's dismiss, our own) and the backdrop
        if (e.target.closest('[data-bs-dismiss="offcanvas"]') || e.target.closest('.btn-close') || e.target.closest('.btn-close-trigger')) {
            win.closeSide();
        }
        if (e.target.closest('#backdrop')) win.closeSide();

        // following a menu link on a phone closes the drawer
        const side = doc.getElementById('side');
        if (side && side.contains(e.target)) {
            const link = e.target.closest('a[href]');
            if (link && link.getAttribute('href') && !link.getAttribute('href').startsWith('#') && win.matchMedia(MOBILE).matches) {
                win.closeSide();
            }
        }
    });

    win.matchMedia(MOBILE).addEventListener?.('change', (e) => handleResize(win, e));
}

/** Apply the remembered state to the page that was just rendered — call on every page load. */
export function initSidebarState(win) {
    const saved = read(win);
    if (saved === null) {
        applyCollapsed(win.document, false);
        write(win, '0');
    } else {
        applyCollapsed(win.document, saved === '1');
    }
    handleResize(win, win.matchMedia(MOBILE));
}

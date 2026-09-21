// The app layout's behaviour (resources/js/layout/): sidebar, page-load spinner, dropdowns / TomSelect / auto-growing
// textareas and the unsaved-changes guard.  Run: node --test tests/js/
//
// The point of moving it out of layouts/app.blade.php: as inline scripts, Turbo Drive re-ran them on every visit and each
// visit stacked another copy of every document / window listener. The first tests pin that down — the set of listeners
// is registered once and does not grow — the rest pin the behaviour the inline code had, so the move changes nothing.
import { build } from 'esbuild';
import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import path from 'node:path';
import { createWorld } from './helpers/fake-dom.mjs';

const code = (await build({
  entryPoints: [path.resolve('resources/js/layout/index.js')],
  bundle: true, format: 'iife', globalName: 'Layout', write: false, logLevel: 'silent',
})).outputFiles[0].text;
const Layout = (() => { const box = {}; vm.runInNewContext(`${code}\nglobalThis.out = Layout;`, box); return box.out; })();

const EXPECTED_LISTENERS = [
  'document:DOMContentLoaded', 'document:click', 'document:click', 'document:click(capture)', 'document:submit',
  'document:turbo:load', 'window:beforeunload', 'window:pageshow',
].sort();

// A page of the app layout, the way the Blade markup lays it out.
function appPage(body, world) {
  const el = world.el;
  const ref = {};
  ref.menuLink = el('a', { href: '/assets' });
  ref.closeBtn = el('button', { class: 'btn-close' });
  ref.side = el('aside', { id: 'side', class: 'sidebar' }).append(ref.menuLink, ref.closeBtn);
  ref.backdrop = el('div', { id: 'backdrop', class: 'hidden' });
  ref.text = el('input', { type: 'text', name: 'title' });
  ref.hidden = el('input', { type: 'hidden', name: '_token' });
  ref.area = el('textarea', { class: 'overflow-hidden' }); ref.area.scrollHeight = 120;
  ref.select = el('select', { class: 'ts-basic' });
  ref.form = el('form', { method: 'post' }).append(ref.text, ref.hidden, ref.area, ref.select);
  ref.getForm = el('form', { method: 'GET' }).append(ref.getInput = el('input', { type: 'text' }));
  ref.optOut = el('form', { method: 'post', class: 'no-dirty-check' }).append(ref.optOutInput = el('input', { type: 'text' }));
  ref.leave = el('a', { href: '/maintenance/requests' });
  ref.hash = el('a', { href: '#top' });
  ref.blank = el('a', { href: '/x', target: '_blank' });
  ref.noLoader = el('a', { href: '/y', 'data-no-loader': '' });
  ref.js = el('a', { href: 'javascript:void(0)' });
  ref.chat = el('div', { id: 'chatWidgetRoot' }); ref.chat.append(ref.chatLink = el('a', { href: '/chat/x' }));
  ref.dropdown = el('button', { 'data-bs-toggle': 'dropdown' });
  ref.main = el('main', { id: 'main' }).append(ref.form, ref.getForm, ref.optOut, ref.leave, ref.hash, ref.blank, ref.noLoader, ref.js, ref.chat, ref.dropdown);
  ref.overlay = el('div', { id: 'loaderOverlay' });
  ref.layout = el('div', { id: 'layout' }).append(ref.side, ref.backdrop, ref.main);
  body.append(ref.layout, ref.overlay);
  return ref;
}

// The page is already rendered when the layout module runs (a deferred module runs after parsing), then the browser fires
// DOMContentLoaded and Turbo fires turbo:load.
function boot({ mobile = false, storage = {}, extra = {}, page = appPage, confirmAnswer = true } = {}) {
  const calls = { confirm: [], dropdowns: [], selects: [], selectOptions: [], errors: [] };
  const world = createWorld({ mobile, storage });
  world.win.bootstrap = { Dropdown: class { static getInstance(el) { return el._dd; } constructor(el, o) { el._dd = this; calls.dropdowns.push([el, o]); } } };
  world.win.TomSelect = class {
    constructor(el, options) {
      el.tomselect = this; this.h = {}; calls.selects.push(el); calls.selectOptions.push(options);
      // like the real one: the wrapper is a sibling inserted right after the <select> (not its parent), so several selects
      // can share one parent and each has a wrapper of its own
      this.wrapper = world.el('div', { class: 'ts-wrapper' });
      const kids = el.parentElement.children;
      kids.splice(kids.indexOf(el) + 1, 0, this.wrapper); this.wrapper.parentElement = el.parentElement;
    }
    on(t, f) { (this.h[t] ||= []).push(f); }
  };
  world.win.Confirm = { show: async (o) => { calls.confirm.push(o); return confirmAnswer; } };
  world.win.console = { error: (...a) => calls.errors.push(a), log() {}, warn() {} };
  Object.assign(world.win, extra);
  const state = { ref: page(world.body, world) };
  Layout.installLayout(world.win);
  world.fireDocument('DOMContentLoaded');
  world.fireDocument('turbo:load');
  world.state = state; world.calls = calls;
  world.go = (build = page) => { world.visit((body) => { state.ref = build(body, world); }); return state.ref; };
  return world;
}
const shown = (world) => world.doc.getElementById('loaderOverlay').classList.contains('show');
const settle = () => new Promise((r) => setTimeout(r, 0));

// ── the bug this move fixes ────────────────────────────────────────────────────────────────────────────────────────
test('the layout registers its document / window listeners once, and Turbo visits do not add any', () => {
  const world = boot();
  assert.deepEqual(world.listenerList(), EXPECTED_LISTENERS);
  const baseline = world.listenerList();
  for (let visit = 1; visit <= 12; visit++) {
    world.go();
    assert.deepEqual(world.listenerList(), baseline, `after visit ${visit}`);
  }
  assert.equal(world.mqls.reduce((n, m) => n + m.listeners.length, 0), 1, 'a single media-query listener, however many visits');
});

test('installing the layout a second time (or from a second module) registers nothing again', () => {
  const world = boot();
  const first = Layout.installLayout(world.win);
  const before = world.listenerList();
  assert.strictEqual(Layout.installLayout(world.win), first);
  assert.deepEqual(world.listenerList(), before);
});

test('each form, select, textarea and dropdown is enhanced once, however many times the page-load step runs', () => {
  const world = boot();
  const first = world.state.ref;
  for (let i = 0; i < 5; i++) world.fireDocument('turbo:load');
  assert.equal(first.form._listeners.length, 3, 'input, change, submit on the form');
  assert.equal(first.area._listeners.length, 1, 'one auto-resize listener');
  assert.equal(world.calls.selects.length, 1, 'one TomSelect');
  assert.equal(world.calls.dropdowns.length, 1, 'one Dropdown');
  assert.equal(first.select.tomselect.h.change.length, 1, 'one change hook on the TomSelect for the dirty check');
});

// ── the spinner ────────────────────────────────────────────────────────────────────────────────────────────────────
test('following a link shows the spinner; anchors, new tabs, opt-outs, prevented clicks and the chat widget do not', () => {
  const world = boot();
  const r = world.state.ref;
  const shownAfter = (fn) => { world.win.Loader.hide(); fn(); return shown(world); };
  assert.equal(shownAfter(() => r.menuLink.dispatch('click')), true);
  assert.equal(shownAfter(() => r.hash.dispatch('click')), false);
  assert.equal(shownAfter(() => r.blank.dispatch('click')), false);
  assert.equal(shownAfter(() => r.noLoader.dispatch('click')), false);
  assert.equal(shownAfter(() => r.chatLink.dispatch('click')), false);
  assert.equal(shownAfter(() => r.main.dispatch('click')), false, 'a click that is not on a link');
  world.doc.addEventListener('click', (e) => e.preventDefault(), true);
  assert.equal(shownAfter(() => r.menuLink.dispatch('click')), false, 'a click something else already prevented');
});

test('submitting a form shows the spinner unless it opts out; leaving the page shows it; a restored page hides it', () => {
  const world = boot();
  const r = world.state.ref;
  r.form.dispatch('submit'); assert.equal(shown(world), true);
  world.win.Loader.hide();
  r.form.setAttribute('data-no-loader', ''); r.form.dispatch('submit'); assert.equal(shown(world), false);
  world.fireWindow('beforeunload'); assert.equal(shown(world), true);
  world.fireWindow('pageshow'); assert.equal(shown(world), false);
});

test('every page load hides the spinner, and once more 600 ms later in case something left it up', () => {
  const world = boot();
  world.win.Loader.show(); world.go();
  assert.equal(shown(world), false);
  world.win.Loader.show();
  assert.ok(world.releaseTimers() > 0);           // the 600 ms safety timers
  assert.equal(shown(world), false);
});

test('the spinner stays down while the sidebar intro plays', () => {
  const world = boot();
  world.html.classList.add('intro-pending');
  world.win.Loader.show();
  assert.equal(shown(world), false);
  world.html.classList.remove('intro-pending');
  world.win.Loader.show();
  assert.equal(shown(world), true);
});

// ── the sidebar ────────────────────────────────────────────────────────────────────────────────────────────────────
test('the sidebar drawer: openSide, the close button, the backdrop and a menu link on a phone', () => {
  const world = boot({ mobile: true });
  const r = world.state.ref;
  world.win.openSide();
  assert.ok(r.side.classList.contains('open') && r.backdrop.classList.contains('show') && !r.backdrop.classList.contains('hidden'));
  assert.equal(world.body.style.overflow, 'hidden');

  r.closeBtn.dispatch('click');
  assert.ok(!r.side.classList.contains('open') && r.backdrop.classList.contains('hidden'));
  assert.equal(world.body.style.overflow, '');

  world.win.openSide(); r.backdrop.dispatch('click');
  assert.ok(!r.side.classList.contains('open'));

  world.win.openSide(); r.menuLink.dispatch('click');
  assert.ok(!r.side.classList.contains('open'), 'following a menu link on a phone closes the drawer');
});

test('on a desktop a menu link does not touch the drawer', () => {
  const world = boot({ mobile: false });
  const r = world.state.ref;
  world.win.openSide(); r.menuLink.dispatch('click');
  assert.ok(r.side.classList.contains('open'));
});

test('the collapse toggle flips the state, remembers it, and the next page comes up the same way', () => {
  const world = boot();
  assert.equal(world.storage['app.sidebar.collapsed'], '0', 'first visit records "expanded"');
  assert.ok(world.body.classList.contains('with-expanded'));

  world.win.toggleSidebarCollapse();
  assert.equal(world.storage['app.sidebar.collapsed'], '1');
  assert.ok(world.state.ref.side.classList.contains('collapsed') && world.body.classList.contains('with-collapsed'));

  const next = world.go();                          // Turbo replaces the body — the saved state is applied to the new one
  assert.ok(next.side.classList.contains('collapsed'));
  world.win.toggleSidebarCollapse();
  assert.equal(world.storage['app.sidebar.collapsed'], '0');
  assert.ok(!next.side.classList.contains('collapsed') && world.body.classList.contains('with-expanded'));
});

test('crossing the phone breakpoint drops the desktop classes; back on a desktop the saved state returns', () => {
  const world = boot({ storage: { 'app.sidebar.collapsed': '1' } });
  const change = world.mqls.find((m) => m.listeners.length).listeners[0];
  world.state.ref.layout.classList.add('with-collapsed');
  change({ matches: true });
  assert.ok(!world.state.ref.layout.classList.contains('with-collapsed'));
  change({ matches: false });
  assert.ok(world.state.ref.side.classList.contains('collapsed'));
});

test('blocked localStorage does not stop the layout from starting', () => {
  const world = boot({ extra: { localStorage: { getItem() { throw new Error('blocked'); }, setItem() { throw new Error('blocked'); } } } });
  world.state.ref.menuLink.dispatch('click');
  assert.equal(shown(world), true);
  assert.deepEqual(world.calls.errors, []);
});

// ── unsaved changes ────────────────────────────────────────────────────────────────────────────────────────────────
test('after editing a form, following a link asks first and stays on the page until confirmed', async () => {
  const world = boot({ confirmAnswer: false });
  const r = world.state.ref;
  r.text.dispatch('input');
  assert.ok(r.text.classList.contains('is-dirty-field'));

  const ev = r.leave.dispatch('click');
  await settle();
  assert.equal(ev.defaultPrevented, true);
  assert.equal(world.calls.confirm.length, 1);
  assert.equal(world.calls.confirm[0].title, 'ยืนยันการออกหน้าปัจจุบัน');
  assert.equal(world.calls.confirm[0].variant, 'warning');
  assert.equal(world.win.location.href, 'https://app.test/current?page=3', 'declined: still here');
  assert.equal(shown(world), false, 'the click never reached the spinner');
});

test('while it asks, the click goes no further — on a phone the drawer stays open behind the dialog', async () => {
  const world = boot({ mobile: true, confirmAnswer: false });
  const r = world.state.ref;
  world.win.openSide();
  r.text.dispatch('input');
  r.menuLink.dispatch('click');                       // a menu link inside the drawer
  await settle();
  assert.ok(r.side.classList.contains('open'), 'the sidebar\'s own click handler must not run');
  assert.equal(world.calls.confirm.length, 1);
});

test('confirming leaves through the link, and then the next click does not ask again', async () => {
  const world = boot({ confirmAnswer: true });
  const r = world.state.ref;
  r.area.dispatch('change');
  r.leave.dispatch('click');
  await settle();
  assert.equal(world.win.location.href, '/maintenance/requests');
  r.leave.dispatch('click'); await settle();
  assert.equal(world.calls.confirm.length, 1);
});

test('nothing is asked when nothing was edited, or for anchors, javascript: links, GET forms and opted-out forms', async () => {
  const world = boot();
  const r = world.state.ref;
  assert.equal(r.leave.dispatch('click').defaultPrevented, false, 'clean page');

  r.getInput.dispatch('input'); r.optOutInput.dispatch('input'); r.hidden.dispatch('input');
  assert.equal(r.leave.dispatch('click').defaultPrevented, false, 'GET form / no-dirty-check form / hidden field');

  r.text.dispatch('input');
  assert.equal(r.hash.dispatch('click').defaultPrevented, false);
  assert.equal(r.js.dispatch('click').defaultPrevented, false);
  assert.equal(r.leave.dispatch('click').defaultPrevented, true, 'a real edit does');
});

test('submitting the form clears the warning; a new page starts clean', async () => {
  const world = boot();
  const r = world.state.ref;
  r.text.dispatch('input'); r.form.dispatch('submit');
  assert.equal(r.leave.dispatch('click').defaultPrevented, false, 'saved');

  r.text.dispatch('input');
  const next = world.go();                          // Turbo visit while dirty (e.g. the back button): the new page is clean
  assert.equal(next.leave.dispatch('click').defaultPrevented, false);
});

test('a TomSelect change counts as an edit and marks its wrapper', () => {
  const world = boot();
  const r = world.state.ref;
  r.select.tomselect.h.change[0]();
  assert.ok(r.select.tomselect.wrapper.classList.contains('is-dirty-field'));
  assert.equal(r.leave.dispatch('click').defaultPrevented, true);
});

// The request form has the asset and the department selects in ONE <section>. The change hook was looked up as "the first select
// in the wrapper's parent", which is the asset select for both wrappers: the department was never bound, so it never turned
// yellow (only the first select of a parent did).
test('every TomSelect in a form gets its own change hook, even when several share one parent', () => {
  const world = boot();
  const page = world.go((body, w) => {
    const asset = w.el('select', { class: 'ts-basic', name: 'asset_id' });
    const dept = w.el('select', { class: 'ts-basic', name: 'department_id' });
    const type = w.el('select', { class: 'ts-basic', name: 'type_id' });
    const link = w.el('a', { href: '/elsewhere' });
    const form = w.el('form', { method: 'post' }).append(w.el('section').append(asset).append(dept), w.el('section').append(type), link);
    body.append(w.el('div', { id: 'layout' }).append(form));
    return { asset, dept, type, link };
  });

  for (const sel of [page.asset, page.dept, page.type]) {
    assert.equal(sel.tomselect.h.change.length, 1, 'one hook each');
    assert.ok(!sel.tomselect.wrapper.classList.contains('is-dirty-field'), 'clean at the start');
  }

  page.dept.tomselect.h.change[0]();
  assert.ok(page.dept.tomselect.wrapper.classList.contains('is-dirty-field'), 'the department turns yellow');
  assert.ok(!page.asset.tomselect.wrapper.classList.contains('is-dirty-field'), 'and only it');
  assert.ok(!page.type.tomselect.wrapper.classList.contains('is-dirty-field'));
  assert.equal(page.link.dispatch('click').defaultPrevented, true, 'and it counts as an edit');

  world.fireDocument('turbo:load');   // the page-load step runs again: nothing is bound twice
  assert.equal(page.dept.tomselect.h.change.length, 1);
});

test('without the confirm dialog the browser confirm() is used', () => {
  const world = boot({ extra: { Confirm: undefined, confirm: () => false } });
  const r = world.state.ref;
  r.text.dispatch('input');
  assert.equal(r.leave.dispatch('click').defaultPrevented, true);
  assert.equal(world.win.location.href, 'https://app.test/current?page=3');
});

// ── enhancements ───────────────────────────────────────────────────────────────────────────────────────────────────
test('textareas grow to their content, selects get TomSelect with a placeholder, dropdowns close on outside clicks only', () => {
  const world = boot();
  const r = world.state.ref;
  r.area.dispatch('input');
  assert.equal(r.area.style.height, '120px');
  assert.equal(world.calls.selects[0], r.select);
  assert.equal(world.calls.selectOptions[0].placeholder, '— ไม่ระบุ —', 'the default placeholder');
  assert.equal(world.calls.selectOptions[0].maxOptions, 2000);
  assert.equal(world.calls.dropdowns[0][1].autoClose, 'outside');

  const custom = world.go((body, w) => { const s = w.el('select', { class: 'ts-department', 'data-placeholder': 'เลือกแผนก' }); body.append(w.el('div', { id: 'layout' }).append(s)); return { s }; });
  assert.equal(world.calls.selectOptions[1].placeholder, 'เลือกแผนก', 'data-placeholder wins');
  assert.equal(world.calls.selects[1], custom.s);
});

// The empty option of a form select ("— ไม่ระบุ —") means "nothing chosen". `allowEmptyOption: true` made TomSelect treat it as a
// chosen value, so its words stayed in the field like real text (and stayed when you typed to search). It has to be the
// placeholder — grey, and gone as soon as you type. Getting back to "not specified" is a first row of the list (as it always was),
// not a × inside the field: choosing that row clears the field, which then shows its placeholder again.
test('an empty option is a placeholder, not a chosen value; a first row of the list takes a value back — no clear button', () => {
  const world = boot();
  const page = world.go((body, w) => {
    const emptyOption = (text) => { const o = w.el('option', { value: '' }); o.textContent = text; return o; };

    const named = w.el('select', { class: 'ts-basic', 'data-placeholder': '— เลือกทรัพย์สิน —' }); named.append(emptyOption('— ไม่ระบุ —'));
    const fromOption = w.el('select', { class: 'ts-basic' }); fromOption.append(emptyOption('— เลือกหมวดหมู่ —'));
    const bare = w.el('select', { class: 'ts-basic' });
    const must = w.el('select', { class: 'ts-basic', required: '' }); must.append(emptyOption('— เลือกบทบาท —'));

    body.append(w.el('div', { id: 'layout' }).append(named).append(fromOption).append(bare).append(must));
    return { named, fromOption, bare, must };
  });
  const opt = (sel) => world.calls.selectOptions[world.calls.selects.indexOf(sel)];
  // what TomSelect does with the callbacks: onInitialize once the widget is built, onItemAdd for every chosen row
  const run = (sel) => {
    const seen = { added: [], cleared: 0 };
    const ts = { addOption: (o) => seen.added.push(o), clear: () => { seen.cleared++; } };
    opt(sel).onInitialize.call(ts);
    return { seen, choose: (value) => opt(sel).onItemAdd.call(ts, value) };
  };

  for (const sel of [page.named, page.fromOption, page.bare, page.must]) {
    assert.notEqual(opt(sel).allowEmptyOption, true, 'the empty option is not a value to display');
    assert.equal(opt(sel).plugins, undefined, 'no clear (×) button inside the field');
  }

  assert.equal(opt(page.named).placeholder, '— เลือกทรัพย์สิน —', 'data-placeholder wins');
  assert.equal(opt(page.fromOption).placeholder, '— เลือกหมวดหมู่ —', 'else the words of the empty option');
  assert.equal(opt(page.bare).placeholder, '— ไม่ระบุ —', 'else the default');

  // an optional select with an empty option gets that option back as the first row of the list
  const named = run(page.named);
  assert.equal(named.seen.added.length, 1);
  assert.equal(named.seen.added[0].text, '— ไม่ระบุ —', 'the words of its empty option');
  assert.equal(named.seen.added[0].value, '__none__');
  assert.equal(opt(page.named).sortField[0].field, 'noneFirst', 'sorted before the alphabetical rest');
  assert.equal(named.seen.added[0].noneFirst, 0);
  named.choose('42');
  assert.equal(named.seen.cleared, 0, 'a real value stays');
  named.choose('__none__');
  assert.equal(named.seen.cleared, 1, 'the row is a way out: choosing it clears the field, it is never a value');

  assert.equal(run(page.fromOption).seen.added[0].text, '— เลือกหมวดหมู่ —');
  assert.equal(run(page.bare).seen.added.length, 0, 'nothing to go back to when there is no empty option');
  assert.equal(run(page.must).seen.added.length, 0, 'and a required field cannot be emptied');
});

// The asset options carry their HIS number ("AST-001 - name (รพจ. 6500123)", `data-his` on the <option>). The text stays whole — that is
// what the picker searches — but the HIS part is drawn in the colour it has on the asset table (font-semibold text-blue-700).
test('the HIS part of an asset option is drawn in the table colour; the text stays whole for the search', () => {
  const world = boot();
  const page = world.go((body, w) => {
    const withHis = w.el('select', { class: 'ts-basic' });
    withHis.append(w.el('option', { value: '1', 'data-his': '6500123' }));
    const plain = w.el('select', { class: 'ts-basic' });
    plain.append(w.el('option', { value: '2' }));
    body.append(w.el('div', { id: 'layout' }).append(withHis).append(plain));
    return { withHis, plain };
  });
  const opt = (sel) => world.calls.selectOptions[world.calls.selects.indexOf(sel)];
  const escape = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const cfg = opt(page.withHis);
  assert.equal(cfg.searchField.join(','), 'text', 'the whole text is searched, HIS number included');
  assert.equal(typeof cfg.render.option, 'function');
  assert.equal(cfg.render.item, cfg.render.option, 'the chosen value looks the same as the list');

  const html = cfg.render.option({ text: '\n   AST-001 - เครื่องวัดความดัน (รพจ. 6500123)\n ', his: '6500123' }, escape);
  assert.equal(html, '<div>AST-001 - เครื่องวัดความดัน<span class="text-blue-700 font-semibold"> (รพจ. 6500123)</span></div>');

  assert.equal(cfg.render.option({ text: '<b>x</b> - y (รพจ. 1)', his: '1' }, escape),
    '<div>&lt;b&gt;x&lt;/b&gt; - y<span class="text-blue-700 font-semibold"> (รพจ. 1)</span></div>', 'text is escaped');
  assert.equal(cfg.render.option({ text: 'AST-002 - no his here' }, escape), '<div>AST-002 - no his here</div>', 'an option without one is plain');
  assert.equal(cfg.render.option({ text: 'AST-003 - other text', his: '9' }, escape), '<div>AST-003 - other text</div>', 'never cuts what is not the suffix');

  assert.equal(opt(page.plain).render, undefined, 'selects without a HIS number keep the default rendering');
});

test('window.initTomSelect / initAutoResize stay available to page scripts', () => {
  const world = boot();
  assert.equal(typeof world.win.initTomSelect, 'function');
  assert.equal(typeof world.win.initAutoResize, 'function');
  assert.equal(typeof world.win.Loader.show, 'function');
});

// ── robustness ─────────────────────────────────────────────────────────────────────────────────────────────────────
test('a page in another layout (login rendered by Turbo) is left alone and its forms are not watched', async () => {
  const world = boot();
  world.state.ref.text.dispatch('input');
  const login = world.go((body, w) => {
    const form = w.el('form', { method: 'post' }).append(w.el('input', { type: 'text' }));
    body.append(form, w.el('a', { href: '/forgot-password' }));
    return { form };
  });
  assert.equal(login.form._listeners.length, 0);
  assert.equal(world.calls.selects.length, 1, 'no new TomSelect');
  assert.equal(world.doc.querySelector('a').dispatch('click').defaultPrevented, false, 'no unsaved-changes prompt on the login page');
});

test('one enhancement failing does not stop the others', () => {
  const world = boot({ extra: {} });
  world.win.TomSelect = class { constructor() { throw new Error('bad markup'); } };
  const next = world.go();
  assert.equal(world.calls.errors.length >= 1, true, 'the error is reported');
  assert.equal(next.form._listeners.length, 3, 'the dirty check still started');
  assert.equal(next.area._listeners.length, 1, 'the textarea still auto-resizes');
});

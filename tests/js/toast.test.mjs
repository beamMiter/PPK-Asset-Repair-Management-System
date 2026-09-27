// Toast notifications (resources/js/toast.js).  Run: node --test tests/js/
//
// As an inline script in components/toast.blade.php it was re-run by Turbo Drive on every visit. Now the module registers
// its `document` / `window` listeners once, and these tests pin that plus the behaviour the inline code had: what a toast
// looks like, when it closes, the session toast that waits for the sidebar intro, and that text stays text.
import { build } from 'esbuild';
import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import path from 'node:path';
import { createWorld } from './helpers/fake-dom.mjs';

const code = (await build({
  entryPoints: [path.resolve('resources/js/toast.js')],
  bundle: true, format: 'iife', globalName: 'Toast', write: false, logLevel: 'silent',
})).outputFiles[0].text;
const Toast = (() => { const box = {}; vm.runInNewContext(`${code}\nglobalThis.out = Toast;`, box); return box.out; })();

const EXPECTED = ['document:DOMContentLoaded', 'document:keydown', 'document:turbo:load', 'window:app:toast'].sort();

// what components/toast.blade.php renders (plus, optionally, the carrier layouts/app puts in <main> for a flashed toast)
function page(body, world, { flash = null } = {}) {
  const overlay = world.el('div', { class: 'toast-overlay' });
  body.append(overlay);
  if (flash) body.append(Object.assign(world.el('script', { id: 'session-toast-data', type: 'application/json' }), { textContent: JSON.stringify(flash) }));
  return overlay;
}

function boot(opts = {}) {
  const world = createWorld();
  const errors = [];
  world.win.console = { error: (...a) => errors.push(a), log() {}, warn() {} };
  world.errors = errors;
  world.overlay = page(world.body, world, opts);
  world.toast = Toast.installToast(world.win);
  world.fireDocument('DOMContentLoaded');
  world.fireDocument('turbo:load');
  world.cards = () => world.doc.querySelectorAll('.toast-card');
  world.settle = () => world.flushFrames();
  return world;
}

const text = (card, cls) => card.querySelector(cls).textContent;

// ── registration ───────────────────────────────────────────────────────────────────────────────────────────────────
test('the listeners are registered once, and neither Turbo visits nor showing toasts add any', () => {
  const world = boot();
  assert.deepEqual(world.listenerList(), EXPECTED);
  for (let i = 0; i < 12; i++) {
    world.visit((body, w) => page(body, w));
    world.toast.show({ message: 'x' });
    assert.deepEqual(world.listenerList(), EXPECTED, `after visit ${i + 1}`);
  }
});

test('installing twice registers nothing more', () => {
  const world = boot();
  const before = world.listenerList();
  assert.strictEqual(Toast.installToast(world.win), world.toast);
  assert.deepEqual(world.listenerList(), before);
});

// ── the card ───────────────────────────────────────────────────────────────────────────────────────────────────────
test('a toast is a card of the right size and type with a default title, in the top-right stack', () => {
  const world = boot();
  world.win.showToast({ type: 'success', message: 'บันทึกแล้ว', size: 'md' });
  world.settle();
  const [card] = world.cards();
  assert.equal(card.className, 'toast-card toast--md toast--success show');
  assert.equal(card.getAttribute('role'), 'status');
  assert.equal(text(card, '.toast-title'), 'สำเร็จ');
  assert.equal(text(card, '.toast-msg'), 'บันทึกแล้ว');
  assert.ok(world.overlay.querySelector('.toast-pos').classList.contains('tr'), 'the position is forced to top-right');
  assert.equal(card.querySelector('.toast-fill').style.width, '100%', 'the countdown bar runs');
  assert.equal(card.querySelector('.toast-fill').style.transition, 'width 3800ms linear');
});

test('the four types have their own default titles; unknown types and sizes fall back; a custom title wins', () => {
  const world = boot();
  const titles = {};
  for (const type of ['success', 'error', 'warning', 'info']) { world.win.showToast({ type }); }
  for (const card of world.cards()) titles[card.className.match(/toast--(success|error|warning|info)/)[1]] = text(card, '.toast-title');
  assert.deepEqual(titles, { success: 'สำเร็จ', error: 'เกิดข้อผิดพลาด', warning: 'โปรดตรวจสอบ', info: 'แจ้งเตือน' });

  world.win.showToast({ type: 'bogus', size: 'huge', title: 'หัวข้อเอง' });
  const last = world.cards().at(-1);
  assert.match(last.className, /toast--lg toast--info/);
  assert.equal(text(last, '.toast-title'), 'หัวข้อเอง');
});

test('a timeout under 800 ms (or not a number) becomes 3.8 s', () => {
  const world = boot();
  world.win.showToast({ timeout: 100 }); world.win.showToast({ timeout: 'soon' }); world.win.showToast({ timeout: 5000 });
  world.settle();
  assert.deepEqual(world.cards().map((c) => c.querySelector('.toast-fill').style.transition), ['width 3800ms linear', 'width 3800ms linear', 'width 5000ms linear']);
});

test('what a caller passes is shown as text, never parsed as HTML', () => {
  const world = boot();
  const evil = '<img src=x onerror="alert(1)"><script>alert(2)</script>';
  world.win.showToast({ message: evil, title: evil });
  const [card] = world.cards();
  assert.equal(text(card, '.toast-msg'), evil);
  assert.equal(text(card, '.toast-title'), evil);
  assert.equal(card.querySelector('.toast-msg').children.length, 0);
  assert.equal(card.querySelectorAll('img').length + card.querySelectorAll('script').length, 0);
});

test('the app:toast event shows a toast; without a detail it still shows an empty info toast', () => {
  const world = boot();
  world.fireWindow('app:toast', { detail: { type: 'error', message: 'พัง' } });
  world.fireWindow('app:toast');
  const [a, b] = world.cards();
  assert.equal(text(a, '.toast-msg'), 'พัง'); assert.match(a.className, /toast--error/);
  assert.equal(text(b, '.toast-msg'), ''); assert.match(b.className, /toast--info/);
});

test('with no overlay on the page (a layout without toasts) nothing happens and nothing throws', () => {
  const world = boot();
  world.visit(() => {});
  assert.doesNotThrow(() => world.win.showToast({ message: 'x' }));
  assert.equal(world.cards().length, 0);
});

// ── closing ────────────────────────────────────────────────────────────────────────────────────────────────────────
test('a toast closes itself after its timeout, fades, and is removed', () => {
  const world = boot();
  world.win.showToast({ timeout: 3000 });
  world.settle();
  const [card] = world.cards();
  world.advance(3059);
  assert.ok(card.classList.contains('show'), 'still up just before the timeout');
  world.advance(1);
  assert.ok(card.classList.contains('hide') && !card.classList.contains('show'));
  world.advance(200);
  assert.equal(world.cards().length, 0);
  assert.equal(world.toast.open.size, 0);
});

test('the close button closes; Escape closes every open toast; other keys do not', () => {
  const world = boot();
  world.win.showToast({ message: 'a' }); world.win.showToast({ message: 'b' }); world.win.showToast({ message: 'c' });
  world.settle();
  world.fireDocument('keydown', { key: 'Enter' });
  assert.equal(world.toast.open.size, 3);

  world.cards()[0].querySelector('.toast-close').dispatch('click');
  assert.equal(world.toast.open.size, 2);

  world.fireDocument('keydown', { key: 'Escape' });
  assert.equal(world.toast.open.size, 0);
  world.advance(200);
  assert.equal(world.cards().length, 0);
  assert.doesNotThrow(() => world.fireDocument('keydown', { key: 'Escape' }));   // nothing left to close
});

test('a toast that closed on its own is not kept alive by an Escape listener', () => {
  const world = boot();
  for (let i = 0; i < 20; i++) { world.win.showToast({ timeout: 800 }); world.advance(1000); }
  assert.equal(world.toast.open.size, 0);
  assert.deepEqual(world.listenerList(), EXPECTED);
});

test('hovering a toast pauses its countdown; leaving it resumes with the time that was left', () => {
  const world = boot();
  world.win.showToast({ timeout: 3000 });
  world.settle();
  const [card] = world.cards();
  card.dispatch('mouseenter');
  world.advance(60_000);
  assert.ok(card.classList.contains('show'), 'paused while hovered');
  card.dispatch('mouseleave');
  world.advance(3100);
  assert.ok(card.classList.contains('hide'), 'closes after the remaining time');
});

// ── the flashed (session) toast ────────────────────────────────────────────────────────────────────────────────────
test('a flashed toast is shown once on the page that carries it, and the carrier is removed', () => {
  const world = boot({ flash: { type: 'success', message: 'บันทึกเรียบร้อย' } });
  assert.equal(world.cards().length, 1);
  assert.equal(text(world.cards()[0], '.toast-msg'), 'บันทึกเรียบร้อย');
  assert.equal(world.doc.getElementById('session-toast-data'), null);
  world.fireDocument('turbo:load'); world.fireDocument('DOMContentLoaded');
  assert.equal(world.cards().length, 1, 'DOMContentLoaded, turbo:load and the immediate check all look at it, once');
});

test('the next page\'s flashed toast is shown on that page; a page without one shows nothing', () => {
  const world = boot({ flash: { message: 'one' } });
  world.visit((body, w) => page(body, w, { flash: { type: 'error', message: 'two' } }));
  assert.deepEqual(world.cards().map((c) => text(c, '.toast-msg')), ['two']);
  world.visit((body, w) => page(body, w));
  assert.equal(world.cards().length, 0);
});

test('during the sidebar intro the flashed toast waits, then shows once — however the two triggers race', () => {
  const world = createWorld();
  world.win.console = { error() {}, log() {} };
  world.html.classList.add('intro-pending');
  world.overlay = page(world.body, world, { flash: { message: 'ยินดีต้อนรับ', timeout: 60000 } }); // stays up, so a second show would be visible
  Toast.installToast(world.win);
  const cards = () => world.doc.querySelectorAll('.toast-card');
  world.advance(4000);
  assert.equal(cards().length, 0, 'not while the intro plays');

  world.html.classList.remove('intro-pending');
  world.mutate();                                  // sidebar-intro.js finished
  world.advance(300);
  assert.equal(cards().length, 1);
  world.advance(10_000);                           // the 5 s fallback must have been cancelled
  assert.equal(cards().length, 1, 'shown once');
});

test('if the intro never reports, the flashed toast shows after 5 s anyway', () => {
  const world = createWorld();
  world.html.classList.add('intro-pending');
  page(world.body, world, { flash: { message: 'x', timeout: 60000 } });
  Toast.installToast(world.win);
  world.advance(4999);
  assert.equal(world.doc.querySelectorAll('.toast-card').length, 0);
  world.advance(1);
  assert.equal(world.doc.querySelectorAll('.toast-card').length, 1);
  world.mutate(); world.advance(1000);
  assert.equal(world.doc.querySelectorAll('.toast-card').length, 1, 'a late intro-finished does not show it again');
});

test('a carrier with broken JSON is reported and does not stop anything', () => {
  const world = createWorld();
  const errors = [];
  world.win.console = { error: (...a) => errors.push(a), log() {} };
  world.overlay = page(world.body, world);
  world.body.append(Object.assign(world.el('script', { id: 'session-toast-data' }), { textContent: '{not json' }));
  assert.doesNotThrow(() => Toast.installToast(world.win));
  assert.equal(errors.length, 1);
});

// The floating chat button and its drawer (resources/js/layout/chat-fab.js).  Run: node --test tests/js/
//
// Two things were wrong with the inline script it replaces:
//  - every Turbo visit re-ran it and started ANOTHER `setInterval`, so after N pages the browser polled /chat/my-updates
//    N times per interval;
//  - it put thread titles, senders and message text — typed by other users — into `innerHTML`, so anyone who could post in
//    a thread ran script in the browser of everyone who took part in it, on every page (the widget is on all of them).
import { build } from 'esbuild';
import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import path from 'node:path';
import { createWorld } from './helpers/fake-dom.mjs';

const code = (await build({
  entryPoints: [path.resolve('resources/js/layout/chat-fab.js')],
  bundle: true, format: 'iife', globalName: 'Fab', write: false, logLevel: 'silent',
})).outputFiles[0].text;
const Fab = (() => { const box = {}; vm.runInNewContext(`${code}\nglobalThis.out = Fab;`, box); return box.out; })();

const settle = () => new Promise((r) => setImmediate(r));

const item = (over = {}) => ({ id: 1, title: 'เครื่องพิมพ์ชั้น 3', show_url: '/chat/threads/1', unread: 0, last_user_name: 'สมชาย', last_user_avatar: null, last_body: 'ได้แล้วครับ', last_created_at: '2026-09-21T08:00:00+07:00', ...over });

// what partials/chat-fab.blade.php renders for a signed-in user
function fabPage(body, world) {
  const el = world.el; const r = {};
  r.fab = el('button', { id: 'chatFab' });
  r.badge = el('span', { id: 'chatBadge', class: 'hidden' });
  r.close = el('button', { id: 'chatClose' });
  r.search = el('input', { id: 'chatSearch' }); r.search.value = '';
  r.list = el('div', { id: 'chatList' });
  r.drawer = el('div', { id: 'chatDrawer' }).append(r.close, r.search, r.list);
  r.audio = el('audio', { id: 'chatNotifySound' }); r.audio.plays = 0; r.audio.play = () => { r.audio.plays++; return Promise.resolve(); };
  r.root = el('div', { id: 'chatWidgetRoot' }).append(r.fab, r.badge, r.drawer, r.audio);
  r.root.dataset.updatesUrl = '/chat/my-updates'; r.root.dataset.notifyIcon = '/images/logoppk.png';
  body.append(r.root);
  return r;
}

/** `answers`: what the server says on each poll, in order — an array of items, or a function/Error for failures. */
function boot({ answers = [[]], page = fabPage, storage = {}, session = {}, notification = 'default', sound = false } = {}) {
  const world = createWorld({ storage });
  const fetches = [], notifications = [], permissionAsked = [];
  const queue = [...answers];
  world.win.fetch = async (url, init) => {
    fetches.push([url, init]);
    const a = queue.length > 1 ? queue.shift() : queue[0];
    if (a instanceof Error) throw a;
    if (typeof a === 'number') return { ok: false, status: a, json: async () => ({}) };
    return { ok: true, status: 200, json: async () => a };
  };
  const errors = [];
  world.win.console = { error: (...x) => errors.push(x), warn() {}, log() {} };
  world.win.sessionStorage = { getItem: (k) => (k in session ? session[k] : null), setItem: (k, v) => { session[k] = String(v); } };
  if (sound) world.storage['myjobs.notify.sound.enabled'] = '1';
  if (notification) {
    world.win.Notification = class { constructor(title, o) { notifications.push([title, o]); } static requestPermission() { permissionAsked.push(true); } };
    world.win.Notification.permission = notification;
  }
  Object.assign(world, { fetches, notifications, permissionAsked, errors, session, queue });
  world.ref = page(world.body, world);
  world.handle = Fab.installChatFab(world.win);
  world.go = async (build = page) => { world.visit((body) => { world.ref = build(body, world); }); await settle(); return world.ref; };
  return world;
}

const rows = (world) => world.ref.list.children;

// ── the timer ──────────────────────────────────────────────────────────────────────────────────────────────────────
test('one poll timer for the whole session: N visits still mean one poll per interval, not N', async () => {
  const world = boot();
  await settle();
  for (let i = 0; i < 10; i++) await world.go();
  assert.equal(world.pendingTimers(), 1, 'a single interval, however many pages were opened');

  const before = world.fetches.length;                 // the first load and each visit polled once
  assert.equal(before, 11);
  world.advance(Fab.POLL_MS);
  await settle();
  assert.equal(world.fetches.length - before, 1);
});

test('the widget polls every 30 seconds, and asks the endpoint the page names', async () => {
  const world = boot();
  await settle();
  const before = world.fetches.length;
  world.advance(Fab.POLL_MS - 1); await settle();
  assert.equal(world.fetches.length, before);
  world.advance(1); await settle();
  assert.equal(world.fetches.length, before + 1);
  assert.equal(world.fetches.at(-1)[0], '/chat/my-updates');
  assert.equal(world.fetches.at(-1)[1].headers.Accept, 'application/json');
  assert.equal(Fab.POLL_MS, 30000);
});

test('listeners: only turbo:load and DOMContentLoaded on the document, none added by visits', async () => {
  const world = boot();
  const before = world.listenerList();
  assert.deepEqual(before, ['document:DOMContentLoaded', 'document:turbo:load']);
  for (let i = 0; i < 8; i++) await world.go();
  assert.deepEqual(world.listenerList(), before);
});

test('installing twice does nothing more; the first load, DOMContentLoaded and turbo:load wire the widget once', async () => {
  const world = boot();
  assert.strictEqual(Fab.installChatFab(world.win), world.handle);
  world.fireDocument('DOMContentLoaded'); world.fireDocument('turbo:load'); world.fireDocument('turbo:load');
  await settle();
  assert.equal(world.ref.fab._listeners.length, 1);
  assert.equal(world.ref.search._listeners.length, 1);
  assert.equal(world.fetches.length, 1, 'one poll for one rendered widget');
});

test('a page without the widget (login) is left alone, and the timer polls nothing there', async () => {
  const world = boot();
  await settle();
  await world.go((body, w) => { body.append(w.el('div')); return {}; });
  const before = world.fetches.length;
  world.advance(Fab.POLL_MS * 3); await settle();
  assert.equal(world.fetches.length, before);
  await world.go();                                     // and it comes back with the next page that has one
  assert.equal(world.fetches.length, before + 1);
});

// ── the drawer ─────────────────────────────────────────────────────────────────────────────────────────────────────
test('the button opens and closes the drawer; the close button closes it; it starts closed', async () => {
  const world = boot();
  await settle();
  const { fab, close, drawer } = world.ref;
  assert.ok(drawer.classList.contains('pointer-events-none') && drawer.hasAttribute('inert') && drawer.getAttribute('aria-hidden') === 'true');
  fab.dispatch('click');
  assert.ok(drawer.classList.contains('opacity-100') && !drawer.hasAttribute('inert') && drawer.getAttribute('aria-hidden') === 'false');
  fab.dispatch('click');
  assert.ok(drawer.classList.contains('opacity-0'));
  fab.dispatch('click'); close.dispatch('click');
  assert.ok(drawer.hasAttribute('inert'));
});

test('the list shows at most ten threads, with the unread pill capped at 99+ and the sender before the text', async () => {
  const many = Array.from({ length: 14 }, (_, i) => item({ id: i, title: `กระทู้ ${i}`, show_url: `/chat/threads/${i}`, unread: i === 0 ? 120 : i === 1 ? 3 : 0 }));
  const world = boot({ answers: [many] });
  await settle();
  assert.equal(rows(world).length, 10);
  const first = rows(world)[0];
  assert.equal(first.href, '/chat/threads/0');
  assert.ok(first.textContent.includes('ใหม่ 99+'));
  assert.ok(rows(world)[1].textContent.includes('ใหม่ 3'));
  assert.ok(rows(world)[2].textContent.includes('สมชาย: ได้แล้วครับ'));
  assert.ok(!rows(world)[2].textContent.includes('ใหม่'));
  assert.ok(rows(world).every((a) => a.hasAttribute('data-no-loader')), 'opening a thread from the drawer skips the page spinner');
});

test('the search box filters by title, sender and message text', async () => {
  const world = boot({ answers: [[item({ id: 1, title: 'เครื่องพิมพ์' }), item({ id: 2, title: 'เน็ตช้า', last_user_name: 'Wanna', last_body: 'switch ชั้น 2' })]] });
  await settle();
  const type = (v) => { world.ref.search.value = v; world.ref.search.dispatch('input'); };
  type('เน็ต'); assert.equal(rows(world).length, 1);
  type('wanna'); assert.equal(rows(world).length, 1);
  type('SWITCH'); assert.equal(rows(world).length, 1);
  type('ไม่มี'); assert.ok(world.ref.list.innerHTML.includes('ยังไม่มีกระทู้'));
  type(''); assert.equal(rows(world).length, 2);
});

test('the badge shows the total unread (99+ at most), hides at zero, and opening the drawer clears it', async () => {
  const world = boot({ answers: [[item({ unread: 2 }), item({ id: 2, unread: 3 })], [item({ unread: 0 })], [item({ unread: 150 })]] });
  await settle();
  const badge = world.ref.badge;
  assert.equal(badge.textContent, '5'); assert.ok(!badge.classList.contains('hidden'));
  world.ref.fab.dispatch('click');
  assert.ok(badge.classList.contains('hidden') && badge.textContent === '', 'opened: cleared');
  world.advance(Fab.POLL_MS); await settle();
  assert.ok(badge.classList.contains('hidden'), 'server says nothing unread');
  world.advance(Fab.POLL_MS); await settle();
  assert.equal(badge.textContent, '99+');
});

// ── text from other users stays text ───────────────────────────────────────────────────────────────────────────────
test('a title, sender or message containing HTML is shown as text and creates no elements', async () => {
  const evil = '<img src=x onerror="alert(document.cookie)"><script>alert(1)</script>';
  const world = boot({ answers: [[item({ title: evil, last_user_name: `<b onclick="x()">${evil}</b>`, last_body: evil, last_user_avatar: null })]] });
  await settle();
  const [a] = rows(world);
  assert.ok(a.textContent.includes(evil), 'the raw text is what the user sees');
  assert.equal(a.querySelectorAll('img').length, 0, 'no <img> was created from the title');
  assert.equal(a.querySelectorAll('script').length + a.querySelectorAll('b').length, 0);
  const everyNode = [a, ...a.querySelectorAll('*')];
  assert.ok(everyNode.every((n) => n.innerHTML === ''), 'nothing user-typed went through innerHTML, on the row or inside it');
  assert.equal(world.ref.list.innerHTML, '', 'nothing user-typed went through innerHTML');
});

test('an avatar URL is set as a URL, not parsed into markup', async () => {
  const url = '"><script>alert(1)</script>';
  const world = boot({ answers: [[item({ last_user_avatar: url })]] });
  await settle();
  const img = rows(world)[0].querySelector('img');
  assert.equal(img.src, url);
  assert.equal(rows(world)[0].querySelectorAll('script').length, 0);
});

test('without an avatar the thread\'s first letter is shown', async () => {
  const world = boot({ answers: [[item({ title: 'abc' }), item({ id: 2, title: '' })]] });
  await settle();
  assert.equal(rows(world)[0].querySelector('span').textContent, 'A');
  assert.equal(rows(world)[1].textContent.includes('Untitled'), true);
});

// ── when it rings ──────────────────────────────────────────────────────────────────────────────────────────────────
test('the first poll in a tab never rings; a later rise does — the sound only if the user turned it on', async () => {
  const world = boot({ answers: [[item({ unread: 1 })], [item({ unread: 1 })], [item({ unread: 4 })]], sound: true });
  await settle();
  assert.equal(world.ref.audio.plays, 0, 'first poll: baseline only');
  world.advance(Fab.POLL_MS); await settle();
  assert.equal(world.ref.audio.plays, 0, 'unchanged');
  world.advance(Fab.POLL_MS); await settle();
  assert.equal(world.ref.audio.plays, 1, 'went up');

  const quiet = boot({ answers: [[item({ unread: 1 })], [item({ unread: 5 })]], sound: false });
  await settle(); quiet.advance(Fab.POLL_MS); await settle();
  assert.equal(quiet.ref.audio.plays, 0, 'sound is off');
});

test('the baseline survives Turbo visits and a reload of the tab (sessionStorage): opening pages does not ring', async () => {
  const world = boot({ answers: [[item({ unread: 2 })]], sound: true });
  await settle();
  for (let i = 0; i < 4; i++) await world.go();
  assert.equal(world.ref.audio.plays, 0, 'same unread total on every page');
  assert.equal(world.session['chatFab.serverUnread'], '2');

  const reloaded = boot({ answers: [[item({ unread: 2 })], [item({ unread: 3 })]], session: world.session, sound: true });
  await settle();
  assert.equal(reloaded.ref.audio.plays, 0, 'same total as before the reload');
  reloaded.advance(Fab.POLL_MS); await settle();
  assert.equal(reloaded.ref.audio.plays, 1);
});

test('a desktop notification only while the tab is hidden, the drawer closed and permission granted', async () => {
  const world = boot({ answers: [[item({ unread: 0 })], [item({ unread: 2, last_user_name: 'สมชาย', last_body: 'ด่วน' })]], notification: 'granted' });
  await settle();
  world.doc.hidden = true;
  world.advance(Fab.POLL_MS); await settle();
  assert.equal(world.notifications.length, 1);
  const [title, options] = world.notifications[0];
  assert.equal(title, 'ข้อความใหม่จาก Live Chat');
  assert.equal(options.body, 'สมชาย: ด่วน');
  assert.equal(options.icon, '/images/logoppk.png');

  const visible = boot({ answers: [[item({ unread: 0 })], [item({ unread: 2 })]], notification: 'granted' });
  await settle(); visible.advance(Fab.POLL_MS); await settle();
  assert.equal(visible.notifications.length, 0, 'the tab is in front');

  const denied = boot({ answers: [[item({ unread: 0 })], [item({ unread: 2 })]], notification: 'denied' });
  denied.doc.hidden = true; await settle(); denied.advance(Fab.POLL_MS); await settle();
  assert.equal(denied.notifications.length, 0);
});

test('permission is asked once per page while it is undecided; a browser without Notification still works', async () => {
  const world = boot({ notification: 'default' });
  await settle();
  assert.equal(world.permissionAsked.length, 1);
  await world.go(); assert.equal(world.permissionAsked.length, 2, 'asked again on the next page while still undecided');

  const granted = boot({ notification: 'granted' }); await settle();
  assert.equal(granted.permissionAsked.length, 0);

  const none = boot({ notification: null, answers: [[item({ unread: 3 })]] });
  await settle();
  assert.equal(rows(none).length, 1, 'the list still loads where the Notification API does not exist');
});

// ── failures ───────────────────────────────────────────────────────────────────────────────────────────────────────
test('a failed poll says so on first load, then recovers; the widget never throws', async () => {
  const world = boot({ answers: [500, [item({ title: 'กลับมาแล้ว' })]] });
  await settle();
  assert.ok(world.ref.list.innerHTML.includes('โหลดข้อมูลไม่สำเร็จ (500)'));
  world.advance(Fab.POLL_MS); await settle();
  assert.equal(rows(world).length, 1);
});

test('a network error and a reply that is not a list are handled', async () => {
  const offline = boot({ answers: [new Error('offline')] });
  await settle();
  assert.ok(offline.ref.list.innerHTML.includes('เกิดข้อผิดพลาดในการเชื่อมต่อเครือข่าย'));
  assert.equal(offline.errors.length, 1);

  const odd = boot({ answers: [{ message: 'nope' }] });
  await settle();
  assert.ok(odd.ref.list.innerHTML.includes('ยังไม่มีกระทู้'));
});

test('an empty list shows the empty state; blocked sessionStorage does not break polling', async () => {
  const world = boot({ answers: [[]] });
  await settle();
  assert.ok(world.ref.list.innerHTML.includes('ยังไม่มีกระทู้ที่คุณมีส่วนร่วม'));

  const blocked = boot({ answers: [[item()]] });
  blocked.win.sessionStorage = { getItem() { throw new Error('blocked'); }, setItem() { throw new Error('blocked'); } };
  await blocked.go();
  assert.equal(rows(blocked).length, 1);
});

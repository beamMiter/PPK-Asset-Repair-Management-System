// The live chat page (resources/js/chat/page.js).  Run: node --test tests/js/
//
// The inline script it replaces started on `DOMContentLoaded` and on Livewire's `livewire:navigated` — neither fires on a
// Turbo visit, so opening the chat from the menu left the thread without live updates until a full reload — and nothing ever
// stopped its poll timer, its Echo channel or its Pusher handler when you left the page. It also put the sender's name
// (which people choose themselves) into `innerHTML`. These tests pin the fixes and the behaviour it had.
import { build } from 'esbuild';
import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import path from 'node:path';
import { createWorld } from './helpers/fake-dom.mjs';

const code = (await build({
  entryPoints: [path.resolve('resources/js/chat/page.js')],
  bundle: true, format: 'iife', globalName: 'Chat', write: false, logLevel: 'silent',
})).outputFiles[0].text;
const Chat = (() => { const box = {}; vm.runInNewContext(`${code}\nglobalThis.out = Chat;`, box); return box.out; })();

const settle = () => new Promise((r) => setImmediate(r));
const EXPECTED = ['document:DOMContentLoaded', 'document:turbo:before-render', 'document:turbo:load'].sort();

const msg = (over = {}) => ({ id: 11, user_id: 9, body: 'สวัสดี', user: { id: 9, name: 'สมชาย', avatar_thumb_url: '/img/9.png' }, ...over });

// what chat/index.blade.php renders with a thread open
function threadPage(body, world, { thread = 7, last = 10, lastUser = 9 } = {}) {
  const el = world.el; const r = {};
  r.pane = el('div', { id: 'chat-pane' }); r.pane.alpine = { chatStatus: 'connecting' };
  r.box = el('div', { id: 'chatBox' });
  Object.assign(r.box.dataset, { threadId: String(thread), myId: '5', lastId: String(last), lastUserId: String(lastUser), chatUrl: `/chat/threads/${thread}/messages` });
  r.box.scrollHeight = 1000; r.box.clientHeight = 500; r.box.scrollTop = 0;
  r.empty = el('div', { id: 'emptyStateMsg' });
  r.input = el('textarea', { id: 'msgInput' }); r.input.value = ''; r.input.scrollHeight = 100;
  r.form = el('form').append(r.input); r.form.submitted = 0; r.form.submit = () => { r.form.submitted++; };
  r.scrollBtn = el('button', { id: 'btnScrollBottom', class: 'hidden' });
  r.icon = el('span', { class: 'material-symbols-outlined' });
  r.refresh = el('button', { id: 'btnHeaderRefresh' }).append(r.icon); r.refresh.disabled = false;
  r.panelLoader = el('div', { id: 'panelLoader', class: 'hidden' });
  r.counter = el('span', { id: `thread-count-${thread}` }); r.counter.textContent = '4';
  r.box.append(r.empty);
  r.pane.append(r.box, r.form, r.scrollBtn, r.refresh, r.panelLoader, r.counter);
  body.append(r.pane);
  return r;
}
const listPage = (body, world) => { body.append(world.el('div', { id: 'chat-pane' })); return {}; };

function boot({ page = threadPage, answers = [[]], echo = true, innerWidth = 1280, ready = true } = {}) {
  const world = createWorld();
  const fetches = []; const queue = [...answers];
  world.win.fetch = async (url) => {
    fetches.push(url);
    const a = queue.length > 1 ? queue.shift() : queue[0];
    if (a instanceof Error) throw a;
    if (a === false) return { ok: false, json: async () => ({}) };
    return { ok: true, json: async () => a };
  };
  world.win.innerWidth = innerWidth;
  world.win.console = { warn() {}, error() {}, log() {} };
  const conn = { state: 'connecting', bound: [], bind(_e, f) { conn.bound.push(f); }, unbind(_e, f) { conn.bound = conn.bound.filter((x) => x !== f); } };
  const handlers = {}; const joined = []; const left = [];
  if (echo) {
    world.win.Echo = { connector: { pusher: { connection: conn } },
      channel(name) { joined.push(name); return { listen(_evt, cb) { handlers[name] = cb; return this; } }; },
      leave(name) { left.push(name); delete handlers[name]; } };
    world.win.Alpine = { $data: (e) => e.alpine };
  }
  Object.assign(world, { fetches, conn, handlers, joined, left, queue });
  world.ref = page(world.body, world);
  world.chat = Chat.installChatPage(world.win);
  world.go = (build = page, opts) => {                       // a Turbo visit: the old page is torn down, the body replaced
    world.fireDocument('turbo:before-render');
    world.visit((body) => { world.ref = build(body, world, opts); });
    return world.ref;
  };
  world.intervals = () => world.timers.filter((t) => t.every).length;
  world.rows = () => (world.ref.box.querySelector('.space-y-6')?.children ?? []);
  world.emit = (m) => world.handlers[`chat.${world.ref.box.dataset.threadId}`]({ message: m });
  return world;
}

// ── it starts on a Turbo visit and stops when you leave ────────────────────────────────────────────────────────────
test('the thread on screen goes live at once: an interval, its channel, the refresh button', () => {
  const world = boot();
  assert.equal(world.intervals(), 1);
  assert.deepEqual(world.joined, ['chat.7']);
  assert.equal(typeof world.win.forceChatPoll, 'function');
  assert.equal(world.conn.bound.length, 1);
  assert.equal(world.ref.box.scrollTop, 1000, 'opens at the latest message');
});

test('opening the chat from the menu (a Turbo visit, no DOMContentLoaded) starts it too', () => {
  const world = boot({ page: listPage });
  assert.equal(world.intervals(), 0, 'the list alone has nothing to keep up to date');
  world.go(threadPage);
  assert.equal(world.intervals(), 1);
  assert.deepEqual(world.joined, ['chat.7']);
});

test('leaving the chat stops the poll, leaves the channel, unbinds the Pusher handler and removes the refresh hook', async () => {
  const world = boot();
  assert.ok(world.handlers['chat.7'], 'subscribed');
  const leftBefore = world.left.length;
  world.go(listPage);                                    // then a page that is not the chat
  assert.equal(world.timers.length, 0, 'no interval, no 10 s status timer');
  assert.equal(world.left.length, leftBefore + 1, 'the channel is left when the page is');
  assert.equal(world.handlers['chat.7'], undefined);
  assert.equal(world.conn.bound.length, 0);
  assert.equal(world.win.forceChatPoll, undefined);
  const before = world.fetches.length;
  world.advance(120_000); await settle();
  assert.equal(world.fetches.length, before, 'nothing keeps polling a thread you left');
});

test('going back and forth between chat pages does not stack anything', () => {
  const world = boot();
  const baseline = world.listenerList();
  assert.deepEqual(baseline, EXPECTED);
  for (let i = 0; i < 10; i++) { world.go(listPage); world.go(threadPage); }
  assert.equal(world.intervals(), 1);
  assert.equal(world.conn.bound.length, 1, 'one state_change handler');
  assert.deepEqual(world.listenerList(), baseline);
  assert.equal(world.timers.length, 2, 'the interval and the 10 s status timer, nothing left over');
});

test('another thread: the old channel is left and the new one joined, and polling follows the new thread', async () => {
  const world = boot({ answers: [[]] });
  world.go(threadPage, { thread: 8, last: 40 });
  assert.deepEqual(world.joined, ['chat.7', 'chat.8']);
  assert.ok(world.left.includes('chat.7'));
  world.advance(5000); await settle();
  assert.equal(world.fetches.at(-1), '/chat/threads/8/messages?after_id=40');
});

test('installing twice, or the same rendered page arriving through several events, mounts once', () => {
  const world = boot();
  assert.strictEqual(Chat.installChatPage(world.win), world.chat);
  world.fireDocument('DOMContentLoaded'); world.fireDocument('turbo:load'); world.fireDocument('turbo:load');
  assert.equal(world.intervals(), 1);
  assert.equal(world.joined.length, 1);
});

test('a chat page with no thread open starts nothing', () => {
  const world = boot({ page: listPage });
  assert.equal(world.timers.length, 0);
  assert.equal(world.joined.length, 0);
  assert.equal(world.win.forceChatPoll, undefined);
});

// ── polling ────────────────────────────────────────────────────────────────────────────────────────────────────────
test('every 5 s it fetches what is newer than the last message, appends it, and moves on', async () => {
  const world = boot({ answers: [[msg({ id: 11 }), msg({ id: 12, user_id: 5, body: 'ตอบแล้ว' })], []] });
  world.advance(4999); await settle();
  assert.equal(world.fetches.length, 0);
  world.advance(1); await settle();
  assert.equal(world.fetches[0], '/chat/threads/7/messages?after_id=10');
  assert.equal(world.rows().length, 2);
  assert.equal(world.ref.box.dataset.lastId, '12');
  assert.equal(world.ref.counter.textContent, '6', 'the counter in the thread list moves by 2');
  assert.equal(world.ref.box.scrollTop, 1000);
  world.advance(5000); await settle();
  assert.equal(world.fetches[1], '/chat/threads/7/messages?after_id=12');
});

test('the list may come wrapped in { data } or bare; a failed or broken reply changes nothing', async () => {
  const wrapped = boot({ answers: [{ data: [msg({ id: 11 })] }] });
  wrapped.advance(5000); await settle();
  assert.equal(wrapped.rows().length, 1);

  const failing = boot({ answers: [false, new Error('offline'), { message: 'odd' }, [msg({ id: 11 })]] });
  for (let i = 0; i < 3; i++) { failing.advance(5000); await settle(); }
  assert.equal(failing.rows().length, 0);
  failing.advance(5000); await settle();
  assert.equal(failing.rows().length, 1, 'and the next tick recovers');
});

test('a reader who scrolled up is not pulled down by new messages until they press the button', async () => {
  const world = boot({ answers: [[msg({ id: 11 })], [msg({ id: 12 })]] });
  world.ref.box.scrollTop = 0; world.ref.box.dispatch('scroll');       // far from the bottom
  world.advance(5000); await settle();
  assert.equal(world.ref.box.scrollTop, 0);
  world.ref.scrollBtn.dispatch('click');
  assert.equal(world.ref.box.scrollTop, 1000);
  assert.ok(world.ref.scrollBtn.classList.contains('hidden'));
  world.advance(5000); await settle();
  assert.equal(world.ref.box.scrollTop, 1000);
});

// ── realtime ───────────────────────────────────────────────────────────────────────────────────────────────────────
test('a live message is appended once; the same or an older one arriving again is ignored', async () => {
  const world = boot({ answers: [[]] });
  world.emit(msg({ id: 11 }));
  world.emit(msg({ id: 11 }));
  world.emit(msg({ id: 3 }));
  assert.equal(world.rows().length, 1);
  assert.equal(world.ref.counter.textContent, '5');
  world.advance(5000); await settle();
  assert.ok(world.fetches[0].endsWith('after_id=11'), 'the poll continues after the live message');
});

// ── what a message looks like ──────────────────────────────────────────────────────────────────────────────────────
test('my messages sit on the right as "You"; others show avatar and name, a follow-up from the same person does not', () => {
  const world = boot({ answers: [[]] });
  world.emit(msg({ id: 11, user_id: 5, body: 'ของฉัน' }));
  world.emit(msg({ id: 12, user_id: 6, body: 'a', user: { name: 'วรรณา', avatar_thumb_url: '' } }));
  world.emit(msg({ id: 13, user_id: 6, body: 'b', user: { name: 'วรรณา' } }));
  const [mine, first, follow] = world.rows();
  assert.ok(mine.className.includes('items-end') && mine.textContent.includes('You') && mine.textContent.includes('ของฉัน'));
  assert.ok(first.querySelector('img').src.startsWith('https://ui-avatars.com/api/?name=' + encodeURIComponent('วรรณา')), 'no avatar: the initials service');
  assert.ok(first.textContent.includes('วรรณา'));
  assert.equal(follow.querySelectorAll('img').length, 0);
  assert.ok(follow.className.includes('mt-1') && first.className.includes('mt-4'));
  assert.equal(mine.dataset.userId, '5');
  assert.equal(world.ref.box.dataset.lastUserId, '6');
});

test('the first live message hides the empty-state text and reveals itself on the next tick', () => {
  const world = boot({ answers: [[]] });
  world.emit(msg());
  assert.equal(world.ref.empty.style.display, 'none');
  const [row] = world.rows();
  assert.ok(row.classList.contains('opacity-0'));
  world.advance(10);
  assert.ok(!row.classList.contains('opacity-0'));
});

test('a sender name or message containing HTML is shown as text and creates no elements', () => {
  const world = boot({ answers: [[]] });
  const evil = '<img src=x onerror="alert(document.cookie)"><script>alert(1)</script>';
  world.emit(msg({ id: 11, user_id: 6, body: evil, user: { name: evil, avatar_thumb_url: '/a.png' } }));
  const [row] = world.rows();
  assert.ok(row.textContent.includes(evil));
  assert.equal(row.querySelectorAll('img').length, 1, 'only the avatar');
  assert.equal(row.querySelectorAll('script').length, 0);
  assert.ok([row, ...row.querySelectorAll('*')].every((n) => n.innerHTML === ''), 'nothing went through innerHTML');
});

test('an avatar URL is set as a URL, not parsed into markup', () => {
  const world = boot({ answers: [[]] });
  world.emit(msg({ user_id: 6, user: { name: 'x', avatar_thumb_url: '"><script>alert(1)</script>' } }));
  const [row] = world.rows();
  assert.equal(row.querySelector('img').src, '"><script>alert(1)</script>');
  assert.equal(row.querySelectorAll('script').length, 0);
});

// ── the composer and the refresh button ────────────────────────────────────────────────────────────────────────────
test('Enter sends on a desktop; Shift+Enter, an empty box and a phone do not', () => {
  const world = boot();
  const { input, form } = world.ref;
  input.value = 'ข้อความ';
  const enter = input.dispatch('keydown', { key: 'Enter', shiftKey: false });
  assert.equal(enter.defaultPrevented, true);
  assert.equal(form.submitted, 1);

  input.dispatch('keydown', { key: 'Enter', shiftKey: true }); assert.equal(form.submitted, 1);
  input.value = '   '; input.dispatch('keydown', { key: 'Enter', shiftKey: false }); assert.equal(form.submitted, 1);
  input.dispatch('keydown', { key: 'a' }); assert.equal(form.submitted, 1);

  const phone = boot({ innerWidth: 400 });
  phone.ref.input.value = 'x';
  assert.equal(phone.ref.input.dispatch('keydown', { key: 'Enter', shiftKey: false }).defaultPrevented, false);
  assert.equal(phone.ref.form.submitted, 0);
});

test('the composer grows with its text up to 140 px', () => {
  const world = boot();
  const { input } = world.ref;
  input.scrollHeight = 100; input.dispatch('input'); assert.equal(input.style.height, '100px');
  input.scrollHeight = 400; input.dispatch('input'); assert.equal(input.style.height, '140px');
  input.scrollHeight = 30; input.dispatch('input'); assert.equal(input.style.height, '30px');
});

test('the refresh button polls now, shows the spinner, and is back to normal after the 600 ms pause', async () => {
  const world = boot({ answers: [[msg({ id: 11 })]] });
  const r = world.ref;
  const done = world.win.forceChatPoll();
  assert.equal(r.refresh.disabled, true);
  assert.ok(r.refresh.classList.contains('opacity-70') && r.icon.classList.contains('animate-spin'));
  assert.ok(!r.panelLoader.classList.contains('hidden') && r.panelLoader.classList.contains('flex'));
  await settle();
  assert.equal(world.rows().length, 1, 'it polled');
  world.advance(600); await done;
  assert.equal(r.refresh.disabled, false);
  assert.ok(!r.icon.classList.contains('animate-spin') && r.panelLoader.classList.contains('hidden'));
});

// ── connection state ───────────────────────────────────────────────────────────────────────────────────────────────
test('the Pusher state maps to chatStatus; "connecting" turns into "offline" after 10 s', () => {
  const world = boot();
  const status = () => world.ref.pane.alpine.chatStatus;
  assert.equal(status(), 'connecting');
  world.conn.bound[0]({ current: 'connected' }); assert.equal(status(), 'online');
  world.conn.bound[0]({ current: 'failed' }); assert.equal(status(), 'offline');
  world.conn.bound[0]({ current: 'connecting' }); assert.equal(status(), 'connecting');
  world.advance(10_000); assert.equal(status(), 'offline');

  const quick = boot();
  quick.conn.bound[0]({ current: 'connected' });
  quick.advance(10_000); assert.equal(quick.ref.pane.alpine.chatStatus, 'online', 'a connected page is not knocked back by the timeout');
});

test('without Echo (or before Alpine has started) the page still polls', async () => {
  const noEcho = boot({ echo: false, answers: [[msg({ id: 11 })]] });
  noEcho.advance(5000); await settle();
  assert.equal(noEcho.rows().length, 1);

  const noAlpine = boot({ answers: [[]] });
  noAlpine.win.Alpine = undefined;
  assert.doesNotThrow(() => noAlpine.conn.bound[0]({ current: 'connected' }));
});

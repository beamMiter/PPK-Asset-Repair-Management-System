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
function threadPage(body, world, { thread = 7, last = 10, lastUser = 9, canModerate = false } = {}) {
  const el = world.el; const r = {};
  r.pane = el('div', { id: 'chat-pane' }); r.pane.alpine = { chatStatus: 'connecting', locked: false, deleting: false };
  r.box = el('div', { id: 'chatBox' });
  Object.assign(r.box.dataset, { threadId: String(thread), myId: '5', lastId: String(last), lastUserId: String(lastUser), chatUrl: `/chat/threads/${thread}/messages`, listUrl: '/chat', canModerate: canModerate ? '1' : '0' });
  r.box.scrollHeight = 1000; r.box.clientHeight = 500; r.box.scrollTop = 0;
  r.empty = el('div', { id: 'emptyStateMsg' });
  r.input = el('textarea', { id: 'msgInput' }); r.input.value = ''; r.input.scrollHeight = 100;
  r.sendBtn = el('button', { type: 'submit' });
  r.form = el('form', { action: `/chat/threads/${thread}/messages` }).append(r.input, r.sendBtn); r.form.submitted = 0; r.form.submit = () => { r.form.submitted++; };
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
  const requests = [];
  world.win.fetch = async (url, init) => {
    fetches.push(url); requests.push({ url, init });
    const a = queue.length > 1 ? queue.shift() : queue[0];
    if (a instanceof Error) throw a;
    if (a === false) return { ok: false, json: async () => ({}) };
    if (a && a.__resp) {                                      // an answer with a status and headers
      const status = a.status ?? 200;
      return { ok: status < 400, status, headers: { get: (k) => a.headers?.[k] ?? null }, json: async () => a.body ?? [] };
    }
    return { ok: true, json: async () => a };
  };
  world.win.innerWidth = innerWidth;
  world.win.console = { warn() {}, error() {}, log() {} };
  const conn = { state: 'connecting', bound: [], bind(_e, f) { conn.bound.push(f); }, unbind(_e, f) { conn.bound = conn.bound.filter((x) => x !== f); } };
  const handlers = {}; const events = {}; const joined = []; const left = []; const toasts = []; const visited = [];
  world.win.showToast = (o) => toasts.push(o);
  const confirms = []; world.win.confirm = (text) => { confirms.push(text); return world.confirmAnswer !== false; };
  world.win.Turbo = { visit: (url) => visited.push(url) };
  if (echo) {
    world.win.Echo = { connector: { pusher: { connection: conn } },
      private(name) { joined.push(name); return { listen(evt, cb) { (events[name] ??= {})[evt] = cb; if (evt === '.message.sent') handlers[name] = cb; return this; } }; },
      leave(name) { left.push(name); delete handlers[name]; delete events[name]; } };
  }
  world.win.Alpine = { $data: (e) => e.alpine };            // the page's Alpine is there whether or not the websocket is
  Object.assign(world, { confirms, fetches, requests, conn, handlers, events, joined, left, queue, toasts, visited });
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
  world.hear = (evt, payload = {}) => world.events[`chat.${world.ref.box.dataset.threadId}`][evt](payload);   // a thread event: '.thread.lock', '.thread.deleted'
  world.resp = (body, { status = 200, headers = {} } = {}) => ({ __resp: true, body, status, headers });
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
test('my messages sit on the right as "คุณ"; others show avatar and name, a follow-up from the same person does not', () => {
  const world = boot({ answers: [[]] });
  world.emit(msg({ id: 11, user_id: 5, body: 'ของฉัน' }));
  world.emit(msg({ id: 12, user_id: 6, body: 'a', user: { name: 'วรรณา', avatar_thumb_url: '' } }));
  world.emit(msg({ id: 13, user_id: 6, body: 'b', user: { name: 'วรรณา' } }));
  const [mine, first, follow] = world.rows();
  assert.ok(mine.className.includes('items-end') && mine.textContent.includes('คุณ') && mine.textContent.includes('ของฉัน'));
  assert.match(mine.textContent, /\d{2}:\d{2}/, 'a message of today: the time on a 24-hour clock');
  assert.ok(!/\b(AM|PM)\b/i.test(mine.textContent) && !/Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday/.test(mine.textContent));
  assert.equal(first.querySelector('img').alt, 'รูปผู้ใช้');
  const src = first.querySelector('img').src;
  assert.ok(src.startsWith('data:image/svg+xml'), 'no avatar: the initials, carried in the src — no request to another site');
  assert.ok(decodeURIComponent(src).includes('>ว</text>'), 'the initial of วรรณา');
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
const sent = (world) => world.requests.filter((r) => r.init && r.init.method === 'POST');
const mine = (over = {}) => ({ id: 12, user_id: 5, body: 'ข้อความ', user: { id: 5, name: 'ฉัน' }, ...over });

test('Enter sends on a desktop; Shift+Enter, an empty box and a phone do not', async () => {
  const world = boot({ answers: [[]] });
  const { input, form } = world.ref;
  world.queue.splice(0, world.queue.length, world.resp(mine(), { status: 201 }));
  input.value = 'ข้อความ';
  const enter = input.dispatch('keydown', { key: 'Enter', shiftKey: false });
  await settle();
  assert.equal(enter.defaultPrevented, true);
  assert.equal(sent(world).length, 1);
  assert.equal(form.submitted, 0, 'the page is not reloaded by a native submit');

  input.value = 'อีกข้อความ';
  input.dispatch('keydown', { key: 'Enter', shiftKey: true }); await settle(); assert.equal(sent(world).length, 1);
  input.value = '   '; input.dispatch('keydown', { key: 'Enter', shiftKey: false }); await settle(); assert.equal(sent(world).length, 1);
  input.dispatch('keydown', { key: 'a' }); await settle(); assert.equal(sent(world).length, 1);

  const phone = boot({ innerWidth: 400 });
  phone.ref.input.value = 'x';
  assert.equal(phone.ref.input.dispatch('keydown', { key: 'Enter', shiftKey: false }).defaultPrevented, false);
  await settle();
  assert.equal(sent(phone).length, 0);
});

// ── sending without a reload ───────────────────────────────────────────────────────────────────────────────────────
/** a thread page whose next answers the test sets (the server's reply to the message), with the CSRF meta tag the layout carries */
function sendable() {
  const world = boot({ answers: [[]] });
  world.body.append(world.el('meta', { name: 'csrf-token', content: 'tok-9' }));
  return world;
}
const type = (world, text) => { world.ref.input.value = text; return world.ref.input; };

test('a message is posted as JSON to the form\'s address with the CSRF token, and drawn from the answer', async () => {
  const world = sendable();
  world.queue.splice(0, world.queue.length, world.resp(mine({ id: 12, body: 'สวัสดีครับ' }), { status: 201 }));
  type(world, 'สวัสดีครับ').dispatch('keydown', { key: 'Enter', shiftKey: false });
  await settle();

  const [req] = sent(world);
  assert.equal(req.url, '/chat/threads/7/messages');
  assert.equal(req.init.headers.Accept, 'application/json');
  assert.equal(req.init.headers['X-CSRF-TOKEN'], 'tok-9');
  assert.deepEqual(JSON.parse(req.init.body), { body: 'สวัสดีครับ' });

  assert.equal(world.ref.input.value, '', 'the box empties');
  assert.equal(world.rows().length, 1);
  assert.ok(world.rows()[0].textContent.includes('สวัสดีครับ'));
  assert.equal(world.ref.box.dataset.lastId, '12', 'the poll asks after it');
  assert.equal(world.ref.counter.textContent, '5', 'the thread counter went up');
  assert.equal(world.ref.box.scrollTop, 1000, 'scrolled to the new message');
});

test('the send button does the same, and its own submit is not left to reload the page', async () => {
  const world = sendable();
  world.queue.splice(0, world.queue.length, world.resp(mine(), { status: 201 }));
  type(world, 'x');
  const ev = world.ref.form.dispatch('submit');
  await settle();
  assert.equal(ev.defaultPrevented, true);
  assert.equal(sent(world).length, 1);
  assert.equal(world.ref.form.submitted, 0);
});

test('pressing Enter twice at once sends once; the send button is off while it goes', async () => {
  const world = sendable();
  world.queue.splice(0, world.queue.length, world.resp(mine(), { status: 201 }));
  type(world, 'x');
  world.ref.input.dispatch('keydown', { key: 'Enter', shiftKey: false });
  assert.equal(world.ref.sendBtn.disabled, true);
  world.ref.input.dispatch('keydown', { key: 'Enter', shiftKey: false });
  await settle();
  assert.equal(sent(world).length, 1);
  assert.equal(world.ref.sendBtn.disabled, false, 'and back on afterwards');
});

test('an empty or blank box sends nothing', async () => {
  const world = sendable();
  type(world, '   ').dispatch('keydown', { key: 'Enter', shiftKey: false });
  world.ref.form.dispatch('submit');
  await settle();
  assert.equal(sent(world).length, 0);
});

test('the broadcast may draw the message before the answer arrives: it is drawn once', async () => {
  const world = sendable();
  world.queue.splice(0, world.queue.length, world.resp(mine({ id: 12 }), { status: 201 }));
  type(world, 'ข้อความ').dispatch('keydown', { key: 'Enter', shiftKey: false });
  world.emit(msg({ id: 12, user_id: 5, body: 'ข้อความ' }));       // the websocket was quicker
  await settle();
  assert.equal(world.rows().length, 1);
  assert.equal(world.ref.counter.textContent, '5', 'counted once');
});

test('the thread was locked while they typed: their words stay, the composer closes, a toast says why', async () => {
  const world = sendable();
  world.queue.splice(0, world.queue.length, world.resp({}, { status: 403 }));
  type(world, 'ยังพิมพ์ไม่เสร็จ').dispatch('keydown', { key: 'Enter', shiftKey: false });
  await settle();
  assert.equal(world.ref.input.value, 'ยังพิมพ์ไม่เสร็จ');
  assert.equal(world.ref.pane.alpine.locked, true);
  assert.match(world.toasts.at(-1).message, /ถูกล็อกแล้ว/);
  assert.equal(world.rows().length, 0);
});

test('the thread was deleted while they typed: told, and taken back to the list', async () => {
  const world = sendable();
  world.queue.splice(0, world.queue.length, world.resp({}, { status: 404 }));
  type(world, 'x').dispatch('keydown', { key: 'Enter', shiftKey: false });
  await settle();
  assert.match(world.toasts.at(-1).message, /ถูกลบแล้ว/);
  world.advance(1500);
  assert.deepEqual(world.visited, ['/chat']);
});

test('a server error or a dropped connection keeps what was typed, says so, and lets them try again', async () => {
  for (const failure of [500, new Error('offline')]) {
    const world = sendable();
    world.queue.splice(0, world.queue.length, typeof failure === 'number' ? world.resp({}, { status: failure }) : failure);
    type(world, 'อย่าให้หาย').dispatch('keydown', { key: 'Enter', shiftKey: false });
    await settle();
    assert.equal(world.ref.input.value, 'อย่าให้หาย');
    assert.match(world.toasts.at(-1).message, /ส่งข้อความไม่สำเร็จ/);
    assert.equal(world.toasts.at(-1).type, 'error');
    assert.equal(world.ref.sendBtn.disabled, false);
    assert.equal(world.rows().length, 0);
  }
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

// ── the thread's own state: locked, unlocked, deleted — with no refresh ────────────────────────────────────────────
test('another person locks the thread: the page follows at once, and says so once', () => {
  const world = boot();
  world.hear('.thread.lock', { thread_id: 7, is_locked: true });
  assert.equal(world.ref.pane.alpine.locked, true);
  assert.equal(world.toasts.length, 1);
  assert.match(world.toasts[0].message, /ถูกล็อกแล้ว/);
  world.hear('.thread.lock', { thread_id: 7, is_locked: true });
  assert.equal(world.toasts.length, 1, 'the same state again says nothing');
});

test('and unlocks it: the composer comes back', () => {
  const world = boot();
  world.ref.pane.alpine.locked = true;
  world.hear('.thread.lock', { is_locked: false });
  assert.equal(world.ref.pane.alpine.locked, false);
  assert.match(world.toasts[0].message, /ส่งข้อความได้อีกครั้ง/);
});

test('the person who pressed lock has been shown already (Alpine flipped before the request): the broadcast adds no second toast', () => {
  const world = boot();
  world.ref.pane.alpine.locked = true;                      // submitLock() did this first
  world.hear('.thread.lock', { thread_id: 7, is_locked: true });
  assert.deepEqual(world.toasts, []);
});

test('a payload with no state is ignored', () => {
  const world = boot();
  world.hear('.thread.lock', {}); world.hear('.thread.lock', { is_locked: 'yes' });
  assert.equal(world.ref.pane.alpine.locked, false);
  assert.deepEqual(world.toasts, []);
});

test('with no websocket at all, the poll still learns of the lock from its header', async () => {
  const world = boot({ echo: false, answers: [[]] });
  world.queue.splice(0, world.queue.length, world.resp([], { headers: { 'X-Thread-Locked': '1' } }));
  world.advance(5000); await settle();
  assert.equal(world.ref.pane.alpine.locked, true);
  assert.match(world.toasts[0].message, /ถูกล็อกแล้ว/);
});

test('the poll header flips the state both ways', async () => {
  const world = boot({ answers: [[]] });
  world.queue.splice(0, world.queue.length, world.resp([], { headers: { 'X-Thread-Locked': '1' } }));
  world.advance(5000); await settle();
  assert.equal(world.ref.pane.alpine.locked, true);
  world.queue.splice(0, world.queue.length, world.resp([], { headers: { 'X-Thread-Locked': '0' } }));
  world.advance(5000); await settle();
  assert.equal(world.ref.pane.alpine.locked, false);
});

test('a poll answer with no header changes nothing', async () => {
  const world = boot({ answers: [[]] });
  world.ref.pane.alpine.locked = true;
  world.advance(5000); await settle();
  assert.equal(world.ref.pane.alpine.locked, true);
});

test('the thread is deleted: the page says so, stops polling and goes back to the list', () => {
  const world = boot();
  world.hear('.thread.deleted');
  assert.match(world.toasts[0].message, /ถูกลบแล้ว/);
  assert.equal(world.intervals(), 0, 'nothing keeps polling a thread that is gone');
  assert.deepEqual(world.visited, [], 'not before the toast has been read');
  world.advance(1500);
  assert.deepEqual(world.visited, ['/chat']);
});

test('the same, learnt from a poll that answers 404', async () => {
  const world = boot({ answers: [[]] });
  world.queue.splice(0, world.queue.length, world.resp([], { status: 404 }));
  world.advance(5000); await settle();
  assert.equal(world.toasts.length, 1);
  world.advance(1500);
  assert.deepEqual(world.visited, ['/chat']);
  world.advance(60_000); await settle();
  assert.equal(world.toasts.length, 1, 'told once, however often it is heard');
});

test('the admin who deleted it is not told: the page is already on its way to the list', () => {
  const world = boot();
  world.ref.pane.alpine.deleting = true;
  world.hear('.thread.deleted');
  assert.deepEqual(world.toasts, []);
  world.advance(5000);
  assert.deepEqual(world.visited, []);
});

test('sending too fast: a warning with the wait from Retry-After, and what was typed stays', async () => {
  const world = sendable();
  world.queue.splice(0, world.queue.length, world.resp({}, { status: 429, headers: { 'Retry-After': '7' } }));
  type(world, 'พิมพ์ไว้แล้ว').dispatch('keydown', { key: 'Enter', shiftKey: false });
  await settle();
  assert.equal(world.toasts.at(-1).type, 'warning');
  assert.match(world.toasts.at(-1).message, /รอ 7 วินาที/);
  assert.equal(world.ref.input.value, 'พิมพ์ไว้แล้ว');
  assert.equal(world.rows().length, 0);
  assert.equal(world.ref.sendBtn.disabled, false);
});

test('a 429 with no Retry-After still says to wait (10 s), not nothing', async () => {
  const world = sendable();
  world.queue.splice(0, world.queue.length, world.resp({}, { status: 429 }));
  type(world, 'x').dispatch('keydown', { key: 'Enter', shiftKey: false });
  await settle();
  assert.match(world.toasts.at(-1).message, /รอ 10 วินาที/);
});

// ── the time of a message: the server's, on the Thai clock ─────────────────────────────────────────────────────────
import { chatTime } from '../../resources/js/chat/time.js';

test('a message is read the way a person says it - the same rules as ThaiDate::chatTime', () => {
  const now = new Date('2026-09-26T11:00:00Z');                       // 18:00 Saturday in Thailand
  const at = (iso) => chatTime(iso, now);
  assert.equal(at('2026-09-26T08:45:00Z'), '15:45');                  // today
  assert.equal(at('2026-09-25T17:05:00Z'), '00:05');                  // 00:05 today in Thailand, still "yesterday" in UTC
  assert.equal(at('2026-09-25T16:59:00Z'), 'เมื่อวาน 23:59');
  assert.equal(at('2026-09-23T02:30:00Z'), 'วันพุธ 09:30');
  assert.equal(at('2026-09-21T02:30:00Z'), 'วันจันทร์ 09:30');       // five days ago
  assert.equal(at('2026-09-20T02:30:00Z'), '20 ก.ย. 2569 09:30');    // six days ago: a date, Buddhist year
  assert.equal(at('2026-07-04T06:05:00Z'), '4 ก.ค. 2569 13:05');
  assert.equal(at('not a date'), '');
});

test('a live message shows the time the server stamped, not this browser\'s clock', () => {
  const world = boot();
  world.emit(msg({ id: 11, user_id: 6, user: { name: 'วรรณา' }, created_at: '2020-01-05T03:07:00Z' }));
  const [row] = world.rows();
  assert.ok(row.textContent.includes('5 ม.ค. 2563 10:07'), row.textContent);
});

// ── deleting a message ─────────────────────────────────────────────────────────────────────────────────────────────
const messageRow = (world, id) => world.rows().find((r) => r.getAttribute('data-message-id') === String(id));
const binOf = (row) => row.querySelector('.chat-msg-delete');

test('a message I wrote has a bin while the thread is open; one of somebody else\'s has none', () => {
  const world = boot();
  world.emit(msg({ id: 11, user_id: 5, body: 'ของฉัน' }));
  world.emit(msg({ id: 12, user_id: 6, body: 'ของเขา', user: { name: 'วรรณา' } }));
  assert.ok(binOf(messageRow(world, 11)), 'my own');
  assert.equal(binOf(messageRow(world, 12)), null, 'somebody else\'s');
  assert.equal(binOf(messageRow(world, 11)).getAttribute('aria-label'), 'ลบข้อความนี้');
});

test('once the thread is locked my own messages lose the bin (they cannot change a closed thread)', () => {
  const world = boot();
  world.ref.pane.alpine.locked = true;
  world.emit(msg({ id: 11, user_id: 5 }));
  assert.equal(binOf(messageRow(world, 11)), null);
});

test('a moderator gets a bin on every message, locked or not', () => {
  const world = boot({ page: (b, w) => threadPage(b, w, { canModerate: true }) });
  world.ref.pane.alpine.locked = true;
  world.emit(msg({ id: 11, user_id: 6, user: { name: 'วรรณา' } }));
  world.emit(msg({ id: 12, user_id: 5 }));
  assert.ok(binOf(messageRow(world, 11)) && binOf(messageRow(world, 12)));
});

test('pressing it asks, deletes with the CSRF token, and the message becomes "ข้อความนี้ถูกลบ" - the counter goes down', async () => {
  const world = boot({ answers: [[]] });
  world.body.append(world.el('meta', { name: 'csrf-token', content: 'tok-5' }));
  world.emit(msg({ id: 11, user_id: 5, body: 'ลับ' }));
  world.queue.splice(0, world.queue.length, world.resp({ deleted: true }));
  binOf(messageRow(world, 11)).dispatch('click');
  await settle();

  assert.match(world.confirms[0], /ลบข้อความนี้/);
  const del = world.requests.find((r) => r.init?.method === 'DELETE');
  assert.equal(del.url, '/chat/threads/7/messages/11');
  assert.equal(del.init.headers['X-CSRF-TOKEN'], 'tok-5');
  const row = messageRow(world, 11);
  assert.ok(row.textContent.includes('ข้อความนี้ถูกลบ') && !row.textContent.includes('ลับ'));
  assert.equal(binOf(row), null, 'no bin on what is gone');
  assert.equal(world.ref.counter.textContent, '4', '4 +1 (the live message) -1 (the deletion)');
});

test('answering "cancel" to the question deletes nothing', async () => {
  const world = boot();
  world.confirmAnswer = false;
  world.emit(msg({ id: 11, user_id: 5 }));
  binOf(messageRow(world, 11)).dispatch('click');
  await settle();
  assert.equal(world.requests.filter((r) => r.init?.method === 'DELETE').length, 0);
  assert.ok(binOf(messageRow(world, 11)));
});

test('another person deletes a message: this page hears it and shows the placeholder, once', () => {
  const world = boot();
  world.emit(msg({ id: 12, user_id: 6, body: 'จะถูกลบ', user: { name: 'วรรณา' } }));
  world.hear('.message.deleted', { thread_id: 7, message_id: 12 });
  world.hear('.message.deleted', { thread_id: 7, message_id: 12 });
  const row = messageRow(world, 12);
  assert.ok(row.textContent.includes('ข้อความนี้ถูกลบ') && !row.textContent.includes('จะถูกลบ'));
  assert.equal(world.ref.counter.textContent, '4', '+1 for the message, -1 once for the deletion (not twice)');
  world.hear('.message.deleted', { thread_id: 7, message_id: 999 });     // a message this page never drew: nothing happens
});

test('a refusal (403) or a failure is told and the message stays; a 404 means it is gone already', async () => {
  for (const [answer, expect] of [[403, /ไม่มีสิทธิ์/], [500, /ไม่สำเร็จ/]]) {
    const world = boot({ answers: [[]] });
    world.emit(msg({ id: 11, user_id: 5, body: 'อยู่ต่อ' }));
    world.queue.splice(0, world.queue.length, world.resp({}, { status: answer }));
    binOf(messageRow(world, 11)).dispatch('click');
    await settle();
    assert.match(world.toasts.at(-1).message, expect);
    assert.ok(messageRow(world, 11).textContent.includes('อยู่ต่อ'));
  }
  const gone = boot({ answers: [[]] });
  gone.emit(msg({ id: 11, user_id: 5, body: 'x' }));
  gone.queue.splice(0, gone.queue.length, gone.resp({}, { status: 404 }));
  binOf(messageRow(gone, 11)).dispatch('click');
  await settle();
  assert.ok(messageRow(gone, 11).textContent.includes('ข้อความนี้ถูกลบ'));
});

test('a deleted message that arrives (older load, or a poll) is drawn as the placeholder with no words in it', () => {
  const row = Chat.buildMessageRow(createWorld().doc, { id: 5, user_id: 6, body: null, deleted: true, user: { name: 'วรรณา' } }, { isMe: false, isConsecutive: false, timeStr: '15:45', canDelete: true });
  assert.ok(row.textContent.includes('ข้อความนี้ถูกลบ'));
  assert.equal(row.getAttribute('data-deleted'), '1');
  assert.equal(row.querySelector('.chat-msg-delete'), null);
});


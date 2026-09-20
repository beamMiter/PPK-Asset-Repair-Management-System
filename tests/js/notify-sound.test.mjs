// The bell in the top bar (sound on / off) and the realtime "new request" beep, from resources/js/repair/my-jobs.js.
// Run: node --test tests/js/
//
// The page is modelled the way the browser + Turbo Drive behave: a visit REPLACES the whole <body> (so the new bell is a
// new element without the old one's listeners), `turbo:load` fires after every visit, and the browser refuses audio.play()
// until the user has interacted with the document (sticky activation — which survives Turbo visits, not a reload).
import { build } from 'esbuild';
import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import path from 'node:path';

const SRC = process.env.MYJOBS_JS || path.resolve('resources/js/repair/my-jobs.js');
const code = (await build({ entryPoints: [SRC], bundle: true, format: 'iife', write: false, logLevel: 'silent' })).outputFiles[0].text;

const LS_KEY = 'myjobs.notify.sound.enabled';

function makeWorld({ stored = null, echo = true } = {}) {
  const world = { activated: false, plays: [], subs: [], handlers: {}, alerts: [], registry: {}, docListeners: {}, winListeners: {}, storage: {} };
  if (stored !== null) world.storage[LS_KEY] = stored;

  class El {
    constructor(id, parent = null) { this.id = id; this.parent = parent; this.className = ''; this.title = ''; this.listeners = {}; this.hidden = new Set(); this.icon = null; this.textContent = ''; }
    addEventListener(t, f) { (this.listeners[t] ||= []).push(f); }
    querySelector(sel) { return sel === 'i' ? this.icon : null; }
    closest(sel) { const ids = sel.split(',').map((s) => s.trim().replace(/^#/, '')); for (let e = this; e; e = e.parent) if (ids.includes(e.id)) return e; return null; }
    get classList() { const h = this.hidden; return { toggle: (c, f) => (f ? h.add(c) : h.delete(c)), add: (c) => h.add(c), remove: (c) => h.delete(c), contains: (c) => h.has(c) }; }
  }
  class AudioEl extends El {
    constructor(id) { super(id); this.volume = 1; this.currentTime = 0; }
    play() { if (!world.activated) return Promise.reject(new Error('NotAllowedError')); if (this.volume > 0) world.plays.push(this.id); return Promise.resolve(); } // the unlock probe plays at volume 0: silent
    pause() {}
  }

  const document = {
    addEventListener: (t, f) => (world.docListeners[t] ||= []).push(f),
    getElementById: (id) => world.registry[id] || null,
    querySelector: () => null, querySelectorAll: () => [],
  };
  const dispatchDoc = (type, ev = {}) => (world.docListeners[type] || []).forEach((f) => f({ type, ...ev }));

  // the top bar of a signed-in staff member: desktop bell, mobile bell, mobile-menu button, plus the layout's <audio>
  world.mount = ({ bell = true, audio = true } = {}) => {
    const reg = {};
    if (bell) {
      for (const [btn, icon, dot] of [['notifyToggleBtn', 'notifyIcon', 'notifyStatusDot'], ['notifyToggleBtnMobileTop', 'notifyIconMobileTop', 'notifyStatusDotMobileTop']]) {
        reg[btn] = new El(btn); reg[icon] = new El(icon, reg[btn]); reg[icon].className = 'bi bi-bell'; reg[dot] = new El(dot, reg[btn]); reg[dot].hidden.add('d-none');
      }
      reg.notifyToggleBtnMobile = new El('notifyToggleBtnMobile'); reg.notifyToggleBtnMobile.icon = new El('i', reg.notifyToggleBtnMobile); reg.notifyToggleBtnMobile.icon.className = 'bi bi-bell me-2';
    }
    if (audio) reg.notifySound = new AudioEl('notifySound');
    world.registry = reg;
    return reg;
  };
  world.visit = (opts) => { const reg = world.mount(opts); dispatchDoc('turbo:load'); return reg; };   // a Turbo visit: new body, then turbo:load
  world.gesture = (target = {}) => { world.activated = true; dispatchDoc('pointerdown', { target }); };
  world.key = () => { world.activated = true; dispatchDoc('keydown', { target: {} }); };
  world.click = async (el) => {                                                                       // pointerdown → click, bubbling to document
    world.gesture(el);
    (el.listeners.click || []).forEach((f) => f({ type: 'click', target: el }));
    dispatchDoc('click', { target: el });
    await new Promise((r) => setTimeout(r, 5));
  };
  world.settle = () => new Promise((r) => setTimeout(r, 5));
  world.otherTabChanges = (value) => { world.storage[LS_KEY] = value; (world.winListeners.storage || []).forEach((f) => f({ key: LS_KEY })); };
  world.newRequest = async () => { world.handlers['.maintenance.created']?.({ id: 1 }); await world.settle(); };

  const sandbox = {
    console: { log() {}, warn() {}, error() {} }, setTimeout, clearTimeout, Promise, JSON, Math, Number, Object, Array, parseFloat,
    document, alert: (m) => world.alerts.push(m), addEventListener: (t, f) => (world.winListeners[t] ||= []).push(f),
    localStorage: { getItem: (k) => world.storage[k] ?? null, setItem: (k, v) => { world.storage[k] = String(v); } },
    IntersectionObserver: class { observe() {} disconnect() {} },
  };
  sandbox.window = sandbox; sandbox.globalThis = sandbox;
  if (echo) sandbox.Echo = { channel: (name) => { world.subs.push(name); return { listen(evt, cb) { world.handlers[evt] = cb; return this; } }; } };
  vm.createContext(sandbox);
  vm.runInContext(code, sandbox);
  return world;
}

const iconOf = (reg) => reg.notifyIcon.className;

test('the bell still switches the sound on and off on the page that was loaded first', async () => {
  const w = makeWorld();
  const page = w.visit();
  await w.click(page.notifyToggleBtn);
  assert.equal(w.storage[LS_KEY], '1');
  assert.equal(iconOf(page), 'bi bi-bell-fill');
  await w.click(page.notifyToggleBtn);
  assert.equal(w.storage[LS_KEY], '0');
  assert.equal(iconOf(page), 'bi bi-bell-slash');
});

test('after a Turbo visit the new bell shows the saved state and still works', async () => {
  const w = makeWorld();
  const first = w.visit();
  await w.click(first.notifyToggleBtn);                       // sound on
  const second = w.visit();                                   // navigate: brand-new bell element, default markup
  assert.equal(iconOf(second), 'bi bi-bell-fill', 'the bell must show that the sound is on');
  assert.match(second.notifyToggleBtn.title, /เปิดเสียงแล้ว/);
  await w.click(second.notifyToggleBtn);                      // switch off from the new page
  assert.equal(w.storage[LS_KEY], '0');
  assert.equal(iconOf(second), 'bi bi-bell-slash');
  const third = w.visit();
  assert.equal(iconOf(third), 'bi bi-bell-slash');
  await w.click(third.notifyToggleBtnMobile);                 // the mobile menu button works too
  assert.equal(w.storage[LS_KEY], '1');
  assert.equal(third.notifyToggleBtnMobile.icon.className, 'bi bi-bell-fill me-2');
});

test('a new request beeps after visiting other pages (the listener does not depend on the first page\'s elements)', async () => {
  const w = makeWorld();
  const first = w.visit();
  await w.click(first.notifyToggleBtn);
  w.visit(); w.visit();
  w.plays.length = 0;
  await w.newRequest();
  assert.equal(w.plays.length, 1);
});

test('the realtime channel is subscribed once however many pages are visited', () => {
  const w = makeWorld();
  w.visit(); w.visit(); w.visit();
  assert.deepEqual(w.subs, ['maintenance-requests']);
});

test('a page loaded without a bell or audio element does not disable the feature for the rest of the session', async () => {
  const w = makeWorld();
  w.visit({ bell: false, audio: false });                     // e.g. the first page shown had no bell
  assert.equal(w.subs.length, 0, 'no bell, no audio: nothing to subscribe for');
  const page = w.visit();                                     // the next one has it
  await w.click(page.notifyToggleBtn);
  assert.equal(w.storage[LS_KEY], '1');
  assert.equal(w.subs.length, 1, 'and it is subscribed to new requests');
});

test('nothing beeps while the sound is off', async () => {
  const w = makeWorld({ stored: '0' });
  w.visit();
  w.gesture();
  await w.newRequest();
  assert.equal(w.plays.length, 0);
});

test('after a reload with the sound saved as on, the first click anywhere unlocks it: the next new request beeps', async () => {
  const w = makeWorld({ stored: '1' });                       // fresh page load: no user activation yet
  const page = w.visit();
  assert.equal(iconOf(page), 'bi bi-bell-fill');
  await w.newRequest();                                       // arrives before the user touched the page → cannot play yet
  assert.equal(w.plays.length, 0);
  w.gesture(); await w.settle();                              // any click / key press on the page
  await w.newRequest();
  assert.equal(w.plays.length >= 1, true, 'a beep must play once the browser allows it');
});

test('clicking the bell while the saved "on" is still locked unlocks it — it does not switch the sound off', async () => {
  const w = makeWorld({ stored: '1' });
  const page = w.visit();
  await w.newRequest();                                       // a missed beep is waiting
  await w.click(page.notifyToggleBtn);
  assert.equal(w.storage[LS_KEY], '1', 'still on');
  assert.equal(iconOf(page), 'bi bi-bell-fill');
  assert.equal(w.plays.length, 1, 'the beep that was waiting plays exactly once');
  assert.deepEqual(w.alerts, []);
});

test('the browser refusing audio is reported, and the sound stays off', async () => {
  const w = makeWorld();
  const page = w.visit();
  const audio = page.notifySound;
  audio.play = () => Promise.reject(new Error('NotAllowedError'));
  await w.click(page.notifyToggleBtn);
  assert.equal(w.alerts.length, 1);
  assert.notEqual(w.storage[LS_KEY], '1');
});

test('once unlocked, clicking the bell (which shows "on") switches the sound off', async () => {
  const w = makeWorld({ stored: '1' });
  const page = w.visit();
  w.gesture(); await w.settle();                              // the user clicked somewhere else first: unlocked
  await w.click(page.notifyToggleBtn);
  assert.equal(w.storage[LS_KEY], '0');
  assert.equal(iconOf(page), 'bi bi-bell-slash');
});

test('a key press also unlocks the audio, and a beep that was waiting plays once', async () => {
  const w = makeWorld({ stored: '1' });
  w.visit();
  await w.newRequest();
  assert.equal(w.plays.length, 0);
  w.key(); await w.settle();
  assert.equal(w.plays.length, 1);
  w.key(); await w.settle();
  assert.equal(w.plays.length, 1, 'and not again');
});

test('switching the sound in another tab updates this tab\'s bell', async () => {
  const w = makeWorld();
  const page = w.visit();
  assert.equal(iconOf(page), 'bi bi-bell-slash');
  w.otherTabChanges('1');
  assert.equal(iconOf(page), 'bi bi-bell-fill');
  w.otherTabChanges('0');
  assert.equal(iconOf(page), 'bi bi-bell-slash');
});

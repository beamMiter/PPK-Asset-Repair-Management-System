// A small DOM + event model for the JS tests — just enough of Element / Document / Window to run the real modules:
// the selectors they use (tag, *, #id, .class, [attr], [attr="v"], :not(...), comma lists), capture / bubble order,
// preventDefault / stopImmediatePropagation, classList, className, dataset, createElement / append / remove, textContent /
// innerHTML, a virtual clock (setTimeout / setInterval / requestAnimationFrame) and a MutationObserver you trigger by hand.
// Not a browser: it only models what the code under test touches.

function splitTop(sel) {
  const parts = []; let depth = 0, cur = '';
  for (const ch of sel) {
    if (ch === '(' || ch === '[') depth++;
    if (ch === ')' || ch === ']') depth--;
    if (ch === ',' && depth === 0) { parts.push(cur); cur = ''; } else cur += ch;
  }
  parts.push(cur);
  return parts.map((s) => s.trim()).filter(Boolean);
}

function parseCompound(s) {
  const c = { tag: null, id: null, classes: [], attrs: [], nots: [] };
  let i = 0;
  const t = s.match(/^[a-zA-Z][\w-]*/);
  if (t) { c.tag = t[0].toLowerCase(); i = t[0].length; } else if (s[0] === '*') i = 1;
  while (i < s.length) {
    const ch = s[i];
    if (ch === '#' || ch === '.') {
      const n = s.slice(i + 1).match(/^[\w-]+/)[0];
      if (ch === '#') c.id = n; else c.classes.push(n);
      i += 1 + n.length;
    } else if (ch === '[') {
      const end = s.indexOf(']', i);
      const m = s.slice(i + 1, end).match(/^([\w-]+)(?:=["']?([^"']*)["']?)?$/);
      c.attrs.push({ name: m[1], value: m[2] });
      i = end + 1;
    } else if (s.startsWith(':not(', i)) {
      let depth = 0, j = i + 4;
      for (; j < s.length; j++) { if (s[j] === '(') depth++; else if (s[j] === ')' && --depth === 0) break; }
      c.nots.push(parseCompound(s.slice(i + 5, j)));
      i = j + 1;
    } else throw new Error(`fake-dom: selector not supported: ${s}`);
  }
  return c;
}

const CASE_INSENSITIVE_ATTRS = new Set(['method', 'type']); // HTML matches these values case-insensitively

function matchCompound(el, c) {
  if (c.tag && el.tagName.toLowerCase() !== c.tag) return false;
  if (c.id && el.id !== c.id) return false;
  if (c.classes.some((k) => !el.classList.contains(k))) return false;
  for (const { name, value } of c.attrs) {
    if (!el.hasAttribute(name)) return false;
    if (value !== undefined) {
      const actual = el.getAttribute(name);
      const same = CASE_INSENSITIVE_ATTRS.has(name) ? actual.toLowerCase() === value.toLowerCase() : actual === value;
      if (!same) return false;
    }
  }
  return !c.nots.some((n) => matchCompound(el, n));
}

const compile = (sel) => splitTop(sel).map(parseCompound);

export class FakeEvent {
  constructor(type, { bubbles = true, props = {} } = {}) {
    this.type = type; this.bubbles = bubbles; this.defaultPrevented = false; this.target = null;
    this._stop = false; this._stopAll = false; Object.assign(this, props);
  }
  preventDefault() { this.defaultPrevented = true; }
  stopPropagation() { this._stop = true; }
  stopImmediatePropagation() { this._stop = true; this._stopAll = true; }
}

class Target {
  constructor() { this._listeners = []; }
  addEventListener(type, fn, opts) {
    const capture = typeof opts === 'boolean' ? opts : !!opts?.capture;
    this._listeners.push({ type, fn, capture });
  }
  removeEventListener(type, fn) { this._listeners = this._listeners.filter((l) => !(l.type === type && l.fn === fn)); }
  _fire(ev, phase) { // phase: 'capture' | 'bubble' | 'all'
    for (const l of [...this._listeners]) {
      if (l.type !== ev.type) continue;
      if (phase === 'capture' && !l.capture) continue;
      if (phase === 'bubble' && l.capture) continue;
      l.fn.call(this, ev);
      if (ev._stopAll) return;
    }
  }
}

let CURRENT = null; // the world the running test built (one at a time)

function pathOf(target) {
  const { doc, win, html } = CURRENT;
  if (target === win) return [win];
  if (target === doc) return [doc, win];
  const path = [];
  for (let e = target; e; e = e.parentElement) path.push(e);
  return path[path.length - 1] === html ? [...path, doc, win] : path; // a detached element bubbles nowhere
}

function dispatch(target, ev) {
  const path = pathOf(target);
  ev.target = target;
  for (let i = path.length - 1; i > 0 && !ev._stop; i--) path[i]._fire(ev, 'capture'); // window → … → parent
  if (ev._stop) return ev;
  path[0]._fire(ev, 'all');                                                            // the target itself
  if (ev.bubbles) for (let i = 1; i < path.length && !ev._stop; i++) path[i]._fire(ev, 'bubble');
  return ev;
}

export class FakeText {
  constructor(text) { this.nodeType = 3; this.textContent = String(text); this.parentElement = null; }
}

export class FakeElement extends Target {
  constructor(tag, attrs = {}) {
    super();
    this.tagName = tag.toUpperCase(); this.attrs = { ...attrs }; this.children = []; this.parentElement = null;
    this.dataset = new Proxy({}, { set(target, key, value) { target[key] = String(value); return true; } }); // data-* values are strings
    this.style = {}; this.cells = []; this._text = ''; this._html = '';
    this._classes = new Set((attrs.class || '').split(/\s+/).filter(Boolean));
  }
  get id() { return this.attrs.id || ''; }
  get classList() {
    const s = this._classes;
    return { add: (...c) => c.forEach((x) => s.add(x)), remove: (...c) => c.forEach((x) => s.delete(x)), contains: (c) => s.has(c) };
  }
  get className() { return [...this._classes].join(' '); }
  set className(v) { this._classes = new Set(String(v).split(/\s+/).filter(Boolean)); }
  get textContent() { return this._text + this.children.map((k) => k.textContent).join(''); }
  set textContent(v) { this.children = []; this._text = String(v); }
  get innerHTML() { return this._html; }
  set innerHTML(v) { this.children = []; this._text = ''; this._html = String(v); }
  hasAttribute(n) { return n in this.attrs; }
  getAttribute(n) { return n in this.attrs ? this.attrs[n] : null; }
  setAttribute(n, v) { this.attrs[n] = String(v); }
  removeAttribute(n) { delete this.attrs[n]; }
  append(...kids) {
    kids.forEach((k) => { const node = typeof k === 'string' ? new FakeText(k) : k; node.parentElement = this; this.children.push(node); });
    return this;
  }
  appendChild(k) { this.append(k); return k; }
  remove() {
    if (!this.parentElement) return;
    this.parentElement.children = this.parentElement.children.filter((k) => k !== this);
    this.parentElement = null;
  }
  matches(sel) { return compile(sel).some((c) => matchCompound(this, c)); }
  closest(sel) { for (let e = this; e && e.tagName; e = e.parentElement) if (e.matches(sel)) return e; return null; }
  contains(el) { for (let e = el; e; e = e.parentElement) if (e === this) return true; return false; }
  querySelectorAll(sel) {
    const cs = compile(sel), out = [];
    const walk = (e) => e.children.forEach((k) => { if (!k.tagName) return; if (cs.some((c) => matchCompound(k, c))) out.push(k); walk(k); });
    walk(this);
    return out;
  }
  querySelector(sel) { return this.querySelectorAll(sel)[0] || null; }
  dispatch(type, props = {}) { return dispatch(this, new FakeEvent(type, { props })); }
}

export function createWorld({ storage = {}, mobile = false, extraWindow = {} } = {}) {
  const world = { mobile, storage, mqls: [], observers: [], now: 0 };
  const timers = []; const frames = []; let nextId = 1;
  world.timers = timers;

  const html = new FakeElement('html');
  const body = new FakeElement('body');
  html.append(body);

  class FakeDocument extends Target {
    constructor() { super(); this.documentElement = html; this.body = body; this.readyState = 'interactive'; this.hidden = false; }
    getElementById(id) { return html.querySelectorAll('*').find((e) => e.id === id) || null; }
    querySelectorAll(sel) { return html.querySelectorAll(sel); }
    querySelector(sel) { return html.querySelector(sel); }
    createElement(tag) { return new FakeElement(tag); }
  }
  const doc = new FakeDocument();

  class FakeForm extends FakeElement {}
  class FakeObserver {
    constructor(cb) { this.cb = cb; this.connected = false; world.observers.push(this); }
    observe() { this.connected = true; }
    disconnect() { this.connected = false; }
  }
  const schedule = (fn, ms, every) => { const id = nextId++; timers.push({ id, fn, at: world.now + (ms || 0), every }); return id; };
  const cancel = (id) => { const i = timers.findIndex((x) => x.id === id); if (i >= 0) timers.splice(i, 1); };

  const win = Object.assign(new Target(), {
    document: doc,
    HTMLFormElement: FakeForm,
    MutationObserver: FakeObserver,
    location: { href: 'https://app.test/current?page=3' },
    localStorage: { getItem: (k) => (k in storage ? storage[k] : null), setItem: (k, v) => { storage[k] = String(v); } },
    setTimeout: (fn, ms) => schedule(fn, ms),
    setInterval: (fn, ms) => schedule(fn, ms, ms),
    clearTimeout: cancel,
    clearInterval: cancel,
    requestAnimationFrame: (fn) => { frames.push(fn); return frames.length; },
    matchMedia: (query) => {
      const mql = { query, get matches() { return world.mobile; }, listeners: [], addEventListener(t, f) { this.listeners.push(f); } };
      world.mqls.push(mql);
      return mql;
    },
    confirm: () => true,
    console,
    ...extraWindow,
  });
  win.window = win; win.globalThis = win;
  CURRENT = { doc, win, html };

  Object.assign(world, {
    doc, win, html, body,
    el: (tag, attrs = {}) => (tag === 'form' ? new FakeForm(tag, attrs) : new FakeElement(tag, attrs)),
    listenerCount: () => doc._listeners.length + win._listeners.length,
    listenerList: () => [...doc._listeners.map((l) => `document:${l.type}${l.capture ? '(capture)' : ''}`), ...win._listeners.map((l) => `window:${l.type}`)].sort(),
    fireDocument: (type, props = {}) => dispatch(doc, new FakeEvent(type, { props })),
    fireWindow: (type, props = {}) => dispatch(win, new FakeEvent(type, { bubbles: false, props })),
    // a Turbo visit: the whole <body> is replaced (new elements, none of the old listeners), then turbo:load fires
    visit: (build) => { body.children = []; build(body, world); world.fireDocument('turbo:load'); },
    // run the one-shot timers that are pending, whatever their delay (intervals are left alone)
    releaseTimers: () => { const due = timers.filter((x) => !x.every); due.forEach((x) => cancel(x.id)); due.forEach((x) => x.fn()); return due.length; },
    // move the virtual clock forward, running timers and intervals that fall due, in order
    advance: (ms) => {
      const target = world.now + ms;
      for (;;) {
        const due = timers.filter((x) => x.at <= target).sort((a, b) => a.at - b.at || a.id - b.id)[0];
        if (!due) break;
        world.now = due.at;
        if (due.every) due.at += due.every; else cancel(due.id);
        due.fn();
      }
      world.now = target;
    },
    flushFrames: (max = 10) => { for (let i = 0; i < max && frames.length; i++) frames.splice(0).forEach((f) => f()); },
    mutate: () => world.observers.filter((o) => o.connected).forEach((o) => o.cb([])),
    pendingTimers: () => timers.length,
  });
  return world;
}

// The character counter of a text area with a limit (resources/js/layout/char-counter.js).  Run: node --test tests/js/
//
// The browser stops typing at `maxlength` and cuts a longer paste to it, both silently: text the user thought they had written was not
// there. A text area marked data-counter shows "123 / 1000", and a paste that is cut says so.
import { build } from 'esbuild';
import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import path from 'node:path';
import { createWorld } from './helpers/fake-dom.mjs';

const code = (await build({
  entryPoints: [path.resolve('resources/js/layout/char-counter.js')],
  bundle: true, format: 'iife', globalName: 'Counter', write: false, logLevel: 'silent',
})).outputFiles[0].text;
const Counter = (() => { const box = { CustomEvent }; vm.runInNewContext(`${code}\nglobalThis.out = Counter;`, box); return box.out; })();

function boot() {
  const world = createWorld();
  world.toasts = [];
  world.win.dispatchEvent = (e) => world.toasts.push({ name: e.type, ...e.detail });
  return world;
}

/** a textarea in a wrapper, as the modals have it */
function area(world, { max = 1000, value = '', counted = true, selection = [0, 0] } = {}) {
  const wrap = world.el('div');
  const ta = world.el('textarea', { name: 'note', ...(max ? { maxlength: String(max) } : {}) });
  if (counted) ta.setAttribute('data-counter', '');
  ta.value = value;
  ta.selectionStart = selection[0];
  ta.selectionEnd = selection[1];
  wrap.append(ta);
  world.body.append(wrap);
  return ta;
}

const counterOf = (ta) => ta.nextElementSibling;
const type = (ta, text) => { ta.value = text; ta.dispatch('input'); };
const paste = (ta, text) => ta.dispatch('paste', { clipboardData: { getData: () => text } });

test('the counter registers its listeners once however often it is installed', () => {
  const world = boot();
  Counter.installCharCounter(world.win);
  Counter.installCharCounter(world.win);
  assert.deepEqual(world.listenerList(), [
    'document:DOMContentLoaded', 'document:focusin', 'document:input', 'document:paste(capture)', 'document:turbo:load',
  ]);
});

test('a text area on the page when it loads gets its counter at once, showing what it already holds', () => {
  const world = boot();
  const existing = 'มีข้อความเดิมอยู่แล้ว';
  const ta = area(world, { max: 1000, value: existing });

  Counter.installCharCounter(world.win);

  assert.equal(counterOf(ta).textContent, `${existing.length} / 1000`);
  assert.equal(counterOf(ta).getAttribute('data-char-counter'), '');
});

test('typing updates it — and it is one counter, not one per keystroke', () => {
  const world = boot();
  const ta = area(world, { max: 1000 });
  Counter.installCharCounter(world.win);

  type(ta, 'ก'.repeat(5));
  type(ta, 'ก'.repeat(12));

  assert.equal(counterOf(ta).textContent, '12 / 1000');
  assert.equal(ta.parentElement.children.filter((k) => k.tagName === 'P').length, 1);
});

test('grey, then amber from 90 %, then red at the limit', () => {
  const world = boot();
  const ta = area(world, { max: 100 });
  Counter.installCharCounter(world.win);

  type(ta, 'ก'.repeat(50));
  assert.match(counterOf(ta).className, /text-slate-400/);
  type(ta, 'ก'.repeat(90));
  assert.match(counterOf(ta).className, /text-amber-600/);
  type(ta, 'ก'.repeat(100));
  assert.match(counterOf(ta).className, /text-rose-600/);
});

test('a text area that is not there when the page loads (a dialog Alpine builds later) is counted when it is first used', () => {
  const world = boot();
  Counter.installCharCounter(world.win);
  const late = area(world, { max: 1000, value: 'abc' });

  assert.equal(counterOf(late), null);
  late.dispatch('focusin');

  assert.equal(counterOf(late).textContent, '3 / 1000');
});

test('a Turbo visit counts the text areas of the new page', () => {
  const world = boot();
  Counter.installCharCounter(world.win);

  let next;
  world.visit((body) => { next = area(world, { max: 2000, value: 'xy' }); });

  assert.equal(counterOf(next).textContent, '2 / 2000');
});

test('only text areas that opt in and have a limit are counted', () => {
  const world = boot();
  const plain = area(world, { max: 3000, counted: false });      // the chat composer
  const unlimited = area(world, { max: 0 });
  Counter.installCharCounter(world.win);
  type(plain, 'abc');
  type(unlimited, 'abc');

  assert.equal(counterOf(plain), null);
  assert.equal(counterOf(unlimited), null);
});

test('a paste that does not fit says so, with the number it was cut to', () => {
  const world = boot();
  const ta = area(world, { max: 1000, value: 'ก'.repeat(900) });
  Counter.installCharCounter(world.win);

  paste(ta, 'ข'.repeat(200));

  assert.equal(world.toasts.length, 1);
  assert.equal(world.toasts[0].type, 'warning');
  assert.equal(world.toasts[0].message, 'ข้อความที่วางยาวเกินกำหนด ระบบตัดให้เหลือ 1000 ตัวอักษร');
});

test('a paste that fits says nothing; a selection the paste replaces is not counted twice', () => {
  const world = boot();
  Counter.installCharCounter(world.win);

  const fits = area(world, { max: 1000, value: 'ก'.repeat(900) });
  paste(fits, 'ข'.repeat(100));                       // exactly the limit
  assert.deepEqual(world.toasts, []);

  const replaces = area(world, { max: 1000, value: 'ก'.repeat(990), selection: [0, 500] });
  paste(replaces, 'ข'.repeat(400));                   // 990 - 500 + 400 = 890
  assert.deepEqual(world.toasts, []);

  paste(replaces, 'ข'.repeat(600));                   // 990 - 500 + 600 = 1090
  assert.equal(world.toasts.length, 1);
});

test('a paste into a text area that is not counted is left alone', () => {
  const world = boot();
  Counter.installCharCounter(world.win);
  const plain = area(world, { max: 10, value: 'x'.repeat(10), counted: false });

  paste(plain, 'ข'.repeat(50));

  assert.deepEqual(world.toasts, []);
});

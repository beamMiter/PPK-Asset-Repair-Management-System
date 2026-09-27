// The file-input guard (resources/js/layout/file-guard.js).  Run: node --test tests/js/
//
// A file input that says what it takes (data-max-kb, data-ext) refuses a file that is too big or of the wrong kind the moment it is
// chosen, with a toast. What used to happen on the notification-sound page: the form was posted, the server refused the file, the page
// printed no errors — and reloaded as if nothing had been chosen.
import { build } from 'esbuild';
import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import path from 'node:path';
import { createWorld } from './helpers/fake-dom.mjs';

const code = (await build({
  entryPoints: [path.resolve('resources/js/layout/file-guard.js')],
  bundle: true, format: 'iife', globalName: 'Guard', write: false, logLevel: 'silent',
})).outputFiles[0].text;
const Guard = (() => { const box = { CustomEvent }; vm.runInNewContext(`${code}\nglobalThis.out = Guard;`, box); return box.out; })();

const MB = 1024 * 1024;

function boot() {
  const world = createWorld();
  world.toasts = [];
  world.win.dispatchEvent = (e) => world.toasts.push({ name: e.type, ...e.detail });
  world.guard = Guard.installFileGuard(world.win);
  return world;
}

/** the sound input of the notification page: 2 MB, mp3 / wav */
function soundInput(world, files, attrs = {}) {
  const input = world.el('input', { type: 'file', name: 'sound_file', ...attrs });
  input.dataset.maxKb = '2048';
  input.dataset.ext = 'mp3,wav';
  input.files = files;
  input.value = files.length ? `C:\\fakepath\\${files[0].name}` : '';
  input.ownOnchange = 0;
  input.addEventListener('change', () => { input.ownOnchange++; });   // the page's own onchange (the file-name label)
  world.body.append(input);
  return input;
}

const choose = (input) => input.dispatch('change');

test('the guard registers one capturing listener, once however often it is installed', () => {
  const world = boot();
  Guard.installFileGuard(world.win);
  assert.deepEqual(world.listenerList(), ['document:change(capture)']);
});

test('a file over the limit: a warning toast that names the file and the sizes, the input emptied, the page\'s own onchange never runs', () => {
  const world = boot();
  const input = soundInput(world, [{ name: 'alarm.mp3', size: 3 * MB }]);

  const ev = choose(input);

  assert.equal(world.toasts.length, 1);
  assert.equal(world.toasts[0].name, 'app:toast');
  assert.equal(world.toasts[0].type, 'warning');
  assert.equal(world.toasts[0].message, 'ไฟล์ "alarm.mp3" มีขนาด 3 MB เกินที่กำหนด (ไม่เกิน 2 MB)');
  assert.equal(input.value, '', 'nothing chosen, so `required` still stops an empty submit');
  assert.equal(input.ownOnchange, 0, 'the label of the file name must not show a refused file');
  assert.equal(ev._stopAll, true);
});

test('a file of the wrong kind: the toast says which kinds are taken', () => {
  const world = boot();
  const input = soundInput(world, [{ name: 'notes.txt', size: 1024 }]);

  choose(input);

  assert.equal(world.toasts[0].message, 'ไฟล์ "notes.txt" ไม่รองรับ รองรับเฉพาะไฟล์ .mp3 และ .wav');
  assert.equal(input.value, '');
});

test('the kind is checked before the size: a wrong file that is also big is only "not supported"', () => {
  const world = boot();
  const input = soundInput(world, [{ name: 'movie.mp4', size: 90 * MB }]);

  choose(input);

  assert.match(world.toasts[0].message, /ไม่รองรับ/);
  assert.doesNotMatch(world.toasts[0].message, /เกินที่กำหนด/);
});

test('a good file passes untouched: no toast, the choice kept, the page\'s own onchange runs', () => {
  const world = boot();
  const input = soundInput(world, [{ name: 'ding.MP3', size: 2 * MB }]);   // the limit itself is allowed; the extension's case does not matter

  choose(input);

  assert.deepEqual(world.toasts, []);
  assert.notEqual(input.value, '');
  assert.equal(input.ownOnchange, 1);
});

test('one byte over the limit is refused', () => {
  const world = boot();
  const input = soundInput(world, [{ name: 'a.wav', size: 2 * MB + 1 }]);

  choose(input);

  assert.equal(world.toasts.length, 1);
});

test('sizes read as people say them: MB with a decimal only when there is one, else KB', () => {
  assert.equal(Guard.formatSize(2 * MB), '2 MB');
  assert.equal(Guard.formatSize(2.5 * MB), '2.5 MB');
  assert.equal(Guard.formatSize(350 * 1024), '350 KB');
  assert.equal(Guard.formatSize(10), '1 KB');
});

test('several files: one bad one refuses the choice', () => {
  const world = boot();
  const input = soundInput(world, [{ name: 'a.mp3', size: 1024 }, { name: 'b.exe', size: 1024 }]);

  choose(input);

  assert.match(world.toasts[0].message, /b\.exe/);
  assert.equal(input.value, '');
});

test('a name with no extension is not a supported kind', () => {
  assert.match(Guard.fileProblem({ name: 'sound', size: 1 }, { exts: ['mp3'] }), /ไม่รองรับ/);
});

test('an input that states no limit, and things that are not file inputs, are left alone', () => {
  const world = boot();

  const free = world.el('input', { type: 'file', name: 'other' });
  free.files = [{ name: 'anything.bin', size: 900 * MB }];
  free.value = 'x';
  world.body.append(free);
  choose(free);

  const text = world.el('input', { type: 'text', name: 't' });
  world.body.append(text);
  text.dispatch('change');

  assert.deepEqual(world.toasts, []);
  assert.equal(free.value, 'x');
});

test('cancelling the file dialog (no file) is not an error', () => {
  const world = boot();
  const input = soundInput(world, []);

  assert.doesNotThrow(() => choose(input));
  assert.deepEqual(world.toasts, []);
});

test('limits come from the input\'s data attributes; a leading dot and capitals in data-ext do not matter', () => {
  const world = boot();
  const input = world.el('input', { type: 'file' });
  input.dataset.ext = ' .MP3 , wav ';
  const limits = Guard.limitsOf(input);   // built in another realm (the vm), so compared by value, not by prototype
  assert.equal(limits.maxKb, 0);
  assert.equal(JSON.stringify(limits.exts), '["mp3","wav"]');
  assert.equal(Guard.limitsOf(world.el('input', { type: 'file' })), null);
});

// The broken-image net (resources/js/image-fallback.js).  Run: node --test tests/js/
//
// A picture that will not load shows a "no picture" placeholder instead of the browser's broken-image icon. These tests pin
// what gets swapped, what is left alone (an image that was never asked for, the placeholder itself, a good SVG) and that the
// listeners are registered once however many Turbo visits follow.
import { build } from 'esbuild';
import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import path from 'node:path';
import { readFileSync } from 'node:fs';
import { createWorld } from './helpers/fake-dom.mjs';

const code = (await build({
  entryPoints: [path.resolve('resources/js/image-fallback.js')],
  bundle: true, format: 'iife', globalName: 'Fallback', write: false, logLevel: 'silent',
})).outputFiles[0].text;
const Fallback = (() => { const box = {}; vm.runInNewContext(`${code}\nglobalThis.out = Fallback;`, box); return box.out; })();

const EXPECTED = ['document:DOMContentLoaded', 'document:error(capture)', 'document:turbo:load'].sort();

function boot() {
  const world = createWorld();
  world.fallback = Fallback.installImageFallback(world.win);
  return world;
}

// a picture the way the browser reports it once it has finished (or given up on) loading
const img = (world, src, props = {}) => {
  const el = world.el('img', src === null ? {} : { src });
  Object.assign(el, { complete: true, naturalWidth: 40, ...props });
  world.body.append(el);
  return el;
};

test('a picture that fails to load gets the placeholder', () => {
  const world = boot();
  const el = img(world, '/storage/attachments/gone.jpg', { naturalWidth: 0 });
  el.dispatch('error');
  assert.equal(el.getAttribute('src'), Fallback.NO_IMAGE_URL);
});

test('the placeholder is an svg in the src itself, not a request that could fail too', () => {
  assert.ok(Fallback.NO_IMAGE_URL.startsWith('data:image/svg+xml;charset=utf-8,'));
  const svg = decodeURIComponent(Fallback.NO_IMAGE_URL.split(',')[1]);
  assert.match(svg, /^<svg[^>]+viewBox="0 0 120 120"/);
});

test('it is the same drawing as the file the asset pages use for "no photo"', () => {
  const file = readFileSync('public/images/equipment/default.svg', 'utf8');
  const drawing = (s) => s.match(/<(?:rect|circle|path)[^>]*>/g).join('\n');
  assert.equal(drawing(decodeURIComponent(Fallback.NO_IMAGE_URL.split(',')[1])), drawing(file));
});

test('a srcset would win over the new src, so it goes', () => {
  const world = boot();
  const el = img(world, '/a.jpg', { naturalWidth: 0 });
  el.setAttribute('srcset', '/a.jpg 1x, /a@2x.jpg 2x');
  el.dispatch('error');
  assert.equal(el.hasAttribute('srcset'), false);
  assert.equal(el.getAttribute('src'), Fallback.NO_IMAGE_URL);
});

test('a picture that never had a src is left alone (an empty src also fires `error`)', () => {
  const world = boot();
  const empty = img(world, '');
  const none = img(world, null);
  empty.dispatch('error');
  none.dispatch('error');
  assert.equal(empty.getAttribute('src'), '');
  assert.equal(none.hasAttribute('src'), false);
});

test('the placeholder failing too does not loop', () => {
  const world = boot();
  const el = img(world, Fallback.NO_IMAGE_URL, { naturalWidth: 0 });
  el.dispatch('error');
  el.dispatch('error');
  assert.equal(el.getAttribute('src'), Fallback.NO_IMAGE_URL);
});

test('an error on something that is not a picture does nothing', () => {
  const world = boot();
  const script = world.el('script', { src: '/x.js' });
  world.body.append(script);
  script.dispatch('error');
  assert.equal(script.getAttribute('src'), '/x.js');
});

test('a picture that failed before the module ran is caught by the sweep', () => {
  const world = createWorld();
  const failed = img(world, '/storage/gone.png', { naturalWidth: 0 });
  const fine = img(world, '/storage/ok.png');
  const loading = img(world, '/storage/slow.png', { complete: false, naturalWidth: 0 });
  Fallback.installImageFallback(world.win); // the page is already parsed: the sweep runs at once
  assert.equal(failed.getAttribute('src'), Fallback.NO_IMAGE_URL);
  assert.equal(fine.getAttribute('src'), '/storage/ok.png');
  assert.equal(loading.getAttribute('src'), '/storage/slow.png', 'still loading is not failed');
});

test('the sweep leaves an svg alone: some browsers report width 0 for a good one', () => {
  const world = createWorld();
  const file = img(world, '/images/dashboard/world-map.svg', { naturalWidth: 0 });
  const inline = img(world, 'data:image/svg+xml;charset=utf-8,%3Csvg%2F%3E', { naturalWidth: 0 });
  Fallback.installImageFallback(world.win);
  assert.equal(file.getAttribute('src'), '/images/dashboard/world-map.svg');
  assert.ok(inline.getAttribute('src').startsWith('data:image/svg+xml;charset=utf-8,%3Csvg'));
});

test('an svg that fires `error` is still swapped: it did fail', () => {
  const world = boot();
  const el = img(world, '/images/equipment/missing.svg', { naturalWidth: 0 });
  el.dispatch('error');
  assert.equal(el.getAttribute('src'), Fallback.NO_IMAGE_URL);
});

test('every Turbo visit is swept, and new pictures are covered by the same listener', () => {
  const world = boot();
  world.visit((body, w) => {
    const el = w.el('img', { src: '/storage/gone.jpg' });
    Object.assign(el, { complete: true, naturalWidth: 0 });
    body.append(el);
  });
  assert.equal(world.body.querySelector('img').getAttribute('src'), Fallback.NO_IMAGE_URL);

  const later = img(world, '/storage/added-by-a-script.jpg', { naturalWidth: 0 });
  later.dispatch('error');
  assert.equal(later.getAttribute('src'), Fallback.NO_IMAGE_URL);
});

test('the listeners are registered once, and neither Turbo visits nor a second install add any', () => {
  const world = boot();
  assert.deepEqual(world.listenerList(), EXPECTED);
  assert.strictEqual(Fallback.installImageFallback(world.win), world.fallback);
  for (let i = 0; i < 12; i++) world.visit(() => {});
  assert.deepEqual(world.listenerList(), EXPECTED);
});

// The sort dropdown and name search of the technician rating board (resources/js/maintenance/rating/technician-board.js).
// Run: node --test tests/js/
//
// What used to be wrong: the controls were wired by an inline script on DOMContentLoaded, which never fires on a Turbo
// visit — so after opening the page from the menu neither the sort dropdown nor the search box did anything.
import { build } from 'esbuild';
import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import path from 'node:path';
import { createWorld } from './helpers/fake-dom.mjs';

const code = (await build({
  entryPoints: [path.resolve('resources/js/maintenance/rating/technician-board.js')],
  bundle: true, format: 'iife', globalName: 'Board', write: false, logLevel: 'silent',
})).outputFiles[0].text;
const Board = (() => { const box = { URL }; vm.runInNewContext(`${code}\nglobalThis.out = Board;`, box); return box.out; })();  // the browser's URL, handed to the sandbox

// the board page: filters (search + sort) and a table whose 3rd column is the technician's name
function boardPage(body, world, names = ['Somchai Jaidee', 'Wanna Chan', 'สมหญิง ใจดี']) {
  const search = world.el('input', { id: 'techSearch' }); search.value = '';
  const sort = world.el('select', { id: 'sortSelector' }); sort.value = 'impact_desc';
  const tbody = world.el('tbody');
  tbody.rows = names.map((n, i) => {
    const row = world.el('tr');
    row.cells = [world.el('td'), world.el('td'), Object.assign(world.el('td'), { textContent: n })];
    row.cells[0].textContent = String(i + 1);
    return row;
  });
  tbody.rows.push(Object.assign(world.el('tr'), { cells: [world.el('td')] })); // the "no data" style row: one cell
  const overlay = world.el('div', { id: 'loaderOverlay' });
  body.append(search, sort, world.el('table').append(tbody), overlay);
  return { search, sort, tbody, overlay, rows: tbody.rows };
}
const init = (world) => Board.initBoard(world.doc, world.win);

test('sortUrl sets the order, drops the page number and keeps the other filters', () => {
  assert.equal(Board.sortUrl('https://app.test/technicians?page=3&q=a', 'score_desc'), 'https://app.test/technicians?q=a&sort=score_desc');
  assert.equal(Board.sortUrl('https://app.test/technicians?sort=impact_desc', 'count_desc'), 'https://app.test/technicians?sort=count_desc');
});

test('filterRows matches the name case-insensitively and leaves rows with fewer than three cells alone', () => {
  const world = createWorld();
  const { rows } = boardPage(world.body, world);
  Board.filterRows(rows, 'wanna');
  assert.deepEqual(rows.map((r) => r.style.display), ['none', '', 'none', undefined]);
  Board.filterRows(rows, '');
  assert.deepEqual(rows.map((r) => r.style.display), ['', '', '', undefined]);
  Board.filterRows(rows, 'สมหญิง');
  assert.deepEqual(rows.map((r) => r.style.display), ['none', 'none', '', undefined]);
});

test('after a Turbo visit the sort dropdown reloads the page in the chosen order and shows the spinner', () => {
  const world = createWorld();
  world.visit((body) => boardPage(body, world));
  init(world);
  const sort = world.doc.getElementById('sortSelector');
  sort.value = 'score_desc';
  sort.dispatch('change');
  assert.equal(world.win.location.href, 'https://app.test/current?sort=score_desc');   // ?page=3 is gone
  assert.ok(world.doc.getElementById('loaderOverlay').classList.contains('show'));
});

test('after a Turbo visit typing in the search box filters the table', () => {
  const world = createWorld();
  world.visit((body) => boardPage(body, world));
  init(world);
  const search = world.doc.getElementById('techSearch');
  search.value = 'somchai';
  search.dispatch('keyup');
  const rows = world.doc.querySelector('tbody').rows;
  assert.deepEqual(rows.map((r) => r.style.display), ['', 'none', 'none', undefined]);
});

test('calling initBoard on every visit binds each control once, and the next visit binds its own new controls', () => {
  const world = createWorld();
  let page;
  for (let visit = 1; visit <= 5; visit++) {
    world.visit((body) => { page = boardPage(body, world); });
    init(world); init(world);                 // turbo:load handler + the late-load fallback
    assert.equal(page.sort._listeners.length, 1, `visit ${visit}: sort`);
    assert.equal(page.search._listeners.length, 1, `visit ${visit}: search`);
  }
});

test('it adds no document or window listeners at all (nothing can pile up across visits)', () => {
  const world = createWorld();
  for (let i = 0; i < 5; i++) { world.visit((body) => boardPage(body, world)); init(world); }
  assert.equal(world.listenerCount(), 0);
});

test('on any other page it does nothing and does not throw', () => {
  const world = createWorld();
  world.visit((body) => body.append(world.el('div')));
  assert.doesNotThrow(() => init(world));
  assert.equal(world.listenerCount(), 0);
});

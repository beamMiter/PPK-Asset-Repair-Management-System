// The initials avatar (resources/js/avatar.js) — the twin of App\Support\InitialsAvatar (tests/Feature/InitialsAvatarTest.php).
// Run: node --test tests/js/
import { build } from 'esbuild';
import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import path from 'node:path';

const code = (await build({
  entryPoints: [path.resolve('resources/js/avatar.js')],
  bundle: true, format: 'iife', globalName: 'Avatar', write: false, logLevel: 'silent',
})).outputFiles[0].text;
const Avatar = (() => { const box = {}; vm.runInNewContext(`${code}\nglobalThis.out = Avatar;`, box); return box.out; })();

const svgOf = (url) => {
  assert.ok(url.startsWith('data:image/svg+xml;charset=utf-8,'), 'a data URI, not a link to another site');
  return decodeURIComponent(url.slice('data:image/svg+xml;charset=utf-8,'.length));
};
const lettersOf = (url) => svgOf(url).match(/<text[^>]*>(.*?)<\/text>/su)[1];

test('the first letter of the first two words, in capitals', () => {
  assert.equal(Avatar.initials('อรทัย สุขสันต์'), 'อส');
  assert.equal(Avatar.initials('somchai jaidee'), 'SJ');
  assert.equal(Avatar.initials('  somchai   jaidee  kaew '), 'SJ');
  assert.equal(Avatar.initials('Admin'), 'A');
});

test('a leading Thai vowel is not an initial', () => {
  assert.equal(Avatar.initials('เกียรติ ศักดิ์'), 'กศ');
  assert.equal(Avatar.initials('ใจดี ไชยา'), 'จช');
  assert.equal(Avatar.initials('แมว'), 'ม');
});

test('nothing to draw gives a question mark', () => {
  assert.equal(Avatar.initials(''), '?');
  assert.equal(Avatar.initials('   '), '?');
  assert.equal(Avatar.initials(undefined), '?');
});

test('a name cannot break out of the svg', () => {
  const svg = svgOf(Avatar.initialsAvatarUrl('<script>alert(1)</script> "><img src=x onerror=1>'));
  assert.ok(!svg.includes('<script') && !svg.includes('<img'));
  assert.equal((svg.match(/<text/g) || []).length, 1);
});

test('the colour is the one the PHP side gives the same name (crc32 of the lower-cased name, modulo the palette)', () => {
  // the values App\Support\InitialsAvatar::colorFor gives, worked out by PHP — a drift in the crc32 here changes everyone's colour
  assert.equal(Avatar.colorFor('user'), '374151');
  assert.equal(Avatar.colorFor('somchai'), 'DB2777');
  assert.equal(Avatar.colorFor('SomChai'), 'DB2777', 'case does not matter');
  assert.equal(Avatar.colorFor('อรทัย'), '0E2B51');
  assert.equal(Avatar.colorFor('Admin Test'), '374151');
  assert.equal(Avatar.colorFor('ใจดี'), '7C3AED');
});

test('it is a square of the asked size, carrying the initials', () => {
  const svg = svgOf(Avatar.initialsAvatarUrl('อรทัย สุขสันต์', 96));
  assert.ok(svg.includes('width="96"') && svg.includes('height="96"') && svg.includes('viewBox="0 0 100 100"'));
  assert.equal(lettersOf(Avatar.initialsAvatarUrl('อรทัย สุขสันต์')), 'อส');
});

// How loud the sound that tells staff a repair job or a chat message came in is (resources/js/layout/notify-volume.js).  Run: node --test tests/js/
//
// Everybody heard it at the full volume of the file, and the only thing a person could do about it was to switch it off. Now each person sets
// a level for themselves from the top bar; it is kept in THIS browser (next to the on/off switch of the speaker button), starts at 50%, never
// goes below 10% (silence is the speaker button's job) and is applied to both players (the new-job sound and the chat sound) the moment it
// changes, and again after every Turbo visit, which replaces the <audio> elements.
import test from 'node:test';
import assert from 'node:assert/strict';
import {
  VOLUME_KEY, DEFAULT_VOLUME, MIN_VOLUME, MAX_VOLUME,
  clampVolume, readVolume, writeVolume, applyVolume, installNotifyVolume,
} from '../../resources/js/layout/notify-volume.js';

const storageOf = (initial = {}, { blocked = false } = {}) => {
  const data = { ...initial };
  if (blocked) {
    const refuse = () => { throw new Error('SecurityError'); };
    return { data, api: { getItem: refuse, setItem: refuse } };
  }
  return { data, api: { getItem: (k) => (k in data ? data[k] : null), setItem: (k, v) => { data[k] = String(v); } } };
};

// the top bar + the phone's menu each have a slider and a label; the layout has the two players
function page(initial = {}, opts = {}) {
  const { data, api } = storageOf(initial, opts);
  const handlers = { doc: {}, win: {} };
  const world = { data, plays: [], handlers };
  const range = () => { const r = { value: '', closest: (sel) => (sel === '[data-notify-volume-range]' ? r : null) }; return r; };
  const label = () => ({ textContent: '' });
  const audio = (id) => {
    const a = { id, volume: 1, currentTime: 9 };
    a.play = () => { world.plays.push({ id, volume: a.volume, currentTime: a.currentTime }); return Promise.resolve(); };
    return a;
  };
  world.ranges = [range(), range()];
  world.labels = [label(), label()];
  world.audios = { notifySound: audio('notifySound'), chatNotifySound: audio('chatNotifySound') };
  world.doc = {
    addEventListener: (type, f) => (handlers.doc[type] ||= []).push(f),
    getElementById: (id) => world.audios[id] || null,
    querySelectorAll: (sel) => (sel === '[data-notify-volume-range]' ? world.ranges : sel === '[data-notify-volume-value]' ? world.labels : []),
  };
  world.win = { document: world.doc, localStorage: api, addEventListener: (type, f) => (handlers.win[type] ||= []).push(f) };
  world.fire = (where, type, ev = {}) => (handlers[where][type] || []).forEach((f) => f({ type, ...ev }));
  world.slide = (el, value) => { el.value = String(value); world.fire('doc', 'input', { target: el }); };
  world.click = (target) => world.fire('doc', 'click', { target });
  world.testButton = { closest: (sel) => (sel === '[data-notify-volume-test]' ? world.testButton : null) };
  return world;
}

test('without a saved level the sound is at half, and a saved one is read back, kept between 10 and 100', () => {
  assert.equal(DEFAULT_VOLUME, 50);
  assert.equal(readVolume(page().win), 50);
  assert.equal(readVolume(page({ [VOLUME_KEY]: '70' }).win), 70);
  assert.equal(readVolume(page({ [VOLUME_KEY]: '3' }).win), MIN_VOLUME, 'never below the floor');
  assert.equal(readVolume(page({ [VOLUME_KEY]: '500' }).win), MAX_VOLUME, 'never above the file itself');
  assert.equal(readVolume(page({ [VOLUME_KEY]: 'loud' }).win), 50, 'rubbish is the default');
  assert.equal(readVolume(page({ [VOLUME_KEY]: '' }).win), 50, 'an empty value is the default, not 0');
});

test('a browser that refuses storage still gets the default, and writing does not throw', () => {
  const w = page({}, { blocked: true }).win;
  assert.equal(readVolume(w), 50);
  assert.equal(writeVolume(w, 80), 80, 'the level that was asked for is returned even if it could not be kept');
});

test('writing clamps, rounds and keeps the level under the saved key', () => {
  const p = page();
  assert.equal(writeVolume(p.win, 64.6), 65);
  assert.equal(p.data[VOLUME_KEY], '65');
  assert.equal(writeVolume(p.win, 0), MIN_VOLUME);
  assert.equal(writeVolume(p.win, 250), MAX_VOLUME);
  assert.equal(clampVolume(NaN), DEFAULT_VOLUME);
});

test('a player is set to the saved level as a fraction, and a missing player is not a problem', () => {
  const p = page({ [VOLUME_KEY]: '30' });
  applyVolume(p.audios.notifySound, p.win);
  assert.equal(p.audios.notifySound.volume, 0.3);
  assert.doesNotThrow(() => applyVolume(null, p.win));
});

test('installing draws every slider and label from the saved level and sets both players', () => {
  const p = page({ [VOLUME_KEY]: '40' });
  installNotifyVolume(p.win);
  assert.deepEqual(p.ranges.map((r) => r.value), ['40', '40']);
  assert.deepEqual(p.labels.map((l) => l.textContent), ['40%', '40%']);
  assert.equal(p.audios.notifySound.volume, 0.4);
  assert.equal(p.audios.chatNotifySound.volume, 0.4, 'the chat sound follows the same level');

  const fresh = page();
  installNotifyVolume(fresh.win);
  assert.equal(fresh.audios.notifySound.volume, 0.5, 'a person who never touched it hears half');
});

test('moving a slider keeps the level, moves the other slider and the labels, and sets both players at once', () => {
  const p = page();
  installNotifyVolume(p.win);
  p.slide(p.ranges[0], 80);
  assert.equal(p.data[VOLUME_KEY], '80');
  assert.deepEqual(p.ranges.map((r) => r.value), ['80', '80'], 'the phone menu\'s slider follows the bar\'s');
  assert.deepEqual(p.labels.map((l) => l.textContent), ['80%', '80%']);
  assert.equal(p.audios.notifySound.volume, 0.8);
  assert.equal(p.audios.chatNotifySound.volume, 0.8);

  p.slide(p.ranges[1], 2);
  assert.equal(p.data[VOLUME_KEY], String(MIN_VOLUME), 'a value below the floor is raised to it');
  assert.equal(p.ranges[1].value, String(MIN_VOLUME));
});

test('something else that is typed in is not a slider', () => {
  const p = page();
  installNotifyVolume(p.win);
  p.fire('doc', 'input', { target: { value: '99', closest: () => null } });
  assert.equal(VOLUME_KEY in p.data, false);
});

test('a Turbo visit brings new <audio> elements and sliders: they are set again from the saved level', () => {
  const p = page({ [VOLUME_KEY]: '60' });
  installNotifyVolume(p.win);
  p.audios.notifySound.volume = 1; p.audios.chatNotifySound.volume = 1;   // the new body's players start at full
  p.ranges.forEach((r) => { r.value = '50'; });
  p.fire('doc', 'turbo:load');
  assert.equal(p.audios.notifySound.volume, 0.6);
  assert.equal(p.audios.chatNotifySound.volume, 0.6);
  assert.deepEqual(p.ranges.map((r) => r.value), ['60', '60']);
});

test('the level changed in another tab is taken over here', () => {
  const p = page();
  installNotifyVolume(p.win);
  p.data[VOLUME_KEY] = '90';
  p.fire('win', 'storage', { key: 'something.else' });
  assert.equal(p.audios.notifySound.volume, 0.5, 'another key is not ours');
  p.fire('win', 'storage', { key: VOLUME_KEY });
  assert.equal(p.audios.notifySound.volume, 0.9);
  assert.equal(p.ranges[0].value, '90');
});

test('the test button plays the new-job sound from the start at the saved level, whether or not the speaker is on', () => {
  const p = page({ [VOLUME_KEY]: '25' });
  installNotifyVolume(p.win);
  p.audios.notifySound.volume = 1;   // whatever it was left at
  p.click(p.testButton);
  assert.deepEqual(p.plays, [{ id: 'notifySound', volume: 0.25, currentTime: 0 }]);
});

test('a click on anything else does not play, and a page with no player does not break the test button', () => {
  const p = page();
  installNotifyVolume(p.win);
  p.click({ closest: () => null });
  assert.deepEqual(p.plays, []);
  p.audios = {};
  p.doc.getElementById = () => null;
  assert.doesNotThrow(() => p.click(p.testButton));
});

test('the settings page can set a player to the level through window.applyNotifyVolume', () => {
  const p = page({ [VOLUME_KEY]: '35' });
  installNotifyVolume(p.win);
  const preview = { volume: 1 };
  p.win.applyNotifyVolume(preview);
  assert.equal(preview.volume, 0.35);
});

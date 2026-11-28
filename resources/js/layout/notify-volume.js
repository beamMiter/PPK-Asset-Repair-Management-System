// How loud the sound that tells staff something came in is: the new repair request sound (#notifySound, chosen by the admin) and the chat
// sound (#chatNotifySound). Both used to play at the full volume of their file, and a person could only switch them off.
//
// Each person sets a level for THEMSELVES in the top bar (the arrow beside the speaker button; the phone's menu has the same slider). It is kept
// in this browser's localStorage, next to the on / off switch of the speaker button ('myjobs.notify.sound.enabled'): a person's phone and their
// office computer can differ, and nothing is stored on the server. It is a percentage of the file's own volume: 100% is the loudness the sound
// had before this, a page cannot make it louder. It starts at 50%, and does not go below 10%: silence is the speaker button's job.
//
// What the markup passes in, as data attributes (the top bar renders each twice, the bar's panel and the phone's menu):
//   data-notify-volume-range   the <input type="range"> (10..100, step 5)
//   data-notify-volume-value   the text that says the level ("50%")
//   data-notify-volume-test    the button that plays the new-job sound at the level, whether or not the speaker is on
//
// A browser that keeps the volume of a media element for itself (iPhone, iPad) ignores `audio.volume`: there the slider has no effect and the
// panel says so.

export const VOLUME_KEY = 'myjobs.notify.sound.volume';
export const DEFAULT_VOLUME = 50;
export const MIN_VOLUME = 10;
export const MAX_VOLUME = 100;

const PLAYERS = ['notifySound', 'chatNotifySound'];

/** A whole percent between MIN and MAX; anything that is not a number is the default. */
export function clampVolume(value) {
    const n = Number(value);
    if (!Number.isFinite(n)) return DEFAULT_VOLUME;
    return Math.min(MAX_VOLUME, Math.max(MIN_VOLUME, Math.round(n)));
}

/** The saved level in percent; the default when nothing is saved, it is rubbish, or the browser refuses storage. */
export function readVolume(win = window) {
    try {
        const raw = win.localStorage.getItem(VOLUME_KEY);
        return raw === null || raw === '' ? DEFAULT_VOLUME : clampVolume(raw);
    } catch {
        return DEFAULT_VOLUME;
    }
}

/** Keep a level (clamped) and return it. A browser that refuses storage still gets the level back; it just is not kept. */
export function writeVolume(win, value) {
    const level = clampVolume(value);
    try { win.localStorage.setItem(VOLUME_KEY, String(level)); } catch { /* storage blocked */ }
    return level;
}

/** Set a player to the saved level. No player (a page without the speaker) is nothing to do. */
export function applyVolume(audio, win = window) {
    if (audio) audio.volume = readVolume(win) / 100;
}

/**
 * Wire the slider(s) and the test button once for the whole session. Turbo Drive replaces <body> on every visit, so the listeners are on the
 * document and the sliders and players are looked up when they are needed; every visit (turbo:load) draws them again from the saved level.
 */
export function installNotifyVolume(win = window) {
    const doc = win.document;

    const sync = () => {
        const level = readVolume(win);
        doc.querySelectorAll('[data-notify-volume-range]').forEach((el) => { el.value = String(level); });
        doc.querySelectorAll('[data-notify-volume-value]').forEach((el) => { el.textContent = `${level}%`; });
        PLAYERS.forEach((id) => applyVolume(doc.getElementById(id), win));
    };

    doc.addEventListener('input', (e) => {
        const range = e.target?.closest?.('[data-notify-volume-range]');
        if (!range) return;
        writeVolume(win, range.value);
        sync();
    });

    doc.addEventListener('click', (e) => {
        if (!e.target?.closest?.('[data-notify-volume-test]')) return;
        const audio = doc.getElementById('notifySound');
        if (!audio) return;
        applyVolume(audio, win);
        audio.currentTime = 0;
        // the click is the gesture the browser wants before it lets a page play
        audio.play()?.catch?.((err) => win.console?.warn?.('[Notify] test sound blocked:', err));
    });

    doc.addEventListener('turbo:load', sync);
    win.addEventListener('storage', (e) => { if (e.key === VOLUME_KEY) sync(); });

    // for the admin's sound preview, an inline script of a page that cannot import this module
    win.applyNotifyVolume = (audio) => applyVolume(audio, win);

    sync();
}

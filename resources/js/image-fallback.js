// A picture that will not load — a file that was deleted from the disk, a link that has gone stale — shows a plain "no picture"
// placeholder instead of the browser's broken-image icon. Pages that know they have no picture (an asset with no photo, a user
// with no avatar) already render their own default; this is the net under every <img> for the ones that only fail on the wire.
//
// The placeholder is a small SVG carried in the `src` itself (`data:image/svg+xml,…`), so it needs no request that could fail
// too. It is the same drawing as public/images/equipment/default.svg, which the asset pages use for "no photo".
//
//   installImageFallback()   call once; a second call returns the first handle instead of registering again
const SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120" fill="none">'
    + '<rect x="20" y="28" width="80" height="64" rx="9" stroke="#cbd5e1" stroke-width="5"/>'
    + '<circle cx="42" cy="49" r="7" fill="#cbd5e1"/>'
    + '<path d="M26 80 L47 60 L62 74 L73 64 L94 84" stroke="#cbd5e1" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>'
    + '</svg>';

export const NO_IMAGE_URL = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(SVG)}`;

const INSTALLED = Symbol.for('ppk.image-fallback.installed');

const isSvgSource = (src) => /^data:image\/svg/i.test(src) || /\.svg(?:[?#]|$)/i.test(src);

/** swap a broken <img> for the placeholder; returns whether it did */
export function useFallback(img) {
    const src = img.getAttribute('src');
    if (!src || src === NO_IMAGE_URL) return false; // nothing was asked for, or the placeholder itself: never loop
    img.removeAttribute('srcset');                  // a srcset would win over the new src
    img.setAttribute('src', NO_IMAGE_URL);
    return true;
}

export function installImageFallback(win = window) {
    if (win[INSTALLED]) return win[INSTALLED];

    const doc = win.document;

    // `error` does not bubble, but it can be caught on the way down: one listener covers every image, including the ones a
    // Turbo visit or a script adds later.
    doc.addEventListener('error', (event) => {
        const el = event.target;
        if (el && el.tagName === 'IMG') useFallback(el);
    }, true);

    // An image can fail before this module has run (module scripts wait for the page to be parsed) and its `error` is then gone.
    // A raster image that finished loading with no width is a failed one. SVGs are left out: without a width and height of their
    // own some browsers report 0 for a perfectly good one.
    const sweep = () => {
        for (const img of doc.querySelectorAll('img')) {
            if (img.complete && img.naturalWidth === 0 && !isSvgSource(img.getAttribute('src') || '')) useFallback(img);
        }
    };

    doc.addEventListener('DOMContentLoaded', sweep);
    doc.addEventListener('turbo:load', sweep);
    if (doc.readyState !== 'loading') sweep(); // this module may run after the page was parsed

    return (win[INSTALLED] = { sweep });
}

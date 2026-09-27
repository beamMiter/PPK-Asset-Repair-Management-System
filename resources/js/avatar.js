// The avatar of someone with no photo: their initials on a coloured square, as a small SVG carried in the `src` itself
// (`data:image/svg+xml,…`) — no request to another site. The PHP twin is App\Support\InitialsAvatar (which draws every avatar
// the server renders); this one draws the chat lines that arrive after the page has loaded. Keep the two alike.

export const PALETTE = ['0D8ABC', '0E2B51', '16A34A', '7C3AED', 'EA580C', 'DB2777', '374151'];

// เ แ โ ใ ไ are written before the consonant they follow in sound: the initial of เกียรติ is ก
const LEADING_VOWELS = 'เแโใไ';

// crc32 of the UTF-8 bytes of the text — PHP's crc32() — so a name gets the same colour on the server and here
function crc32(str) {
    const bytes = unescape(encodeURIComponent(str)); // one character per UTF-8 byte
    let crc = -1;
    for (let i = 0; i < bytes.length; i++) {
        crc ^= bytes.charCodeAt(i);
        for (let k = 0; k < 8; k++) crc = (crc >>> 1) ^ (0xEDB88320 & -(crc & 1));
    }
    return (crc ^ -1) >>> 0;
}

export function colorFor(name) {
    const key = (name || 'user').toLowerCase();
    return PALETTE[crc32(key) % PALETTE.length];
}

/** one letter per word, two words at most; `?` when there is nothing to draw */
export function initials(name) {
    let letters = '';
    for (const word of String(name || '').trim().split(/\s+/).filter(Boolean)) {
        const first = [...word].find((ch) => !LEADING_VOWELS.includes(ch) && /[\p{L}\p{N}]/u.test(ch));
        if (!first) continue;
        letters += first.toUpperCase();
        if ([...letters].length === 2) break;
    }
    return letters || '?';
}

const escapeXml = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&apos;');

export function initialsAvatarUrl(name, size = 80) {
    const text = initials(name);
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 100 100">`
        + `<rect width="100" height="100" fill="#${colorFor(name)}"/>`
        + `<text x="50" y="50" dy=".34em" text-anchor="middle" fill="#ffffff" font-weight="700" font-size="${[...text].length > 1 ? 40 : 46}"`
        + ` font-family="Tahoma,'Leelawadee UI','Noto Sans Thai',Arial,sans-serif">${escapeXml(text)}</text></svg>`;
    return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
}

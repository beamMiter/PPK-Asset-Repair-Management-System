// A file input that says what it takes refuses a file that is too big or of the wrong kind the moment it is chosen — with a toast —
// instead of after an upload that comes back refused. On a page that prints no errors that used to be nothing at all: the page
// reloaded and the file was simply not there.
//
//   <input type="file" data-max-kb="2048" data-ext="mp3,wav" accept=".mp3,.wav">
//
//   data-max-kb   the largest file, in KB — the number the server's `max:` rule uses (Laravel counts a file's max in KB)
//   data-ext      the extensions it takes, comma separated; the check is on the name, `accept` only hints in the file dialog
//
//   installFileGuard()   call once; a second call returns the first handle instead of registering again
const INSTALLED = Symbol.for('ppk.file-guard.installed');

/** 2 097 152 → "2 MB", 358 400 → "350 KB" */
export function formatSize(bytes) {
    if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1).replace(/\.0$/, '')} MB`;
    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

/** What is wrong with one file for these limits, in Thai, or null. The kind is checked first: a file of the wrong kind is no use at any size. */
export function fileProblem(file, { maxKb = 0, exts = [] } = {}) {
    const name = file.name ?? '';

    if (exts.length) {
        const ext = name.includes('.') ? name.split('.').pop().toLowerCase() : '';
        if (!exts.includes(ext)) {
            return `ไฟล์ "${name}" ไม่รองรับ รองรับเฉพาะไฟล์ ${exts.map((e) => '.' + e).join(' และ ')}`;
        }
    }

    if (maxKb > 0 && file.size > maxKb * 1024) {
        return `ไฟล์ "${name}" มีขนาด ${formatSize(file.size)} เกินที่กำหนด (ไม่เกิน ${formatSize(maxKb * 1024)})`;
    }

    return null;
}

/** The limits a file input carries in its data attributes; null when it carries none. */
export function limitsOf(input) {
    const maxKb = Number(input.dataset.maxKb) || 0;
    const exts = (input.dataset.ext || '').split(',').map((e) => e.trim().toLowerCase().replace(/^\./, '')).filter(Boolean);

    return maxKb || exts.length ? { maxKb, exts } : null;
}

export function installFileGuard(win = window) {
    if (win[INSTALLED]) return win[INSTALLED];

    const doc = win.document;

    // Caught on the way down, before the input's own onchange: a refused file must not reach it (a preview, a file-name label…).
    doc.addEventListener('change', (event) => {
        const input = event.target;
        if (!input || input.tagName !== 'INPUT' || input.getAttribute('type') !== 'file') return;

        const limits = limitsOf(input);
        if (!limits) return;

        for (const file of Array.from(input.files || [])) {
            const problem = fileProblem(file, limits);
            if (!problem) continue;

            input.value = '';                 // nothing chosen, so the form's `required` still stops an empty submit
            event.stopImmediatePropagation();
            win.dispatchEvent(new CustomEvent('app:toast', { detail: { type: 'warning', message: problem, timeout: 5000 } }));
            return;
        }
    }, true);

    return (win[INSTALLED] = {});
}

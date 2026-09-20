// The two controls of the technician rating board (maintenance/rating/technicians-dashboard.blade.php): the sort
// dropdown and the name search. They used to be an inline <script> that Turbo re-ran on every visit and that only
// initialised on DOMContentLoaded — which does not fire on a Turbo visit, so after opening the page from the menu
// neither control did anything until a full reload.
//
// `initBoard()` is called on every `turbo:load` (and once when this module loads late) and binds each element once.

/** The current URL with `?sort=` set to `value`; a new order starts from the first page. */
export function sortUrl(href, value) {
    const url = new URL(href);
    url.searchParams.set('sort', value);
    url.searchParams.delete('page');
    return url.toString();
}

/** Show only the rows whose name (3rd column) contains `term`; rows with fewer cells (the "no data" row) are left alone. */
export function filterRows(rows, term) {
    const needle = String(term ?? '').toUpperCase();
    for (const row of rows) {
        if (row.cells.length < 3) continue;
        row.style.display = row.cells[2].textContent.toUpperCase().includes(needle) ? '' : 'none';
    }
}

export function initBoard(doc = document, win = window) {
    const sort = doc.getElementById('sortSelector');
    if (sort && !sort.dataset.boardBound) {
        sort.dataset.boardBound = '1';
        sort.addEventListener('change', () => {
            doc.getElementById('loaderOverlay')?.classList.add('show');
            win.location.href = sortUrl(win.location.href, sort.value);
        });
    }

    const search = doc.getElementById('techSearch');
    if (search && !search.dataset.boardBound) {
        search.dataset.boardBound = '1';
        search.addEventListener('keyup', () => {
            const tbody = doc.querySelector('tbody');
            if (tbody) filterRows(tbody.rows, search.value);
        });
    }
}

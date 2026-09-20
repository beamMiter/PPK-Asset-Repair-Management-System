// "Unsaved changes": once someone edits a field of a form that is not a GET form, following a link asks first
// (through `window.Confirm` when the confirm dialog is on the page, the browser's confirm() otherwise).
// Forms opt out with the class `no-dirty-check`.

const FORMS = 'form:not([method="GET"]):not(.no-dirty-check)';
const CONTROLS = 'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="file"]), textarea, select';

const TITLE = 'ยืนยันการออกหน้าปัจจุบัน';
const MESSAGE = 'ระบบตรวจพบว่าข้อมูลในหน้านี้ยังไม่ได้รับการบันทึก หากคุณดำเนินการต่อ การเปลี่ยนแปลงทั้งหมดจะสูญหาย';
const NATIVE_MESSAGE = 'ข้อมูลในหน้านี้ยังไม่ได้รับการบันทึก หากคุณดำเนินการต่อ การเปลี่ยนแปลงจะสูญหาย ยืนยันการออกหรือไม่?';

/** Installs the (capturing) link guard once; returns the handle the page-load step uses. */
export function installDirtyCheck(win) {
    const doc = win.document;
    let dirty = false;

    const leave = (href) => { dirty = false; win.location.href = href; };

    doc.addEventListener('click', (e) => {
        if (!dirty) return;
        const anchor = e.target.closest('a');
        if (!anchor) return;

        const href = anchor.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('javascript:')) return;

        e.preventDefault();
        e.stopImmediatePropagation();
        win.Loader?.hide();

        if (win.Confirm) {
            win.Confirm.show({ title: TITLE, message: MESSAGE, confirmText: 'ยืนยันการออก', cancelText: 'ยกเลิก', variant: 'warning' })
                .then((confirmed) => { if (confirmed) leave(href); });
        } else if (win.confirm(NATIVE_MESSAGE)) {
            leave(href);
        }
    }, true);

    return {
        get dirty() { return dirty; },
        reset() { dirty = false; },

        /** A page was rendered: start clean and watch its forms (each form and select is watched once). */
        init() {
            dirty = false;
            doc.querySelectorAll(FORMS).forEach((form) => {
                if (!form.dataset.dirtyBound) {
                    form.dataset.dirtyBound = '1';
                    const edited = (e) => {
                        if (!e.target.matches(CONTROLS)) return;
                        dirty = true;
                        e.target.classList.add('is-dirty-field');
                    };
                    form.addEventListener('input', edited);
                    form.addEventListener('change', edited);
                    form.addEventListener('submit', () => { dirty = false; });
                }

                if (win.TomSelect) {
                    form.querySelectorAll('.ts-wrapper').forEach((wrapper) => {
                        const select = wrapper.parentElement.querySelector('select');
                        if (select?.tomselect && !select.dataset.dirtyTsBound) {
                            select.dataset.dirtyTsBound = '1';
                            select.tomselect.on('change', () => {
                                dirty = true;
                                wrapper.classList.add('is-dirty-field');
                            });
                        }
                    });
                }
            });
        },
    };
}

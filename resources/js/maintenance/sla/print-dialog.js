// The print dialog of the SLA page: which late jobs go in the report, a note for the paper, and the preparer's signature.
// An Alpine component (`x-data="slaPrintDialog(rows)"`), registered in app.js so it exists before Alpine looks at the page, also on a
// Turbo visit. `rows` are every late job [{ id, no, title, dept, late }], not just the 20 the page's panel shows.
//
// Every warning is a toast (`app:toast`, resources/js/toast.js), not a browser alert().
// Run the tests: node --test tests/js/

const TOAST_SIGN = 'กรุณาลงนามในช่อง "ลายเซ็นผู้จัดทำรายงาน" ก่อนพิมพ์';
const TOAST_NO_PAD = 'ช่องลงนามยังไม่พร้อมใช้งาน กรุณารีเฟรชหน้าแล้วลองอีกครั้ง';
const TOAST_NONE_CHOSEN = 'ยังไม่ได้เลือกงานที่เกินเวลา รายงานจะไม่มีตารางรายการ กด "พิมพ์รายงาน PDF" อีกครั้งเพื่อยืนยัน';
const TOAST_BUILDING = 'กำลังสร้างรายงาน PDF...';

export function slaPrintDialog(rows, env = {}) {
    const win = env.win ?? window;
    const doc = env.doc ?? document;

    return {
        pad: null,
        rows,
        selected: rows.map((r) => r.id),
        q: '',
        note: '',
        busy: false,            // the form has been sent: a second click must not send it again
        noneConfirmed: false,   // "print without any job" has been warned about once

        // Alpine calls init() when it sets the component up. `showSignModal` belongs to the element around it.
        init() {
            this.$watch('showSignModal', (open) => {
                if (!open) return;
                this.busy = false;
                this.noneConfirmed = false;
                this.$nextTick(() => this.initPad());
            });
            // a changed choice starts the warning over
            this.$watch('selected', () => { this.noneConfirmed = false; });
        },

        toast(type, message, timeout) {
            win.dispatchEvent(new CustomEvent('app:toast', { detail: { type, message, ...(timeout ? { timeout } : {}) } }));
        },

        // ---- which jobs
        get shown() {
            const q = this.q.trim().toLowerCase();
            return q === '' ? this.rows : this.rows.filter((r) => (r.no + ' ' + r.title + ' ' + r.dept).toLowerCase().includes(q));
        },
        isOn(id) { return this.selected.includes(id); },
        toggle(id) { this.selected = this.isOn(id) ? this.selected.filter((x) => x !== id) : [...this.selected, id]; },
        selectShown() { this.selected = [...this.selected, ...this.shown.map((r) => r.id).filter((id) => !this.isOn(id))]; },
        clearShown() {
            const off = new Set(this.shown.map((r) => r.id));
            this.selected = this.selected.filter((id) => !off.has(id));
        },

        // ---- the signature
        initPad() {
            const canvas = this.$refs.canvas;
            if (!canvas) return;

            if (canvas.offsetWidth === 0) {   // the dialog is still fading in
                setTimeout(() => this.initPad(), 50);
                return;
            }

            const ratio = Math.max(win.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d').scale(ratio, ratio);

            if (typeof win.SignaturePad === 'undefined') {
                console.error('SignaturePad is not defined');
                this.toast('error', TOAST_NO_PAD);
                return;
            }

            this.pad = new win.SignaturePad(canvas, {
                backgroundColor: 'rgba(255, 255, 255, 0)',
                penColor: '#0F2D5C',
                minWidth: 1.5,
                maxWidth: 4,
            });
        },
        clearPad() { this.pad && this.pad.clear(); },

        // ---- print
        submitReport() {
            if (this.busy) return;

            if (!this.pad) {
                this.toast('error', TOAST_NO_PAD);
                return;
            }
            if (this.pad.isEmpty()) {
                this.toast('warning', TOAST_SIGN);
                return;
            }
            // a report with no job in it is allowed (a summary alone), but is rarely meant: ask once
            if (this.rows.length > 0 && this.selected.length === 0 && !this.noneConfirmed) {
                this.noneConfirmed = true;
                this.toast('warning', TOAST_NONE_CHOSEN, 6000);
                return;
            }

            doc.getElementById('sig-input').value = this.pad.toDataURL('image/png');
            doc.getElementById('tickets-input').value = this.selected.join(',');
            doc.getElementById('note-input').value = this.note;

            this.busy = true;
            this.toast('info', TOAST_BUILDING, 3000);   // the PDF takes a second or two: the page is still there until it arrives
            doc.getElementById('pdf-form').submit();
            this.showSignModal = false;
        },
    };
}

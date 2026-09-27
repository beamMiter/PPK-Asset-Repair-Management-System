// The print dialog of the SLA page (resources/js/maintenance/sla/print-dialog.js): which late jobs go in the report, a note, and the
// preparer's signature. Run: node --test tests/js/
//
// What used to be wrong: a missing signature was a browser alert(), and nothing warned about printing with no job chosen or about a
// signature pad that never started. Every warning is a toast now (the `app:toast` event, resources/js/toast.js).
import { build } from 'esbuild';
import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import path from 'node:path';

const source = (await build({
  entryPoints: [path.resolve('resources/js/maintenance/sla/print-dialog.js')],
  bundle: true, format: 'iife', globalName: 'Dialog', write: false, logLevel: 'silent',
})).outputFiles[0].text;
const Dialog = (() => { const box = { CustomEvent, setTimeout, console }; vm.runInNewContext(`${source}\nglobalThis.out = Dialog;`, box); return box.out; })();

const ROWS = [
  { id: 44, no: '691000033', title: 'อินเทอร์เน็ตห้องการเงินหลุดๆ หายๆ', dept: 'ฝ่ายการเงินและบัญชี', late: '+3 วัน 13 ชม.' },
  { id: 46, no: '691000035', title: 'รายงานการเงินใน HIS ออกไม่ครบ', dept: 'ฝ่ายการเงินและบัญชี', late: '+3 วัน 3 ชม.' },
  { id: 47, no: '691000038', title: 'เครื่องคอมพิวเตอร์ห้องฉุกเฉินทำงานช้ามาก', dept: 'เวชศาสตร์ฉุกเฉิน', late: '+1 วัน 17 ชม.' },
];

/**
 * A component as Alpine would set it up: the magics it uses ($watch, $nextTick, $refs) and `showSignModal`, which lives on the
 * element around it. Returns the component, the toasts it raised, and the form fields it filled.
 */
function mount(rows = ROWS, { SignaturePad, ratio = 2 } = {}) {
  const toasts = [];
  const watchers = {};
  const form = { submitted: 0 };
  const els = { 'pdf-form': { submit() { form.submitted++; } }, 'sig-input': {}, 'tickets-input': {}, 'note-input': {} };
  const canvas = { offsetWidth: 480, offsetHeight: 200, getContext: () => ({ scale() {} }) };
  const win = {
    devicePixelRatio: ratio,
    SignaturePad,
    dispatchEvent(e) { toasts.push({ name: e.type, ...e.detail }); },
  };

  const c = Dialog.slaPrintDialog(rows, { win, doc: { getElementById: (id) => els[id] } });
  c.showSignModal = false;
  c.$refs = { canvas };
  c.$nextTick = (fn) => fn();
  c.$watch = (key, fn) => { watchers[key] = fn; };
  c.init();

  const open = () => { c.showSignModal = true; watchers.showSignModal(true); };
  const signed = () => { c.pad = { isEmpty: () => false, toDataURL: () => 'data:image/png;base64,AAAA', clear() {} }; };
  const changeSelection = (fn) => { fn(); watchers.selected(c.selected); };   // Alpine calls the watcher when `selected` changes
  return { c, toasts, els, form, canvas, open, signed, changeSelection };
}

test('no browser alert() is left in the dialog: its warnings are toasts', () => {
  assert.doesNotMatch(source, /\balert\(/);
});

/* ------------------------------------------------------------------ the signature */

test('no signature: a warning toast, and nothing is sent', () => {
  const { c, toasts, form } = mount();
  c.pad = { isEmpty: () => true, toDataURL: () => '' };

  c.submitReport();

  assert.equal(toasts.length, 1);
  assert.equal(toasts[0].name, 'app:toast');
  assert.equal(toasts[0].type, 'warning');
  assert.match(toasts[0].message, /กรุณาลงนาม/);
  assert.equal(form.submitted, 0);
});

test('a signature pad that never started: an error toast, not the "please sign" warning, and nothing is sent', () => {
  const { c, toasts, form } = mount();
  c.pad = null;

  c.submitReport();

  assert.equal(toasts.length, 1);
  assert.equal(toasts[0].type, 'error');
  assert.match(toasts[0].message, /รีเฟรชหน้า/);
  assert.equal(form.submitted, 0);
});

test('opening the dialog starts the pad; when the library is missing the user is told', () => {
  const errors = [];
  const withLib = mount(ROWS, { SignaturePad: class { constructor(canvas, options) { this.canvas = canvas; this.options = options; } } });
  withLib.open();
  assert.ok(withLib.c.pad, 'the pad is created');
  assert.equal(withLib.canvas.width, 960, 'the canvas is sized for the screen density (480 px x 2)');
  assert.equal(withLib.c.pad.options.penColor, '#0F2D5C');

  const withoutLib = mount(ROWS, { SignaturePad: undefined });
  const log = console.error; console.error = (m) => errors.push(m);
  try { withoutLib.open(); } finally { console.error = log; }
  assert.equal(withoutLib.c.pad, null);
  assert.equal(withoutLib.toasts.length, 1);
  assert.equal(withoutLib.toasts[0].type, 'error');
  assert.match(withoutLib.toasts[0].message, /รีเฟรชหน้า/);
  assert.deepEqual(errors, ['SignaturePad is not defined']);
});

/* ------------------------------------------------------------------ printing with no job chosen */

test('choosing no job at all is warned about once, and a second press confirms', () => {
  const { c, toasts, form, els, signed } = mount();
  signed();
  c.selected = [];

  c.submitReport();
  assert.equal(form.submitted, 0, 'the first press only warns');
  assert.equal(toasts.length, 1);
  assert.equal(toasts[0].type, 'warning');
  assert.match(toasts[0].message, /ยังไม่ได้เลือกงานที่เกินเวลา/);
  assert.match(toasts[0].message, /อีกครั้งเพื่อยืนยัน/);

  c.submitReport();
  assert.equal(form.submitted, 1, 'the second press prints');
  assert.equal(els['tickets-input'].value, '', 'an empty selection is sent as empty, not left out');
});

test('changing the choice after the warning starts the warning over', () => {
  const { c, toasts, form, signed, changeSelection } = mount();
  signed();
  c.selected = [];
  c.submitReport();                                    // warned
  changeSelection(() => c.toggle(44));                 // ticked one...
  changeSelection(() => c.toggle(44));                 // ...and unticked it again

  c.submitReport();
  assert.equal(form.submitted, 0, 'still not confirmed: the choice changed in between');
  assert.equal(toasts.filter((t) => t.type === 'warning').length, 2);
});

test('with no late job at all there is nothing to warn about', () => {
  const { c, toasts, form, signed } = mount([]);
  signed();

  c.submitReport();

  assert.equal(form.submitted, 1);
  assert.deepEqual(toasts.map((t) => t.type), ['info'], 'only the "building the report" toast');
});

/* ------------------------------------------------------------------ printing */

test('a signed report with some jobs chosen: the form is filled, sent once, and the user is told it is being built', () => {
  const { c, toasts, form, els, signed } = mount();
  signed();
  c.toggle(46);                       // 44 and 47 remain
  c.note = 'รออะไหล่จากผู้ขาย';

  c.submitReport();

  assert.equal(els['sig-input'].value, 'data:image/png;base64,AAAA');
  assert.equal(els['tickets-input'].value, '44,47');
  assert.equal(els['note-input'].value, 'รออะไหล่จากผู้ขาย');
  assert.equal(form.submitted, 1);
  assert.equal(c.showSignModal, false, 'the dialog closes');
  assert.deepEqual(toasts.map((t) => [t.type, t.message]), [['info', 'กำลังสร้างรายงาน PDF...']]);

  c.submitReport();
  assert.equal(form.submitted, 1, 'a second click while the PDF is being built does not send it again');
});

test('opening the dialog again lets it print again', () => {
  const { c, form, signed, open } = mount(ROWS, { SignaturePad: class {} });
  signed();
  c.submitReport();
  assert.equal(form.submitted, 1);

  open();
  signed();
  c.submitReport();
  assert.equal(form.submitted, 2);
});

/* ------------------------------------------------------------------ choosing jobs */

test('everything is chosen to begin with, and a job can be unticked and ticked again', () => {
  const { c } = mount();
  assert.deepEqual(c.selected, [44, 46, 47]);

  c.toggle(46);
  assert.equal(c.isOn(46), false);
  assert.deepEqual(c.selected, [44, 47]);
  c.toggle(46);
  assert.equal(c.isOn(46), true);
});

test('the search narrows the list, and select all / clear act on the rows shown only', () => {
  const { c } = mount();

  c.q = 'เวชศาสตร์';
  assert.deepEqual(c.shown.map((r) => r.id), [47]);
  c.clearShown();
  assert.deepEqual(c.selected, [44, 46], 'the rows outside the search keep their tick');
  c.selectShown();
  c.selectShown();
  assert.deepEqual([...c.selected].sort(), [44, 46, 47], 'selecting twice does not duplicate');

  c.q = '691000035';
  assert.deepEqual(c.shown.map((r) => r.id), [46], 'the request number is searchable');
  c.q = 'ไม่มีทางเจอข้อความนี้';
  assert.deepEqual(c.shown, []);
});

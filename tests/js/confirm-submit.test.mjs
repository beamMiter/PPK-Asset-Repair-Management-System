// window.confirmSubmit (resources/js/layout/confirm-submit.js).  Run: node --test tests/js/
//
// A form's `onsubmit="return confirm('...')"` blocked the browser's own paint until answered and looked nothing like the
// rest of the app's dialogs. `confirmSubmit(event, options)` shows the app's own async dialog instead, in every place a
// native confirm() used to gate a form submit (admin/users suspend or reactivate, a maintenance type's "ปิดใช้งาน", an
// attachment's delete). `preventDefault()` must run before the `await`, or the browser submits the form on its own
// regardless of what a Promise later resolves to.
import { build } from 'esbuild';
import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import path from 'node:path';

const code = (await build({
  entryPoints: [path.resolve('resources/js/layout/confirm-submit.js')],
  bundle: true, format: 'iife', globalName: 'ConfirmSubmit', write: false, logLevel: 'silent',
})).outputFiles[0].text;
const ConfirmSubmit = (() => { const box = {}; vm.runInNewContext(`${code}\nglobalThis.out = ConfirmSubmit;`, box); return box.out; })();

function fakeWin(answer) {
  const calls = [];
  return {
    calls,
    win: { Confirm: { show: (options) => { calls.push(options); return Promise.resolve(answer); } } },
  };
}

function fakeEvent() {
  const submitted = { count: 0 };
  return {
    submitted,
    event: {
      defaultPrevented: false,
      preventDefault() { this.defaultPrevented = true; },
      target: { submit: () => { submitted.count++; } },
    },
  };
}

test('prevents the native submit before the dialog even opens', () => {
  const { win } = fakeWin(true);
  const { event } = fakeEvent();
  ConfirmSubmit.installConfirmSubmit(win);

  const result = win.confirmSubmit(event, { message: 'x' });
  assert.equal(event.defaultPrevented, true, 'prevented synchronously, not after the await');
  return result;
});

test('submits the form once the person confirms', async () => {
  const { win, calls } = fakeWin(true);
  const { event, submitted } = fakeEvent();
  ConfirmSubmit.installConfirmSubmit(win);

  await win.confirmSubmit(event, { title: 'ยืนยันการระงับบัญชี', message: 'ระงับบัญชี ทดสอบ ?', variant: 'warning' });

  assert.equal(submitted.count, 1);
  assert.equal(calls[0].variant, 'warning');
  assert.equal(calls[0].message, 'ระงับบัญชี ทดสอบ ?');
});

test('never submits when the person cancels', async () => {
  const { win } = fakeWin(false);
  const { event, submitted } = fakeEvent();
  ConfirmSubmit.installConfirmSubmit(win);

  await win.confirmSubmit(event, { message: 'x' });

  assert.equal(submitted.count, 0);
});

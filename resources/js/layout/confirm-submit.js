// A form whose submit used to be gated by the browser's own confirm() (`onsubmit="return confirm('...')"`) needs an
// async version to use the app's own dialog instead. `preventDefault()` runs synchronously, before the `await`, so the
// native submit never happens on its own; the form is submitted for real only once the person has answered yes.
//
//   <form onsubmit="return confirmSubmit(event, { title: '…', message: '…', variant: 'warning' })">
export function installConfirmSubmit(win = window) {
    win.confirmSubmit = async function (event, options) {
        event.preventDefault();
        const form = event.target;
        const ok = await win.Confirm.show(options);
        if (ok) form.submit();
    };
}

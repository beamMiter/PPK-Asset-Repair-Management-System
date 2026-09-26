// A chat message's time as a person reads it, on the THAI clock whatever the browser's timezone is (the same rules as
// App\Support\ThaiDate::chatTime, which draws the messages the page is loaded with):
// today "15:45", yesterday "เมื่อวาน 15:45", the last few days "วันเสาร์ 15:45", older "26 ก.ย. 2569 15:45".

const TZ = 'Asia/Bangkok';

const clockFormat = new Intl.DateTimeFormat('th-TH', { timeZone: TZ, hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
const weekdayFormat = new Intl.DateTimeFormat('th-TH', { timeZone: TZ, weekday: 'long' });                       // วันเสาร์
const dateFormat = new Intl.DateTimeFormat('th-TH', { timeZone: TZ, day: 'numeric', month: 'short', year: 'numeric' }); // 26 ก.ย. 2569
const isoDay = new Intl.DateTimeFormat('en-CA', { timeZone: TZ, year: 'numeric', month: '2-digit', day: '2-digit' });   // 2026-09-26

/** the Thai calendar day of a moment, as a number of days: two of them subtract into "how many days ago" */
function thaiDayNumber(date) {
    const [y, m, d] = isoDay.format(date).split('-').map(Number);
    return Date.UTC(y, m - 1, d) / 86_400_000;
}

/** @param {string|Date|number} value an ISO string (what the server sends), a Date or a timestamp */
export function chatTime(value, now = new Date()) {
    const at = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(at.getTime())) return '';

    const clock = clockFormat.format(at);
    const days = thaiDayNumber(now) - thaiDayNumber(at);

    if (days <= 0) return clock;                         // today (or a moment ahead of this browser's clock)
    if (days === 1) return `เมื่อวาน ${clock}`;
    if (days <= 5) return `${weekdayFormat.format(at)} ${clock}`;
    return `${dateFormat.format(at)} ${clock}`;
}

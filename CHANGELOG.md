# Changelog

All notable changes to the PPK Asset Repair Management System are documented
here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and the project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Choose which late jobs go in the printed SLA report, and a fuller report.** Printing every late job cannot always fit
  one page, so the print button now opens a dialog listing *every* late job (the panel on the page shows only the
  20 most overdue) with a tick box each, a search, select all / clear and a count. Ticking fewer prints only those, and
  the paper says so ("แสดง 8 จากงานที่เกินเวลาทั้งหมด 16 รายการ"): a cut list read as the whole picture would understate
  the backlog. Up to about ten jobs still end on one A4 page. The dialog also takes a note (ข้อสังเกต / ข้อเสนอแนะ)
  that is printed on the report. The report itself gains: the time the data is as of, who prepared it, the total number
  of jobs in the period, the person responsible and how late each job is (replacing the due date), a short "how the
  figures are worked out" so the numbers can be taken at face value, page numbers, and two signature blocks (the
  preparer's drawn on screen with their name under it, the approver's left for the paper). Long Thai text now breaks
  between words (`App\Support\ThaiText`) instead of in the middle of one. Covered by `SlaReportLayoutTest` and
  `ThaiFormattingTest`.

### Fixed

- **The printed SLA report ran to four pages and its signature did not sit on the signature line.** The report is
  rebuilt to end on one A4 page for a normal report (the sample of 16 late jobs in 8 departments was four pages; about
  ten jobs fit one page, and the dialog above lets the user choose which): a smaller body, the status split shown as figures instead of a table, the departments in two columns, and column widths taken
  from the real values (Thai has no spaces to wrap at, so a value wider than its column printed over the next one). A
  really long list of late jobs still runs on to a second page rather than being cut, with its header row repeated and
  the signature block kept whole. The signature canvas hands over its whole area, so the strokes floated a hand's
  breadth above the line: the empty margin is now trimmed (`App\Support\ReportSignature`) and the picture sits on a
  dotted line after "ลงชื่อ", with the name, the role and the date under it. The report also now states the period
  it covers (the headings said "this month" while the default is the year so far), prints dates with Thai month names
  and the Buddhist year like the SLA page, and shows the status in Thai instead of the raw code (`In_progress`). Covered
  by `SlaReportLayoutTest` and `ReportSignatureTest`; `phpunit.xml` gets a 512M memory limit because the suite, run in one
  process, was already close to the 128M default and each PDF test builds a whole dompdf document.
- **Thai tone marks vanished in every PDF: "ที่" printed as "ที", "ทั้งหมด" as "ทังหมด", "เฉลี่ย" as "เฉลีย".** A browser
  puts a tone mark on an upper vowel with the font's GSUB/GPOS tables (a smaller mark, moved up and along); dompdf reads
  neither, so it drew the mark at its default place — inside the vowel, where it cannot be seen. The layout is now left to
  HarfBuzz once: `scripts/build-thai-pdf-fonts.py` builds `sarabunpdf_normal.ttf` / `sarabunpdf_bold.ttf` (Sarabun, SIL OFL,
  plus ~100 / ~160 ready-made glyphs for vowel + tone, tone + ำ, and a lone vowel or tone over ป ฝ ฟ, reached through
  private-use characters), and `App\Support\ThaiPdfText` swaps each such cluster for its character in the HTML before dompdf
  sees it. Used by the SLA report for now; the work order and asset sheet print the same way and can adopt it with one call
  and one font-family name. The font is registered by fixed names in `installed-fonts.json` (with its `.ufm`), not by
  `@font-face`: dompdf's own registration copies the font under a hash of the machine's path and left that absolute path in
  the tracked json. `ThaiPdfTextTest`.
- **Running the test suite reset every password and signed everyone out.** `phpunit.xml` had `RefreshDatabase` running
  `migrate:fresh` against the same MySQL database the app itself (and anyone logged in) uses — every test run wiped
  the `sessions` table and reset every user's password to the seeder's, so a login attempt right after tests ran
  could fail for a real reason (wrong password, or literally no account left) that had nothing to do with the login
  code. Tests now point at a separate database on the same MySQL server (same host/port/user); the dev database is
  untouched by a full test run.
- **Filter-bar selects (สถานะ, ประเภทงาน, บทบาท, หน่วยงาน, เรียงลำดับข้อมูล) had column widths that did not match what
  their own options needed** — worked out from Sarabun's advance widths against the pixel width each `lg:col-span-N`
  resolves to. The request list's สถานะ and ประเภทงาน had it backwards (สถานะ's longest label needed more room than
  ประเภทงาน's did); the user list's บทบาท had a column to spare that หน่วยงาน needed more than it did; the technician
  rating page's เรียงลำดับข้อมูล ("ผลงานดีที่สุด (Impact Score)") was in a row with 5+ columns going unused.
  `FilterSelectWidthTest`.
- **SLA dashboard: the quick date-range shortcuts never showed which one was applied, "แสดงข้อมูล:" was wrong about
  it too, and the icon-only tools were easy to miss.** "รายงานสรุป" and "ล่าสุด" were split buttons (icon block +
  label) of their own, which the shared `<x-ui.button icon="..." split>` already draws. They are bare icons now — no
  box of any size, not even a bordered square — sized the same as the live chat page's own refresh icon
  (`chat/index.blade.php`, `#btnHeaderRefresh`) and the paperclip/camera pair elsewhere, but in a new `ghost-brand`
  variant (navy icon, navy-tinted hover, still no border or background at rest) rather than plain `ghost`'s neutral
  grey — this whole page is navy (the apply button, the active shortcut pill, every focus ring), and grey read as too
  faint to notice against it. "แสดงผล" (apply the date range) was the one filter-submit button in the app that was a
  rectangle with a word on it; it is the same round, filled, magnifying-glass icon button the request/asset/user
  list pages already use for their own search-submit button. The three quick ranges (6 เดือน / 12 เดือน / ปีนี้) were
  plain underlined links; they are a small set of pills now, the one that's applied filled navy — which needed
  knowing which one is applied, and the page already computed that once, for the "แสดงข้อมูล:" summary below the
  filters, but compared `request('from')` against dates worked out a different way (`subMonths(5)->startOfMonth()` /
  `subMonths(11)->startOfMonth()`) than the ones the links themselves send (`subMonths(6)->addDay()` /
  `subYear()->addDay()`) — so that line read "ช่วงวันที่" even right after clicking "6 เดือน" or "12 เดือน", never the
  shortcut's own name. Both places now read the one `$activeShortcut` the links' own dates produce.
  `SlaShortcutsTest`, `ButtonComponentTest`.
- **Docker: an uploaded file landed in the project folder, not a separate store.** `app` (and `web`, `worker`) bind-mount the
  whole project (`./:/var/www/html`) for live-reload dev, so a file the app wrote to `storage/app/public` (or `/private`)
  came out at that same path on the host — inside the git checkout, next to `app/` and `resources/`, not a store decoupled
  from the source tree. Two named volumes (`storage_uploads`, `storage_private`) are now layered on that path in `app`,
  `worker` and `web` (nginx reads uploads from the same `storage_uploads`, matching `.docker/nginx.conf`'s
  `location ^~ /storage/`), so a container's uploads live in their own store, not the bind-mounted project folder.
  `Storage::disk()` and the request/asset attachment code are unchanged — this is a Docker volume fix, not an app one.
- **Opening a chat thread from the widget did not mark it read.** The web chat controller only advanced a user's read
  pointer when they posted a message; viewing one (`GET /chat?thread_id=…`, what the widget's "My Topics" links to) never
  did, so a thread you had just read stayed "unread" — the widget's badge and "ใหม่" label kept alerting on it until you
  replied. Opening a thread now advances the pointer to its newest message, the same way sending one already did.
  `ChatReadTrackingTest`.
- **The "ใหม่" label in the chat widget was a boxed green pill.** Every unread row got a filled, ringed badge, which read as
  loud on a list where most rows have one. It is blue text now, no fill, no border — blue rather than red so it does not
  compete with the FAB's own red unread dot. `tests/js/chat-fab.test.mjs`.
- **A long thread title in the chat widget could run under the "ใหม่" label instead of truncating.** The title's
  `truncate` class does nothing on a flex item that refuses to shrink, and it had no `min-w-0`: a title with no early
  break point kept its full width, and the label — `shrink-0`, pushed to the end with `ml-auto` — had nowhere left to
  sit but on top of it. `min-w-0 flex-1` on the title fixes it, the same pair its own parent row already used one
  level up. `tests/js/chat-fab.test.mjs`.
- **The unread count on the chat FAB was not quite round, and the digit sat low.** `#chatBadge` had a min-width but no
  height and no line-height, so at 11px the browser's default line box was taller than the 20px min-width —
  `rounded-full` drew an oval. A fixed height equal to the min-width plus flex centring made it a circle, but the digit
  still sat low even at `leading-none`: the page's default font is Sarabun, whose ascent is unusually tall (headroom for
  Thai marks stacked above a vowel), which pushes a plain digit's ink low within a line box built from its metrics. The
  badge never shows Thai, so it now uses `font-sans` (system-ui / Segoe UI / Roboto — ordinary, balanced metrics for a
  digit) instead of the page's Sarabun. It still optically sat a little low even in that normal font (a digit's ink is
  baseline-up, so the font's own em-box centre sits a touch above it): `pb-0.5` eats 2px off the bottom of the box only —
  its height stays a fixed 20px, so the circle itself does not move — which nudges the centred content up about 1px.
  `ChatBadgeShapeTest`.
- **The "closed" dialog's backdrop did not match every other dialog's.** Assign team, reject/cancel/hold/close confirm, the
  history log and the shared confirm dialog all dim the page with `bg-slate-900/40 backdrop-blur-sm`; the dialog that pops
  up right after approving a job's repair (`_modal_post_close`, "อนุมัติผลการซ่อมบำรุงเรียบร้อยแล้ว!") used a darker tint
  and a heavier blur of its own. It now matches. `ModalBackdropTest`.
- **Login feedback:** a failed sign-in was completely silent. The auth layout now renders the
  session toast (`<x-toast />` only consumed it), the messages under the CID / password fields
  are shown, and every login message is in Thai. A lock-out after 5 attempts now says to wait N
  seconds instead of looking like "wrong password", and an expired page (419) redirects back with
  a message instead of the bare "419 | Page Expired" screen.
- **"Login successful" toast appeared twice** — the intro-finished path and the 5 s safety timer
  both fired it; whichever fires first now cancels the other.
- **Chat FAB played its sound on every page load / when opening the drawer** (unread baseline
  reset to 0 each time). It now rings only when the unread total actually goes up.
- **`npm run dev` no longer collides with another project's Vite on port 5173** (styles and
  scripts came back as HTML). It picks the first free port itself; HMR follows it. `VITE_PORT`
  still sets the starting port.
- **Print PDF on a request was a 500.** The controller rendered a view that had been left under another name, dompdf
  ignored `config/dompdf.php` (`setOptions()` replaces the whole option object) and the committed
  `installed-fonts.json` held one machine's absolute path. Fixed all three; the template now uses the model's status
  labels (it printed the raw code for "acknowledged").
- **Dashboard "เวลาเฉลี่ยปิดงาน"** averaged an unordered `limit(3000)` sample; it is now one SQL `AVG` over every
  finished request. The dashboard controller also lost 17 cached `Schema::hasColumn` probes and a dead `monthCost`.
- **Password reset in the browser never worked:** both POST handlers returned raw JSON and the e-mailed link pointed
  at an undefined `app.frontend_url`. It now redirects with a message, the link opens `/reset-password/<token>`, and
  accounts without an e-mail are told to ask an admin (JSON clients unchanged).
- **Request-type dropdown was stale for up to an hour** after an admin added / renamed / disabled a type (cached with
  no invalidation, in four copies); a **suspended account could still be suggested as a type's default assignee**.
- **A mistyped URL was a 500:** `?from=garbage` on the SLA dashboard, `GET /api/stats/assets/by-department`
  (selected a column that does not exist — failed on every call) and `?limit=-1` / `0` on the technician rating board.
- **N+1 queries** on My Jobs (team members), the request list (department) and the SLA ticket table (type).
- **The sound bell stopped working after the first page change.** It was wired once, to the buttons of the page that
  loaded first; Turbo replaces the body on every visit, so from the second page on the bell did nothing and showed the
  default icon whatever was saved. Also fixed in the same flow: a first page without a bell disabled the feature for the
  session; after a reload with the sound saved as "on" the browser blocks audio until the user touches the page, so beeps
  were swallowed while the bell claimed "on" (and clicking it switched the sound *off*) — the first click / key press now
  unlocks it, and a bell click while locked unlocks instead of switching off; the setting changed in another tab is now
  followed. `npm run test:js` (`tests/js/notify-sound.test.mjs`, 12 cases) drives the real module through a model of the
  page and Turbo.
- **The notification sound you chose was saved but never played.** The settings page says "เสียงแจ้งเตือนที่ใช้งานอยู่"
  and stores the pick, but the layout's `<audio>` was hard-wired to `new-request.mp3` (only the preview used the choice).
  It now plays the user's pick (`User::notificationSoundUrl()`), falling back to the default when the file has since been
  removed from the library; file names with spaces / Thai are URL-encoded.
- **A Pusher outage broke saves that had already succeeded.** The push runs inside the user's request and after the row
  is written, but was unguarded: creating a request showed a warning toast with the raw cURL error (so people created
  it twice), `POST /api/repair-requests` and posting a chat message (web and API) answered 500 for records that
  existed. `SafeBroadcast` now logs the failure and carries on, and the Pusher connection ships with 2 s / 4 s timeouts
  instead of Laravel's 10 s / 30 s (`PUSHER_CONNECT_TIMEOUT`, `PUSHER_TIMEOUT`). Nothing changes while Pusher works.
- **The local Sarabun web font never loaded.** `public/fonts` was deleted in `a33f009`, but the layout kept asking for
  `/fonts/Sarabun-*.woff2` (8 files, all 404). It went unnoticed because the top bar also imports Sarabun from Google
  Fonts — on a network with no internet (a hospital intranet) every page fell back to the system font. The layout now
  points at `public/images/fonts` (where the files are; SemiBold has no `.woff`, so woff2 is its only source). The
  PDFs were not affected — they embed Sarabun through the family registered in `installed-fonts.json` — so the dead
  `@font-face` blocks in the work-order and asset-sheet templates are simply removed. `FontFilesTest` fails when any
  declared font file is missing, empty or not the kind of file its extension says, and when a PDF stops embedding
  Sarabun. Not changed: the top bar's Google Fonts `@import` (part of the CDN clean-up still to come).
- **`GET /api/repair-requests/my-jobs` answered 404 to everyone** — it was declared below `/{req}`, which took "my-jobs"
  for a request id. It now sits above it and returns the caller's own jobs; `RouteReachabilityTest` fails when any
  static route is shadowed by an earlier one.
- **The app layout stacked its listeners on every page you opened.** Its scripts were inline, and Turbo Drive re-runs inline
  scripts in the `<body>` on every visit, so each visit added another copy of 16 `document` / `window` listeners (the link
  spinner, the form spinner, the sidebar, the unsaved-changes guard, a media-query listener …) — none guarded. They now live
  in `resources/js/layout/` and are registered once when that module loads; each visit only re-applies the per-page work
  (saved sidebar state, TomSelect / dropdowns / auto-growing textareas on the new page, watching its forms). Behaviour is
  unchanged, apart from three side effects that went away: the "unsaved changes" flag no longer carries over to the next page,
  each form is watched once instead of once per visit, and blocked `localStorage` no longer aborts the whole layout script.
  Covered by `tests/js/layout.test.mjs` (23 tests; run `npm run test:js`) and `LayoutScriptsTest`.
- **Technician rating board: the sort dropdown and the name search did nothing after opening the page from the menu** (they
  were wired on `DOMContentLoaded`, which does not fire on a Turbo visit; a full reload worked). They are now bound from the
  page bundle on every `turbo:load` (`technician-board.js`, `tests/js/technician-board.test.mjs`). The page's inline script also
  left an Escape-key listener behind that threw `Cannot read properties of null` on every other page.
- **The chat button's poll timer was started again by every page you opened.** Its inline script ran on each Turbo visit and
  each run added another `setInterval`, so after N pages `/chat/my-updates` was fetched N times per interval. There is one
  timer for the whole session now (30 s, the interval that was in the working copy), started by
  `resources/js/layout/chat-fab.js`; each page load only wires the freshly rendered button and drawer. The endpoint and the
  notification icon reach the module as `data-*` attributes of `#chatWidgetRoot`. `tests/js/chat-fab.test.mjs` (19 tests).
- **The live chat page did not update the open thread when you opened it from the menu, and kept running after you left it.**
  Its inline script started on `DOMContentLoaded` and on Livewire's `livewire:navigated`; neither fires on a Turbo visit, so
  new messages only appeared after a full reload. Nothing ever stopped its 5 s poll timer, its Echo channel or its Pusher
  `state_change` handler either, so they went on (the handler stacking up) on every other page. The page now lives in
  `resources/js/chat/` (`boot.js` installs it once; each page load mounts the thread on screen and the page it replaces is
  torn down on `turbo:before-render`). `wire:navigate`, `data-navigate-once` and the `livewire:*` listeners are gone — Livewire
  was removed earlier — and the Alpine methods and the search form use the layout's `window.Loader`.
  `tests/js/chat-page.test.mjs` (20 tests).
- **Toast: script and styles moved out of the component** into `resources/js/toast.js` (evaluated once per session, like the
  layout) and `resources/css/toast.css` — the file the layout and `vite.config.js` were already loading but that was an empty
  placeholder, untracked, so a clean checkout could not build. The auth layout, which also renders toasts, now loads it too.
  `Escape` now closes the toasts on screen at any time (it used to work only if it was the very first key pressed, because each
  toast registered a `{ once: true }` listener), and two declarations that were a truncated `text-shadow` and never applied are
  gone. `tests/js/toast.test.mjs` (17 tests).
- **A job's status moves one way only — through the transition service, after the permission of that step's button.**
  `PUT /maintenance/requests/{id}` / `PUT /api/repair-requests/{id}` accept a `status` from the team and used to save it into
  the row *before* the service looked at it, so the service saw "no change" and checked nothing: the state map, the
  reporter-approves rule, the SLA pause and the history were all skipped. An assigned technician could send `status=closed`
  and close an in-progress job (no `resolved_at`, no approval by the reporter), an admin could reopen a closed job
  (`closed → pending`, history "pending → pending", nobody recorded), resuming from hold left `paused_duration_minutes` at 0 and
  the SLA deadline where it was, and a reporter could cancel a pending job the cancel button refuses. Now a `status` change
  needs `MaintenanceRequestPolicy::moveTo` (the same rule as the button for that step) and then goes through
  `applyTransition` (an illegal move = 409 and the rest of the edit is not saved). `POST /api/repair-requests/{id}/transition`
  had the same gap — any assigned worker could do any step, incl. closing a job for the reporter — and asks `moveTo` too. The
  edit form of the web UI has no status field, so no page changes. `PUT` also takes an optional `note` (the reason for the move;
  on hold needs one), and answers a refused move with the state map's 409 instead of 422. Also: the `acknowledge` policy now lets
  the worker a job is already assigned to acknowledge it (accept and reject already did; the transition tests relied on it).
  `tests/Feature/RequestStatusPathTest.php` (7 tests).
- **A technician who pressed "รับเรื่อง" was thrown off the job.** Accepting did not put him on the job's team, and the job page
  only opens for the reporter, the team and admins — so the redirect after the button went back to the dashboard with "no
  permission", and so did every other technician (he also could not put it on hold or resolve it later). Accepting now adds the
  technician who pressed it (admins and supervisors who accept on someone's behalf do not become the worker), and a job that is
  accepted with nobody on it can be opened by every technician, since every technician may start it (self-dispatch). No test
  drove the acknowledge / accept / hold / resume / cancel / reject buttons before; `RequestLifecycleEndpointsTest` does.
- **The assign-team dialog had no rules.** Any technician could change the team of any job in any status, and the dialog took
  any account and an empty list: a technician who was not on a *closed* job could put himself on it (the worker who did it
  was cancelled, the job showed "in progress" again, and the rating followed the newest assignment), submitting with every box
  unticked left an in-progress job with nobody on it (from then on only an admin could hold or resolve it), and a plain
  member account could be put on a team. Now: nobody — admins included — changes the team of a closed, cancelled or rejected
  job (it is the record of who did the work); on a resolved job only admins and supervisors do; a technician changes the team
  of a job he is on (hand-over) or of a job nobody is on yet (dispatch), not somebody else's; a job that is accepted, in
  progress, on hold or resolved refuses an empty list (`PUT /api/repair-requests/{id}` with `user_ids: []` too, 422);
  and only working staff (admin, supervisor, technician roles, not suspended) can be picked, also for a hand-made request.
  `RequestAssignmentRulesTest` (7 tests).
- **An asset stayed "in repair" for good when its request moved away or was deleted, and a deleted request's number was handed
  out again.** Changing a pending request's asset (the edit form lets the reporter do it) only marked the *new* asset busy; the
  one it left kept "in repair" although nothing was open on it any more. A deleted (trashed) request kept its asset busy, and
  `generateLegacyRequestNo()` read the highest number among non-deleted rows, so the next new request got the deleted one's number
  and failed on the unique key — every new request from then on. The asset it left / deleted is now released when no other open
  request is on it, a restored request takes its asset again, and numbering counts trashed rows (by the year's prefix rather than
  a scan of `created_at`). No screen deletes a request today (`DELETE /api/repair-requests/{id}` answers 403 to everybody — the
  policy has no `delete` method, so the admin bypass is never consulted), so the deletion half is a guard for when something
  does. `RequestAssetAndNumberingTest` (5 tests).
- **The SLA clock: the type chosen later, and a job on hold.** The deadlines were worked out once, when the request was made. A
  request made without a type (optional in the form) had no deadline for good — choosing the type afterwards did nothing, so the
  SLA page never listed it — and one moved to another type kept the old type's. The deadlines are now set whenever the type is
  chosen or changed on a job that is still open (resolution = request date + the type's minutes + the time already spent on
  hold; the response deadline only until the job is acknowledged); a finished job is history, and a type without minutes leaves
  the deadline as it is. And a job on hold, whose clock is stopped, was listed under "เกินเวลา" (list, KPI, chart) as soon as its
  raw deadline passed and flipped back after it resumed: `MaintenanceRequest::slaDeadline()` moves the deadline out with the
  time on hold, so it is late only if it was already late when it was put on hold. The job page's "เกินกำหนด SLA" banner uses it too
  and no longer calls a cancelled or rejected job overdue. `RequestSlaClockTest` (9 tests).
- **Loose ends around the job buttons.** *Resolve* was allowed on a job on hold by the policy while the state map only leaves
  "on hold" for in-progress / cancelled (the button was hidden, a hand-made POST got a 409); it now follows the map. A finished
  or cancelled job only settled its team rows (done / cancelled) when it had a `technician_id`, which the assign dialog leaves empty
  — the others kept "in progress" rows for good, so the work order printed the team of a cancelled job as still working; every
  finish / cancel now settles them (and the people who were on a cancelled or rejected job can still open its page — the
  technician who cancels one is not thrown back to the dashboard). An outsider posting an invalid body to reject / cancel / hold /
  resolve got a 422 before the permission check (telling him what a valid request looks like); permission now comes first.
  Failures that were not ours (SQL with table names, a PHP error) were shown as they are in the toast / JSON of the job buttons,
  the edit and the new-request form, and the assign dialog; they are logged and replaced by a plain message (our own refusals —
  409s, "asset already disposed" — still show). A repeated move to the status a job is already in (a double click that got past
  the gate) wrote an "in progress → in progress" history row; it is now refused with 409. `RequestLifecycleHygieneTest` (7 tests).
- **The default of a select ("— ไม่ระบุ —") stayed in the field like text you had typed.** Every form select that TomSelect wraps
  (`select.ts-basic` / `.ts-department`: the asset, department and type of the request form, the asset form, the user form, the
  job-type select of My Jobs) showed its empty option as an ordinary value, in the same colour, and it stayed there while you
  typed to search. It is still the default value — a real option, so picking it from the list gives "ไม่ระบุ" back, and the first
  row of the list, set apart by a hairline — but it is drawn like a placeholder: muted grey in the field and in the list, and
  hidden the moment you type (`is-typing` on the wrapper, set from the text box's own `input` event — TomSelect's `type` event
  waits 300 ms), back when the text is deleted, the list closes or a value is picked. No button inside the field. Checked against
  the real TomSelect (row order, the submitted value, one `change` per pick). `tests/js/layout.test.mjs`, `LayoutScriptsTest`.
  Needs `npm run build` in production.
- **The foot of the Thai lower vowel ู (and ุ) was cut off in the input fields.** Thai lower vowels hang further below the baseline
  than the font's own descent (Sarabun: ู reaches 0.332 em down against a descent of 0.232 em — 4.65px against 3px at 14px), so a box
  that clips its text needs to be taller than the font's ascent + descent. *Text inputs:* the inner editor is `overflow: scroll`, and
  Chromium (`text_control_inner_elements.cc`) removes its line-height — sets it to `normal`, the font's ascent + descent — when the input
  has a **fixed height** taller than the line-height. `.ui-input` (58 fields, every `<input>` of the forms) was `h-11`, so it was cut
  exactly 3px under the baseline whatever line-height it declared (measured on a screenshot of the request form: ู had 3 rows of ink
  inside the field and 5 outside it). It now has no `height` — Chromium only looks at that — and is clamped with `min-h-11` + `max-h-11` instead (and no
  vertical padding, a 1.5rem line): every kind of field, text, number, date or select, is exactly 44px whatever its own inner layout
  (a date input has padding of its own, a number input spin buttons; an automatic height had made them taller than a text field), the
  browser centres the text, and the line-height applies. *Other text inputs and selects* (the sign-in / register forms, the profile page, the filter
  bars) have an automatic height and take `line-height: 1.5rem` from `@tailwindcss/forms`, which is 24px on the default 16px font —
  0.19px of room, none for anti-aliasing; one base rule, `line-height: max(1.5rem, 1.625em)`, raises those to 1.625 em when the font is
  big enough for 24px to be too tight (16px → 26px, so the sign-in inputs are 44px tall like the rest) and never lowers it (13px
  fields stay 24px). *Select fields (TomSelect):* the item is `overflow: hidden` for the "…" on a long value and used a 1.25rem line; it
  and its text box use 1.5rem now. `ThaiTextRoomTest` reads Sarabun's metrics from the font file, lays a line out the way Chrome does
  (whole-pixel ascent / descent, half a pixel kept for anti-aliasing), pins the fixed-height mechanism (a 14px field on `normal` is cut
  1.65px short), checks each rule at every font size in use, and fails if an `<input>` gets a fixed height (class or inline style) or an
  `<input>` / `<select>` is written with `text-xs` / `text-sm` and no `leading-*`. Not changed: textareas (they do not clip) and the rows
  of the dropdown list. Needs `npm run build` in production.
- **"Attach a file" and "take a photo" are bare icons everywhere.** The request form had a paperclip and a camera as two boxed square
  buttons; the job page had a text button "เลือกไฟล์เพิ่ม", the camera, and a third "แนบไฟล์" beside them (two buttons that read as the
  same thing), and the asset form had text buttons "เลือกรูปภาพ" / "เลือกไฟล์แนบ" beside the camera. All four places now use one
  component, `<x-ui.attach-buttons>`: a paperclip and a camera, nothing else — no box, no border, no background, just the icon with a
  soft circle on hover (the `ghost` variant at `icon-lg`, the look of the icons in the chat header). They are still real `<button>`s
  underneath, so the keyboard and screen readers work, and each says what it is on hover (`title`) and to a screen reader (`aria-label`);
  the ids their scripts bind are unchanged. On the job page the upload button ("แนบไฟล์") moved into the "ไฟล์ที่เลือก" box, which is
  hidden until a file is chosen: with nothing to upload there is no button. `tests/Feature/Ui/AttachButtonsTest.php`.
- **Job page: the paperclip and camera sit top right, and "assign the team" is a bare icon too.** The two file icons used to be a row
  inside the "ไฟล์แนบ" section; they are now the right-hand end of that section's header, in the corner where "มอบหมายทีมเจ้าหน้าที่"
  sits in the section below (and only for someone who may attach). That green text button is now a bare `group_add` icon in the same
  style (`ghost`, `icon-lg`; `title` and `aria-label` "มอบหมายทีมเจ้าหน้าที่", same id, so the dialog opens as before), on the job page
  and on the edit page. Both section headers stay on one row on a phone (the text button used to drop under the title). The request
  form, the request edit page and the asset form (the picture and the files sections) do the same, so the paperclip and camera are in
  the same corner on every page: `<x-ui.section-head>` has a new `actions` slot for icon tools at the right-hand end of the heading
  (an empty slot leaves no gap). In the request form the hint "รองรับรูปภาพ / PDF" stays as a line under the heading; in the asset form
  the labels "เลือกรูปภาพครุภัณฑ์" / "เลือกไฟล์เอกสารเพิ่มเติม" that stood above the icons are gone (the section titles say it), and the
  hidden file inputs travel with the icons, still inside the form. `AttachButtonsTest`, `AssignIconTest`, `ButtonComponentTest`.
- **The button that sends a new repair request has a tick, not a paper plane.** "ส่งใบแจ้งซ่อมบำรุง" used the `send` icon; it is `check`
  now, like the confirming buttons of the other pages. Editing a request keeps the save icon. `RequestFormSubmitIconTest`.
- **Job page: the workflow buttons are the size and shape of the buttons beside them.** รับทราบ, รับเรื่อง, ดำเนินการ, หยุดชั่วคราว,
  กลับเข้าดำเนินการ, เสร็จสิ้น, อนุมัติปิดงาน, ประเมินความพึงพอใจ, ไม่รับเรื่อง and ยกเลิกการซ่อมบำรุง were `split` buttons (the icon in
  a darker block of its own on the left, so wider and heavier on a wide screen); they are plain buttons now, the icon in front of the
  label, exactly like แก้ไข, พิมพ์ PDF and กลับ in the same row. Same 44px height as before; nothing else about them changed (colours,
  ids, forms, dialogs). The save buttons of the forms and of the cards on the job page keep the split look. `JobHeaderButtonsTest`.
- **Changing page makes far fewer requests.** Turbo Drive replaces the `<body>` on every visit and re-creates what is in it, so a normal
  page asked for about 15 things each time (30+ on lists with avatars), and Turbo 8 also prefetched the page of every link the pointer
  rested on for 100 ms (17 links in the menu, each a full render). Four of the causes are gone (`NavigationCostTest`,
  `InitialsAvatarTest`, `tests/js/avatar.test.mjs`): **(1)** `<meta name="turbo-prefetch" content="false">` in the three layouts;
  **(2)** the avatar of someone with no photo was an image from `ui-avatars.com` — 4 per page, 23 on My Jobs, 34 on the user list, and
  a hospital network with no internet waited on each — it is now their initials on a coloured square as an SVG carried in the `src`
  (`App\Support\InitialsAvatar`, and `resources/js/avatar.js` for chat lines that arrive live), same colours as before, a leading
  Thai vowel (เ แ โ ใ ไ) is not taken as the initial; **(3)** both `<audio>` elements were `preload="auto"` (one an mp3 from
  `assets.mixkit.co`) — nothing is fetched now until a sound rings; **(4)** Bootstrap's bundle and TomSelect were `<script>`s at the end
  of the body, fetched and run again by every visit (Bootstrap stacking its document listeners once more each time) — they are in the
  `<head>` with `defer`, which Turbo keeps, and still run before the layout's modules. Not changed yet: the chat widget's poll on every
  visit, the sidebar / top bar images re-created each visit, gzip and cache headers in `.docker/nginx.conf`, the chat sound's host.
- **The × on the picture of the asset form did nothing.** A hidden, never-shown "ล้างรูปภาพ" button had the same id
  (`hero_image_remove_btn`) as the round × on the preview and came first in the page, so the script bound "remove picture" to the
  button nobody could see. The dead button is gone; the × is the only element with the id.
- **The asset picker of the request form could not be searched by HIS number.** It searches the option text, which was only
  "code - name"; the HIS registry number (รหัสทะเบียน รพจ) is now part of it — `AST-001 - name (รพจ. 6500123)`, not repeated when it
  is the asset code itself (an asset registered from HIS takes the number as its code). The list is still the assets registered in
  this system: HIS is only a *mock* (`HisAssetSyncService::getMockHisData`, used by the "ดึงข้อมูล HIS" button of the asset form) until
  the real HIS API is connected. The HIS part is drawn in the colour the HIS number has on the asset table (bold blue,
  `font-semibold text-blue-700`), in the list and in the chosen value: the option carries `data-his` and `initTomSelect` renders
  it apart, while the text stays whole so the search still finds it. `RequestFormAssetOptionTest` (2 tests), `tests/js/layout.test.mjs`.
- **A form's second select never turned yellow (and did not count as an unsaved edit).** The "edited field" highlight bound a
  select through `wrapper.parentElement.querySelector('select')` — the *first* select of the wrapper's parent. TomSelect puts its
  wrapper next to the select, and the request form keeps the asset and the department in one `<section>`, so the department's
  wrapper was matched to the asset select (already bound) and skipped: it never turned yellow, and changing it did not count as an
  unsaved edit either. Selects are now bound one by one, each to its own wrapper (`ts.wrapper`). The JS tests' fake TomSelect now
  puts its wrapper beside the select like the real one — the old fake wrapped the select, which hid this.

### Added

- **Suspend an account instead of deleting it.** `users.suspended_at` (new migration — an ordinary
  `php artisan migrate`, no data touched). A suspended user keeps every record but cannot sign in (web or API; the
  message is shown only when the password is right), loses an open session / "remember me" cookie / API tokens on the
  next request (`EnsureAccountIsActive`), and is no longer offered or accepted as an assignee or in `/api/meta/users`.
  Admins suspend / reactivate from the user list and the user's edit page (not their own account); the list shows a
  "ระงับ" badge.
- **Shared UI components** (`resources/views/components/ui/`): `<x-ui.button>` (variant × size, one
  place to change a colour or height), `<x-ui.back-button>`, `<x-ui.form-actions>` (the cancel + save
  row), `<x-ui.section-head>` (numbered form section heading), and `.ui-input` / `.ui-textarea` /
  `.ui-label` / `.ui-hint` / `.ui-error` field classes in `app.css`.

### Changed

- **Every popup card has a smaller corner radius.** Assign team (the job page's own copy and the edit page's), the
  history log, the four confirm dialogs, the "closed" dialog, the shared confirm dialog, the profile photo cropper and
  the chat widget's drawer were `rounded-2xl` (16px), and the "closed" dialog was `rounded-3xl` (24px, rounder than the
  rest). All of them are `rounded-xl` (12px) now — smaller than either, and the same as each other. The rating dialog
  (`rounded-sm`, 2px) is untouched: it was already smaller than the new size, and the ask was to shrink the ones that
  were too round, not round up the one that wasn't. `DialogRadiusTest`.
- Header icons restored on the request list, assets, chat, users, maintenance-types and
  notification-settings pages (Material Symbols, same style as My Jobs).
- **Buttons only have to match inside a pattern group** (form pages, list-page row actions, dialogs, back button) —
  detail pages, staff job cards, dashboards and auth pages may look different. The four spots that had drifted:
  the user list's row actions (36px → the ~32px of the other lists), the technician rating page's "กลับ" (now
  `<x-ui.back-button>`), the global confirm dialog and the technician-rating popup's "ปิด" (now `<x-ui.button>`, 44px;
  the confirm colour still follows the caller's `variant`), and the notification settings page (four buttons at
  38 / 40 / 40 → shared buttons, the save button keeps its navy, the select beside them is 44px; the full-width
  green "เพิ่มเข้าคลังเสียง" bar of the drop zone is left as it was).
- **Browser-tab titles follow one pattern:** `<English page name> • PPK Asset Repair` on every page (was a mix of Thai
  and English, with different or missing suffixes: "สรุปใบงานซ่อม #…", "Repair Dashboard", "กระดานสนทนา",
  "…• PPK Hospital System", "…• <APP_NAME>"). The suffix is `config('app.title_suffix')`; each view only sets
  `@section('title', 'Assets')`. Names match the sidebar (Dashboard, My Jobs, Maintenance Requests, Assets, Livechat,
  Users, Request Types, Notifications, …); detail pages add the identifier ("Request #691000001",
  "Edit Request Type: Software"). `PageTitleTest` covers every page and forbids Thai page names.
- **ประเมินความพึงพอใจ page:** the header image icon is now a Material Symbol like the other pages, and its
  buttons use the shared `<x-ui.button>` ("รายละเอียด" secondary, "ประเมินงาน" the amber star button used in the
  post-close dialog, "ดูรายการ" small secondary). Buttons keep their natural width and wrap instead of stretching.
- **Buttons and form fields look the same on every create / edit page** (assets, maintenance requests,
  users, maintenance types, profile), on the technician request-detail page and on the list-page
  "create" buttons: one 44px height (the same as a field), one corner radius, one weight. Colours that
  carry meaning (reject = red, hold = amber, cancel = grey, accept = blue) are kept as variants. The
  two indigo "save" buttons in the assign-team dialog are now the standard green.
  Buttons are only as wide as their label (no fixed or stretched widths). Note: the pages load Bootstrap
  from a CDN whose `!important` `.px-4` / `.px-5` / `.gap-3`… override Tailwind's same-named classes, so
  the shared button uses `px-[16px]`-style values that Bootstrap has no twin for.
  Row-level "ดูรายละเอียด / แก้ไข" links in tables and the round search buttons are unchanged.
  The maintenance-types list was the odd one out (grey "แก้ไข", solid red "ปิดใช้งาน", no icons); its row actions now
  match the other lists (outlined emerald edit + outlined rose disable, each with an icon).
- **Request history dialog:** the header now matches the assign-team dialog (plain 36px icon, 16px title, 13px
  subtitle) and the footer "ปิดหน้าต่าง" button is gone (× or a click outside closes it). The timeline cards were redesigned: every status has its own icon (resolved and approved no longer
  share look-alike ticks — approval is a filled paper-with-tick), the "เริ่มต้น -> x" chip is replaced by
  "เปลี่ยนจาก <status>", the creation card no longer repeats its own sentence, all text is ≥ 12px, and rows that
  only carry the status in the note prefix (seeded / legacy) now show the right title and icon.
- **Refactors with unchanged behaviour** (each pinned by tests written against the old code first):
  `MaintenanceRequestController` (list visibility / ordering became `visibleTo()` / `orderedForList()` scopes),
  `AssetController` 865 → 677 lines (one `AssetInput` for the rules, messages and the "not back to active while a repair
  is open" guard), `Repair\DashboardController` (small private methods, output identical on ten filter combinations)
  and the 1,199-line request detail view (`show.blade.php` → 171 lines + eight partials; the rendered HTML of 27 pages is
  identical). 16 files lost unused imports. Login / logout / register no longer answer 204 to browsers "when testing" —
  the real redirects are now what the tests exercise.
- **Less is loaded on every page** (measured with `vite build`): the JS every page fetches went from 511 kB to 300 kB
  (gzip 165 → 94 kB) because Chart.js — 200 kB — is now fetched with `import()` only on a page that has a chart, instead of
  being part of `app.js` for the login page and everything else (`JsBundleTest` guards it). Also gone from every page: the
  Lottie player (unpinned `@latest`, no view used it), a second Alpine on the manual page, Font Awesome (six icons on the
  sound-settings page, where it is now loaded), and the top bar's `@import` of Sarabun from Google Fonts (the local files
  are used since the font fix above; weights 400–700, same family). `CdnAssetsTest` fails on an unpinned or duplicated
  third-party asset. Still loaded from a CDN: Bootstrap (css + js) and Bootstrap Icons, TomSelect, Material Symbols and
  Inter from Google Fonts, Cropper on the profile page.

- **Controllers grouped by area:** 12 controllers moved from the root of `app/Http/Controllers` into `Maintenance/` (request,
  transition, assignment, attachment, operation log, log, print, rating, SLA), `Settings/` (request types, notification sounds)
  and `Repair/` (my jobs, next to the dashboard) to match the `/maintenance`, `/settings` and `/repair` URLs. Namespace only:
  no class, method, route, URL or route name changed (the route table is identical before and after). The SLA dashboard script
  moved from `resources/js/settings/sla/` to `resources/js/maintenance/sla/`, next to its view.
- **The app layout's CSS is a file now.** ~500 lines of plain CSS (page frame, sidebar / content widths, the form and
  dirty-field looks, TomSelect overrides) were an inline `<style>` in `layouts/app.blade.php`, sent again with every page. It is
  `resources/css/layout.css`, linked from the same place in `<head>` — after the page-level `@stack('styles')` and the CDN
  stylesheets — so the cascade is unchanged (`LayoutScriptsTest` pins the position). The four `@font-face` rules stay inline
  because their URLs come from `asset()`. The file is byte-for-byte the old CSS (whitespace aside); the layout goes from 648
  to 150 lines, and each page's HTML is 14 KB lighter (the stylesheet is 7.6 KB minified and cached).
- **The top bar's CSS is a file too** (`resources/css/topbar.css`, 207 lines with its header): the bar's styles, and the
  layout variables it defines (`--topbar-h`, `--side-w`, the `--ppk-*` palette). It was an inline `<style>` at the top of `<body>`
  in every page, so it is linked right after `layout.css` — after every head style, before every style a page body brings, the
  place it always had (`LayoutScriptsTest` pins the order). No page overrides the bar, so it always applied on the app layout.
  The component (387 → 174 lines) also lost a `<script>` that held two comment lines.
- **The sidebar's CSS is a file too** (`resources/css/sidebar.css`, 0.6 KB built): the hidden scrollbar, the press effect on the
  phone close button and the full-height phone drawer. Its `<style>` sat inside `<aside>`, after the top bar's, so the file is
  linked after `topbar.css` (`LayoutScriptsTest` pins the order). No page overrides the sidebar. With this the only inline
  `<style>` left on a page of the app layout is the four `@font-face` rules (they need `asset()`); a test says so.
- **"อนุมัติผลการซ่อมบำรุง" has one icon everywhere:** `task` (a page with a tick), the one the history timeline already
  used. It was `verified` on the my-jobs list and in the manual's status legend, `fact_check` in the dashboard's status map,
  a plain `check` on the "อนุมัติปิดงาน" button (the "เสร็จสิ้น" button already shares its icon with its status, `task_alt`)
  and `check_circle` in the "approved" dialog. The manual's guide section "การตรวจสอบและปิดงาน" (its side-menu link and its
  heading) keeps `verified`: it is a section of the guide, not the status. The SLA page's `verified` (on-time rate) and
  the manual's shield note about ratings are other things and stay.
  The icon is also the same green as "ซ่อมบำรุงเสร็จสิ้น" wherever a page colours both (it was one or two shades darker):
  the manual's status legend (`emerald-600`), the status chip beside a job on my-jobs (`emerald-700`, the icon and its label
  share the class) and the dashboard's status map (`emerald-500`). Places where the colour goes on a label, a dot or an
  accent bar rather than an icon (request list, asset page, the my-jobs card bar) are unchanged.
  `ApprovalIconTest` pins each place. (The dashboard's `$statusTH` / `$statusPill` / `$statusStyle` are declared but nothing
  calls them; only the glyph name and the icon colour were changed there.)
- **SLA page: the "เกินเวลา" / "ใกล้ครบกำหนด" lists have a stated limit.** Each tab cut its rows at 20 inside the view without a
  word, so a badge saying 45 sat over a list of 20, and the search box could not find the other 25. The limit is now one
  named constant (`SlaPerformanceController::TICKET_LIST_LIMIT`, still 20), used by both tabs, and a cut list says
  "แสดง 20 รายการที่เกินเวลานานที่สุด จากทั้งหมด 45 รายการ (ค้นหาได้เฉพาะรายการที่แสดง)" (the near-due tab: "ใกล้ครบกำหนดที่สุด").
  The most overdue / the soonest due are the rows kept. The badges, the KPI counts and the PDF report keep the full list — the
  cap is applied to the screen only (a test fails if the shared list is cut).

### Removed

- **The "สร้างทะเบียนแจ้งซ่อม" button in the Main Dashboard hero.** Every role lands on this dashboard after login
  (`RouteServiceProvider::HOME`, no role branching in `DashboardController::index`), and the button went to the same
  place ("+ สร้างใบแจ้งซ่อม") the request list page already offers, one click from the sidebar. A KPI overview page —
  charts, department/asset breakdowns, technician workload, no other action on it — is not where a create button
  belongs, and it duplicated one that already exists. The hero is now just the title and the "updated" line.
  `DashboardHeroTest`.
- **Deleting a user from the app** (route `admin.users.destroy`, `UserController::destroy`, the "ลบ" buttons in the
  user list and the "danger zone" on the edit page). Users are referenced by requests, logs, assignments, ratings and
  chat with `ON DELETE CASCADE` / `SET NULL`, so a delete destroyed other people's data (whole chat threads with every
  message, the ratings a person gave, job assignments) and orphaned requests. Removing an account is now a
  database-level operation; editing a user is unchanged, and accounts can be suspended instead (see Added).
- Unused files: `components/_form-standard.blade.php` (a template with `{{ page_title }}`
  placeholders), `maintenance/requests/partials/_form_submit.blade.php` and
  `_form_operation_log.blade.php` (nothing included them).
- **Dead code:** `/repair/queue` (its view was deleted in #8 — a guaranteed 500), `MaintenanceAssignmentService`,
  unrouted controller methods and 200 lines of commented-out controllers, the duplicated `GET|POST /register`, the
  unreachable confirm-password controller + view, the `tech-only` (defined twice, differently) and `admin-only` gates,
  and `DELETE /profile` — a hidden self-delete that still hard-deleted the caller and cascaded like the user-admin one.
- `DELETE /maintenance/requests/{req}/assignments/{assignment}`: it threw a TypeError on every call and nothing used it
  (the team is edited through `assignments.store`).
- **Seeders: 13 files / 2,700 lines → 5 files.** `DatabaseSeeder` now runs `ReferenceDataSeeder` (roles, 12 departments, 8 asset
  categories, 5 request types), `UserSeeder` (15 people), `DemoDataSeeder` (35 assets, 71 requests) and `ChatSeeder`
  (6 threads). Seven seeders that nothing called (`AssetSeeder`, `DepartmentSeeder`, `DevAdminSeeder`, …) and the
  overlapping ones (`MockUsers`, `DemoRating`, `AdminEvaluation`, …) are gone; the data is deterministic (no faker), relative
  to "now", and seeds in under a second instead of ~9 s. It fixes what was wrong in it: the Hardware type's default role was
  `support` (no such role), the chat was pinned to a hard-coded user id 409, and demo accounts with known passwords were
  seeded on any environment — production now gets the reference data only. The requests are built the way the app builds
  them (timeline by status, lead = `technician_id`, one log row per allowed transition, SLA dates from the type, ratings only
  on closed requests by their reporter, assets `in_repair` exactly while they have an open request) and cover the situations
  the screens handle: every status, SLA breached / about to breach / met, on hold and resumed, a three-person team, a
  hand-over, four unrated requests for the admin plus one whose rating window has passed, last year's requests, disposed
  assets, a suspended technician's history, a request with no asset / no type / a switched-off type, a member with no e-mail.
  `SeederIntegrityTest` checks all of that (and that production gets only reference data). Logins: `1234567890123` /
  `Dev12345!` (admin, the developer account) and `10000000000NN` / `12345678` for the other roles.
- **Dead front-end code:** the 350-line "SearchSelect" script in `app.js` and the two components it served
  (`search-select`, `searchable-select` — no view used them), `intro-frame-reveal.js` (imported by nothing) and
  `assets/_tomselect.blade.php` (included by nothing).
- **The unused queue leftovers:** `composer dev` no longer starts `php artisan queue:listen` (nothing in the app is queued —
  the push events are `ShouldBroadcastNow`, mail and notifications are sent inline), `/api/health` no longer reports a
  "queue" check (it only built the connection object, so it said ok whatever the queue's state), and the breadcrumb
  label of the removed `/repair/queue` page is gone. `HealthEndpointTest` also fails if a queued job / listener / mail is
  ever added, as a reminder to bring a worker and a real check back. The `jobs` tables from Laravel's default migration
  are left alone (empty, harmless).
- **Unused files (clean-up):** 18 Blade views and the `AppLayout` / `Icon` component classes that nothing rendered (Breeze's
  `modal`, `dropdown`, `nav-link` … , `app-icon`, `stat-card`, `layouts/layout`, `layouts/navigation`, `dynamic-search-dropdown`,
  `partials/repair-action`), the unregistered `app/Exceptions/Handler.php`, `config/reverb.php` (the reverb server package is
  not installed), the placeholder `tests/Unit/ExampleTest.php`, five Lottie animations (the toast has used inline SVG since
  2026-04-26), 19 Sarabun font files that no page or PDF loads (1.4 MB — both PDFs render identically without them), an
  unused icon, the README logo copy `imagesREADME/PPK.png` (byte-identical to `public/images/logoppk.png`) and `.styleci.yml`.
  Everything is in git history.
- **The technician board's "rating detail" modal** (markup, `openRatingModal` / `renderRatingModal` and their Escape listener):
  nothing ever called it — the rows link to the full rating page — and it wrote the rating comments and names it fetched
  into `innerHTML` unescaped, so it would have been a stored XSS the day something opened it. The JSON branch of
  `MaintenanceRatingController::summary()` that fed it is still there.
- **The e-mail verification scaffolding from Breeze:** three controllers, the `verified` middleware alias, the `verify-email`
  view and the three `verification.*` routes. Nothing ever sent a link (`User` is not `MustVerifyEmail`) or redirected to
  it, people sign in with the citizen id and some have no e-mail. Forgot / reset password are unchanged.
- **Unused packages:** `laravel/scout`, `spatie/laravel-query-builder`, `livewire/livewire`, `blade-ui-kit/blade-icons` and
  `codeat3/blade-simple-icons` (registered 3,413 icon components at every boot, none used), `laravel/breeze` (dev) and the
  npm `@tailwindcss/line-clamp` (Tailwind 3.4 has `line-clamp-*` built in and the plugin was never in the config). No other
  package changed version. Livewire also registered 9 routes on its own (`/livewire-…/update`, `/upload-file`,
  `/preview-file/{filename}`, its JS and source maps) — they are gone with it. **After pulling this: `composer install`, `npm install` and `php artisan view:clear`** — views
  compiled while Livewire was installed call its classes and answer 500 until the compiled-view cache is cleared.
  The layout's leftover `livewire:navigated` listeners went too (that event never fired: Livewire's JS was never on a page);
  the chat page still has its own.
- **21 model methods nobody called that were a trap or a copy** — `MaintenanceAssignment::mark*` (state changes that skip the
  transition rules, the log and the asset sync), `User::rating_average` / `rating_count` (an N+1 per read), `Toast::isSuccess`,
  a second copy of the asset sort whitelist and of the operation-log upsert, and pure wrappers. 21 other unused relations,
  predicates and one-line scopes were kept on purpose.

### Security

- **`GET /debug/login` signed anyone in as user 410** — no middleware, no password (`Auth::loginUsingId(410)`), in the
  routes since the UI-polish merge. Removed together with `/debug/whoami`; `NoDebugRoutesTest` forbids any "debug"
  route from coming back.
- **A reporter could assign staff to, or wipe the team of, their own request — and back-date it.** In
  `MaintenanceRequestService::updateRequest` the "not a team member" branch read `user_ids` before stripping it and never
  stripped `request_date`, so `PUT /maintenance/requests/{id}` (or the API twin) with `user_ids` / `user_ids: []` /
  `request_date` changed the team and the date every SLA figure starts from. Both are now ignored for non-team users;
  staff behave as before and no screen sent these fields.
- **`GET /api/search/maintenance-requests` leaked every request's number, title and status to any signed-in user** (it
  read the table directly: no visibility rule, and soft-deleted rows included). It now follows the request list: a member
  only finds their own, deleted requests never appear.
- **Private attachments were readable by every signed-in user** (`GET /attachments/{id}` with a guessed id) and ignored
  `expires_at`. A file is now as visible as what it is attached to (request policy / asset policy; orphans: uploader or
  admin) and an expired one answers 410.
- **System management is admin-only.** The sidebar "การจัดการระบบ" pages — maintenance types, notification
  sounds and user admin — now sit behind one `manage-system` gate (admin role). Maintenance types and the
  notification settings used to be open to supervisors and every worker role, and the notification controller
  only turned `member` away (a deny-list of one), so any staff account could add or delete sound files in the
  shared `public/sounds` library. Route middleware, controller middleware, the type policy and the sidebar all use
  the same gate, and the SLA page's "จัดการประเภทงาน" shortcut is hidden for other roles. The SLA dashboard and
  technician rating board keep their existing `maintenance-type-manage` gate (supervisors / workers).
  Note: only admins can now choose their notification sound on the settings page.
- **Stored XSS in the chat button's "My Topics" drawer.** The drawer (on every page of the app) built its rows with
  `innerHTML` from `/chat/my-updates`, which returns thread titles, sender names and message text exactly as typed. Anyone who
  could post in a thread — every role — could run script in the browser of everyone who had taken part in it, on every page
  they opened, as that user. The rows are now built from DOM nodes with `textContent` (`buildItem` in `chat-fab.js`), and the
  test posts `<img onerror>` / `<script>` payloads through the title, sender, message and avatar URL. The chat page itself
  (`chat/index.blade.php`) was not part of this change.
- **Stored XSS through a display name on the live chat page.** A message that arrives while the page is open (Echo or the
  poll) was built with `innerHTML`, with the sender's name interpolated as-is. Users can change their own name on the profile
  page, so anyone could run script in the browser of everyone who had that thread open. Rows are DOM nodes with
  `textContent` now (`buildMessageRow`); message bodies were already set as text, and the messages the server renders were
  always escaped.

## [2.0.0] - 2026-09-10

Framework modernisation plus a full feature-by-feature logic and security audit.
No data migration is required beyond running `php artisan migrate`.

### Upgraded

- Laravel 11 → **13**, PHP requirement raised to **^8.3**.
- Livewire 3 → **4**.
- PHPUnit 12 → **13**.
- Carbon 2 → **3** — `diffIn*()` now returns a signed float; all SLA, rating
  and dashboard call sites were audited for direction and casting.
- `intervention/image` 3 → **4** (avatar pipeline now uses `decodePath()` +
  `encodeUsingFileExtension()`).
- `laravel/scout`, `laravel/tinker`, `spatie/laravel-query-builder`,
  `laravel/sanctum` moved to their current majors.
- Frontend stays on Tailwind CSS **3.4** + daisyUI 5 (a Tailwind 4 migration
  was attempted and reverted — it could not coexist with the CDN Bootstrap
  reset).

### Security

- **Notification sounds:** `destroySound()` built a filesystem path from the
  raw `file_name` request field, allowing directory traversal and deletion of
  files outside `public/sounds`. Input is now `basename()`d, extension-checked
  and confirmed to resolve inside the sounds directory. `updateSound()` no
  longer accepts an arbitrary string.
- **User admin:** the `manage-users` gate allowed every worker role — it is now
  admin-only, so `/admin/users` (full staff list incl. citizen IDs) is no
  longer exposed to IT staff.
- **Technician rating board:** `rating.technicians` and
  `technicians/{user}/rating-summary` had no authorization and leaked every
  technician's scores plus reviewer names and comments to any signed-in user;
  both now sit behind `maintenance-type-manage`.
- **SLA report:** the `signature` field is validated as an inline image data
  URI before it reaches the generated PDF.

### Fixed

- **Assets:** hero-image upload 500'd under MySQL strict mode
  (`attachments.order_column` was unsigned but the hero sentinel is `-1`;
  column is now signed). `POST /api/assets` validated uploads then dropped
  them. `warranty_start` was missing from the edit form. `hero_image_url`
  caused an N+1 on the asset list API. HIS sync could not create an asset
  (`asset_code` never mapped).
- **Rating:** the "pending evaluation" list showed jobs the rating guard then
  rejected (list filter now matches `withinRatingWindow()`). The API rating
  endpoint attributed scores to a nullable column and never auto-closed a
  resolved job — it now mirrors the web path. The eligibility window, the
  technician resolver and the score rules are shared via
  `App\Traits\HandlesMaintenanceRating`.
- **Maintenance requests:** an invalid status transition returned 500 instead
  of 409. Cancelling a job left its assignments `in_progress` instead of
  `cancelled`. `POST /api/repair-requests` returned a 302 redirect instead of
  a JSON 422 on validation failure. Team/technician pick-lists used a
  hard-coded role list instead of `User::teamRoles()`.
- **Stats:** `/api/stats/summary` and the technician summary counted neither
  `acknowledged` (open) nor `rejected` (terminal) requests.
- **SLA:** the distribution chart double-counted every SLA-compliant ticket.
- **Attachments:** `GET /attachments/{attachment}` read `path`/`disk`/`mime`
  from the `Attachment` row (those columns live on `File`), so every private
  download 404'd.
- **Chat:** the web controller predated `chat_thread_reads` — it never
  advanced a read pointer on send and always reported zero unread; the API
  controller never broadcast new messages. Both now share
  `App\Traits\HandlesChatReads`.
- **Repair dashboard:** the KPI cards read "this year / last year" and query
  year-to-date, but the payload keys were named `thisMonth` / `lastMonth`.
- **Auth:** login and registration tests still posted `email`; updated to
  `citizen_id`.

### Removed

- Orphaned, unrouted code: `AssetCategoryController` + its views,
  `AttachmentDownloadController`, the `admin.users.bulk` route, the unused
  `assets/_fields.blade.php` partial, dead state-machine methods on
  `MaintenanceRequest`, and a legacy top-level `UserController` /
  `Store|UpdateUserRequest`.
- Dead post-validation `str_replace` on the asset `price` field.

## [1.0.0]

- Initial internal release: asset register, maintenance request lifecycle,
  technician assignment, SLA tracking, satisfaction rating, live chat.

# Changelog

All notable changes to the PPK Asset Repair Management System are documented
here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and the project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

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

- Header icons restored on the request list, assets, chat, users, maintenance-types and
  notification-settings pages (Material Symbols, same style as My Jobs).
- **Buttons only have to match inside a pattern group** (form pages, list-page row actions, dialogs, back button) —
  detail pages, staff job cards, dashboards and auth pages may look different. The four spots that had drifted:
  the user list's row actions (36px → the ~32px of the other lists), the technician rating page's "กลับ" (now
  `<x-ui.back-button>`), the global confirm dialog and the technician-rating popup's "ปิด" (now `<x-ui.button>`, 44px;
  the confirm colour still follows the caller's `variant`), and the notification settings page (four buttons at
  38 / 40 / 40 → shared buttons, the save button keeps its navy, the select beside them is 44px; the full-width
  green "เพิ่มเข้าคลังเสียง" bar of the drop zone is left as it was).
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

### Removed

- **Deleting a user from the app** (route `admin.users.destroy`, `UserController::destroy`, the "ลบ" buttons in the
  user list and the "danger zone" on the edit page). Users are referenced by requests, logs, assignments, ratings and
  chat with `ON DELETE CASCADE` / `SET NULL`, so a delete destroyed other people's data (whole chat threads with every
  message, the ratings a person gave, job assignments) and orphaned requests. Removing an account is now a
  database-level operation; editing a user is unchanged, and accounts can be suspended instead (see Added).
- Unused files: `components/_form-standard.blade.php` (a template with `{{ page_title }}`
  placeholders), `maintenance/requests/partials/_form_submit.blade.php` and
  `_form_operation_log.blade.php` (nothing included them).

### Security

- **System management is admin-only.** The sidebar "การจัดการระบบ" pages — maintenance types, notification
  sounds and user admin — now sit behind one `manage-system` gate (admin role). Maintenance types and the
  notification settings used to be open to supervisors and every worker role, and the notification controller
  only turned `member` away (a deny-list of one), so any staff account could add or delete sound files in the
  shared `public/sounds` library. Route middleware, controller middleware, the type policy and the sidebar all use
  the same gate, and the SLA page's "จัดการประเภทงาน" shortcut is hidden for other roles. The SLA dashboard and
  technician rating board keep their existing `maintenance-type-manage` gate (supervisors / workers).
  Note: only admins can now choose their notification sound on the settings page.

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

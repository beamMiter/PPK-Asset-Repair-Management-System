# Changelog

All notable changes to the PPK Asset Repair Management System are documented
here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and the project uses [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

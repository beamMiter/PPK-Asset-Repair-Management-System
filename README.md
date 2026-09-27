<p align="center">
  <img src="public/images/logoppk2.png" width="400" alt="PPK Logo">
</p>

# PPK Asset & Repair Management System

Asset and repair-management system for Phra Pokklao Hospital (โรงพยาบาลพระปกเกล้า).

## Project Overview

Built to raise the standard of asset and equipment repair management within the organization, with an emphasis on data
transparency, service speed (SLA), and accurate statistics — working toward preventive maintenance planning down the line.

## Key Features

- **Repair requests & status tracking**: report an issue through the system with photo attachments, and track its status in real time
- **Asset Registry**: links equipment records, repair history and usage status together automatically
- **SLA Dashboard**: tracks response and resolution speed against their targets
- **Technician Leaderboard**: rates and ranks technicians by their work and the satisfaction scores they receive
- **Live Chat**: an organization-wide message board (threads), messages in real time, a moderator can lock/delete a thread, and a thread nobody uses is automatically locked and later deleted after a set period (not tied to any particular job)
- **User manual**: the "คู่มือการใช้งาน" menu (`resources/views/help/manual.blade.php`) is kept in step with whatever feature changes — `ManualIsCurrentTest` checks that every button, menu name and number it cites still matches the system

## Design & UI Standards

To keep the system professional and easy to use (user-centric design), it follows these standards:

- **Typography**: Inter as the primary typeface
    - Titles: font-semibold (600)
    - Body: font-medium (500) or font-normal (400)
    - Black (900) weight is avoided, for a cleaner look
- **Flat UI**: a minimal, flat visual style
    - Fewer shadows on cards and buttons
    - Sections are separated with background colour and light borders instead
- **Data integrity**: an asset's status and its repair requests stay in sync at all times

## Access

- **Application URL**: [http://localhost:8000](http://localhost:8000)

### Authentication (trial system)

Sign-in here is a **trial setup for local / internal testing**, not the final login. People register themselves on `/register` (13-digit
citizen ID, name, password) and get a plain member account; an admin then sets their role and department. Nothing checks that the
citizen ID belongs to the person who typed it, and that is deliberate for now.

The real login will be built on the hospital's own personnel database, once this system is connected to it and the staff records can be
pulled from there. Until then, treat every account as unverified, and do not put this instance where the public can reach it. The HIS
asset lookup is in the same state: `HisAssetSyncService::getMockHisData()` is a mock that answers for any number, to be replaced by the
hospital's API (the field mapping is in `mapHisPayload()`, in one place).

### Development notes

- **Vite dev server**: starts on port **5173** (set via `VITE_PORT`) and moves itself to the next free port if another project is
  already using it — the port actually used is written to `public/hot`
- If the port setup changes, check `vite.config.js` and `docker-compose.yml`

## Technical Stack

- **Backend**: Laravel 13 (PHP 8.3+; the image runs 8.4)
- **Frontend**: Tailwind CSS 3.4, Alpine.js, Turbo, Blade templates, Vite 7
- **Database**: MySQL / MariaDB
- **Real-time**: Laravel Broadcasting over Pusher (private channels) — without a connection the chat page polls every 5 seconds
  (once connected, that drops to a 15-second safety-net poll), and the floating chat widget checks every 30 seconds
- **Queue / Cache**: Redis (optional)

## Running it

```bash
docker compose up -d --build        # app (php-fpm), web (nginx :8000), db, redis, worker, scheduler, node (vite :5173)
docker compose exec app php artisan migrate:fresh --seed    # a fresh dev database with demo data (never on real data)
```

| Service | What it does |
|---|---|
| `app` | PHP-FPM; the only container that runs migrations (`RUN_MIGRATIONS`) |
| `web` | nginx on `:8000`: static files, `/build` (cached for a year, gzip), `/storage` uploads (served sandboxed, with `nosniff`) |
| `worker` | `queue:work` |
| `scheduler` | `schedule:work` — **required**: it runs `chat:expire-idle` (03:00 Thai time), `chat:purge-deleted` (03:10) and `sanctum:prune-expired`; without it nothing scheduled ever runs |
| `db`, `redis`, `node` | MariaDB, Redis, the Vite dev server |

PHP limits live in `.docker/php.ini` (uploads 12 MB a file, 40 MB a request, `memory_limit` 512M, opcache); nginx's `client_max_body_size`
(`.docker/nginx.conf`) is kept equal to `post_max_size`. In a production deployment set `PHP_OPCACHE_VALIDATE_TIMESTAMPS=0` so PHP stops
re-reading files that never change.

## Settings worth knowing (`.env`)

| Key | What it is for | If unset |
|---|---|---|
| `TRUSTED_PROXIES` | The proxy / load balancer address(es) in front of nginx (comma separated, CIDR allowed; `*` only if the app is reachable through the proxy alone) | nobody is a proxy — right for a machine with none in front. **Behind one, set it**: otherwise every user shares the proxy's address (the sign-in limit locks everybody together, records name the proxy) and https is not detected |
| `APP_TRUSTED_HOSTS` | Host names besides `APP_URL`'s that the app may be reached by in production | any other Host header gets a 400 |
| `BROADCAST_CONNECTION`, `PUSHER_APP_*` | Real-time chat and notifications | the chat page polls every 5 s instead of receiving pushes |
| `CHAT_*` (see `config/chat.php`) | Flood limits, threads per day (5), messages loaded per page (30), and how long a conversation lives: idle → locked after 90 days (`CHAT_LOCK_IDLE_AFTER_DAYS`), locked → deleted after 90 more (`CHAT_DELETE_LOCKED_AFTER_DAYS`), deleted → erased after 30 (`CHAT_PURGE_DELETED_AFTER_DAYS`); `0` turns a rule off | the defaults in that file |
| `SANCTUM_TOKEN_EXPIRATION_MINUTES` | API token lifetime | 30 days |

## Tests

```bash
php artisan test        # PHP - uses its own MySQL database (see phpunit.xml), never the dev one
npm run test:js         # the browser-side logic (Node's test runner, a fake DOM)
```

## Where things live

`app/Http/Controllers` (web, and `Api/` for the token API), `app/Services` (logic that reads or writes the database),
`app/Support` (small helpers with no database), `app/Events/Chat` (chat broadcast events), `app/Console` (artisan commands),
`resources/js/<page>/` (one bundle per page, listed in `vite.config.js`), `tests/Feature` (grouped by area: `Chat/`, `Infra/`, `Ui/`, `Auth/`),
`openapi.yaml` (the API), `CHANGELOG.md` (what changed, `[Unreleased]` first).

---

Developed for PPK Hospital. All rights reserved.

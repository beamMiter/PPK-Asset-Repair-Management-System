# PPK Asset & Repair Management System

ระบบบริหารจัดการงานซ่อมบำรุงและทะเบียนทรัพย์สิน โรงพยาบาลพระปกเกล้า

## ภาพรวมโครงการ (Project Overview)

ระบบนี้ถูกพัฒนาขึ้นเพื่อยกระดับการจัดการงานซ่อมบำรุงทรัพย์สินและครุภัณฑ์ภายในองค์กร โดยเน้นความโปร่งใสของข้อมูล ความรวดเร็วในการให้บริการ (SLA) และการสรุปผลเชิงสถิติที่แม่นยำ เพื่อนำไปสู่การวางแผนซ่อมบำรุงเชิงป้องกัน (Preventive Maintenance) ในอนาคต

## คุณสมบัติหลัก (Key Features)

- **ระบบแจ้งซ่อมและติดตามสถานะ**: แจ้งซ่อมผ่านระบบพร้อมแนบรูปถ่าย และติดตามสถานะแบบ Real-time
- **ระบบทะเบียนทรัพย์สิน (Asset Registry)**: เชื่อมต่อข้อมูลครุภัณฑ์ ประวัติการซ่อม และสถานะการใช้งานอัตโนมัติ
- **SLA Dashboard**: ติดตามความเร็วในการตอบสนอง (Response) และการแก้ไขปัญหา (Resolution) เทียบกับเป้าหมาย
- **Technician Leaderboard**: ระบบประเมินประสิทธิภาพและจัดอันดับช่างตามผลงานและความพึงพอใจ
- **Live Chat Communication**: ช่องทางสื่อสารระหว่างผู้แจ้งและช่างซ่อมภายในใบงาน

## มาตรฐานการออกแบบ (Design & UI Standards)

เพื่อให้ระบบมีความเป็นมืออาชีพและใช้งานง่าย (User-Centric Design) จึงมีการกำหนดมาตรฐานดังนี้:

- **Typography**: ใช้ฟอนต์ Inter เป็นมาตรฐานหลัก
    - หัวข้อ (Titles): font-semibold (600)
    - เนื้อหา (Body): font-medium (500) หรือ font-normal (400)
    - งดใช้ความหนาระดับ Black (900) เพื่อความสะอาดตา
- **Flat UI Initiative**: เน้นการออกแบบสไตล์ Minimal Flat
    - ลดการใช้เงา (Shadows) ในระดับ Card และ Button
    - เน้นการใช้สีพื้นหลังและเส้นขอบ (Borders) ที่บางเบาเพื่อแยกส่วนการใช้งาน
- **Data Integrity**: ข้อมูลสถานะครุภัณฑ์และใบแจ้งซ่อมจะเชื่อมโยงกัน (Sync) ตลอดเวลา

## การเข้าใช้งานระบบ (Access)

- **Application URL**: [http://localhost:8000](http://localhost:8000)

### Authentication (trial system)

Sign-in here is a **trial setup for local / internal testing**, not the final login. People register themselves on `/register` (13-digit
citizen ID, name, password) and get a plain member account; an admin then sets their role and department. Nothing checks that the
citizen ID belongs to the person who typed it, and that is deliberate for now.

The real login will be built on the hospital's own personnel database, once this system is connected to it and the staff records can be
pulled from there. Until then, treat every account as unverified, and do not put this instance where the public can reach it. The HIS
asset lookup is in the same state: `HisAssetSyncService::getMockHisData()` is a mock that answers for any number, to be replaced by the
hospital's API (the field mapping is in `mapHisPayload()`, in one place).

### หมายเหตุการพัฒนา (Development Notes)

- **Vite Dev Server**: เริ่มที่พอร์ต **5173** (ตั้งด้วย `VITE_PORT`) และเลื่อนไปพอร์ตที่ว่างถัดไปเองถ้าพอร์ตนั้นถูกโปรเจกต์อื่นใช้อยู่ — ค่าที่ใช้จริงถูกเขียนลง `public/hot`
- หากมีการเปลี่ยนแปลงการตั้งค่าพอร์ต โปรดตรวจสอบที่ไฟล์ `vite.config.js` และ `docker-compose.yml`

## โครงสร้างทางเทคนิค (Technical Stack)

- **Backend**: Laravel 13 (PHP 8.3+, image ใช้ 8.4)
- **Frontend**: Tailwind CSS 3.4, Alpine.js, Turbo, Blade Templates, Vite 7
- **Database**: MySQL / MariaDB
- **Real-time**: Laravel Broadcasting ผ่าน Pusher (private channels) — ถ้าไม่มีการเชื่อมต่อ หน้าแชทจะ poll ทุก 5 วินาที (เมื่อเชื่อมต่อปกติจะเหลือเป็น safety net ทุก 15 วินาที) และแชทลอย (widget) ตรวจทุก 30 วินาที
- **Queue / Cache**: Redis (ไม่บังคับ)

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
| `scheduler` | `schedule:work` — **required**: it runs `chat:purge-deleted` (03:10 Thai time) and `sanctum:prune-expired`; without it nothing scheduled ever runs |
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
| `CHAT_*` (see `config/chat.php`) | Flood limits, threads per day (5), messages loaded per page (30), days before deleted chat is erased (30, `0` = never) | the defaults in that file |
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

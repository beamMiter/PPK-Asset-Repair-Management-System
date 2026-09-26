<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\Maintenance\MaintenanceRequestController;
use App\Http\Controllers\Maintenance\MaintenanceTransitionController;
use App\Http\Controllers\Repair\MaintenanceJobController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Maintenance\MaintenanceLogController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\MaintenanceRatingApiController;
use App\Http\Controllers\Api\MaintenanceRequestApiController;

/*
|--------------------------------------------------------------------------
| API routes — ทุก URL ขึ้นต้นด้วย /api
|--------------------------------------------------------------------------
| ยืนยันตัวตน
|   POST /api/auth/login (citizen_id + password) ได้ Bearer token (Sanctum) แล้วแนบ
|   `Authorization: Bearer <token>` ทุกครั้งที่เรียก route ในกลุ่ม auth:sanctum ด้านล่าง
|   กลุ่มนั้นมี middleware `active` ด้วย: บัญชีที่ถูกระงับได้ 403 account_suspended และ token ถูกลบทิ้ง
|   token ถูกออกพร้อม abilities (AuthController::abilitiesFor) แต่ไม่มี route ไหนบังคับ `ability:`
|   สิทธิ์จริงตัดสินที่ Policy/Gate ใน controller (MaintenanceRequestPolicy, AssetPolicy ฯลฯ)
|
| รูปแบบข้อมูล
|   - ต้องส่ง `Accept: application/json` ทุกครั้ง: หลาย endpoint ใช้ controller ตัวเดียวกับหน้าเว็บ
|     (assets, repair-requests) และตัดสินใจจาก header นี้ว่าจะตอบ JSON หรือ HTML/redirect
|     ตรวจแล้ว: GET /api/repair-requests ที่ไม่ส่ง Accept ได้ 200 text/html
|   - error: {"message": "...", "code": "..."} (code มีเฉพาะบาง endpoint) · validate ไม่ผ่าน = 422 พร้อม errors
|   - รายการแบ่งหน้าส่วนใหญ่ตอบ {"data": [...], "meta": {current_page, per_page, total, last_page}}
|     ยกเว้น .../{req}/logs (paginator ของ Laravel ตรงๆ) และ /repair-requests/my-jobs ({"data": paginator})
|   - หลาย endpoint แนบ key `toast` มาด้วย เป็นข้อความสำหรับ UI ของเว็บ client อื่นไม่ต้องใช้
|
| หน้าเว็บของโปรเจกต์นี้ไม่ได้เรียก /api/* (ใช้ routes/web.php) — ก่อนแก้หรือลบ route ที่นี่
| ให้เช็คก่อนว่ามี client ภายนอกใช้อยู่หรือไม่ ส่วนสเปกอยู่ที่ openapi.yaml (ที่ root, ยังไม่ครบทุก route)
*/

// Public: GET /api/health — ไม่ต้อง login; ตรวจ DB (select 1) และ Redis (ping ถ้ามี ext-redis)
//   200 {"status":"ok", checks, meta} · 503 {"status":"degraded", ...} เมื่อตัวใดตัวหนึ่งผิดปกติ
Route::get('/health', [HealthController::class, 'index'])->name('health');
// Optional: could add throttle here later if abused

// Public auth endpoints
Route::prefix('auth')->name('auth.')->group(function () {
    // POST /api/auth/login   body: citizen_id (13 หลัก), password, device_name? (ชื่อที่จะตั้งให้ token)
    //   201 {token, token_type:"Bearer", user{id,name,citizen_id,email,role,abilities}}
    //   401 invalid_credentials · 403 account_suspended · 429 too_many_attempts (มี Retry-After) หรือ RATE_LIMITED
    //   กันเดา 3 ชั้น (เกณฑ์เดียวกับหน้าเว็บ — App\Services\LoginAttempt): route นี้ 10 ครั้ง/นาที ·
    //   ผิด 5 ครั้งต่อ (citizen_id + IP) ล็อก 60 วินาที · ผิด 30 ครั้งต่อ IP (ทุกบัญชีรวมกัน) ใน 5 นาทีก็ล็อก (กันลองรหัสเดียวกับหลายบัญชี)
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login');

    // Password reset (API) — ผู้ใช้ที่ไม่มีอีเมลในระบบใช้ไม่ได้
    // POST /api/auth/password/email   body: email → ส่งลิงก์รีเซ็ตทางอีเมล (5 ครั้ง/นาที)
    //   200 เสมอ ไม่ว่าอีเมลนั้นจะมีบัญชีหรือไม่ (ตอบต่างกันจะบอกว่าใครมีบัญชี) · ลิงก์ส่งหลังตอบกลับ
    Route::post('/password/email', [PasswordResetController::class, 'sendResetLinkEmail'])
        ->middleware('throttle:5,1')
        ->name('password.email');

    // POST /api/auth/password/reset   body: token, email, password + password_confirmation (≥ 8 ตัว มีตัวอักษรและตัวเลข)
    //   200 · 400 password_reset_failed (token ผิด หมดอายุ หรือไม่มีบัญชี — ข้อความเดียวกัน) · สำเร็จแล้ว token API,
    //   session ที่เปิดอยู่ และ remember-me ของบัญชีนั้นถูกยกเลิกทั้งหมด
    Route::post('/password/reset', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:5,1')
        ->name('password.reset');
});

Route::middleware(['auth:sanctum', 'active', 'password.changed'])->group(function () {
    // GET /api/user — User model ทั้งก้อน (ตาม $hidden) ส่วน /api/auth/me ตอบเฉพาะฟิลด์ที่เลือกไว้ + abilities
    Route::get('/user', fn (Request $r) => $r->user());

    // Assets API — AssetController ตัวเดียวกับหน้าเว็บ; ชื่อ route คือ api.assets.*
    //   GET    /api/assets            ?q= &status= &type= &category_id= &department_id= &location= &sort_by= &sort_dir=
    //                                 &per_page= (1–100, ปริยาย 20) → {data, meta, sort, toast}
    //   POST   /api/assets            สร้างครุภัณฑ์ (ฟิลด์ตาม AssetInput::rules()) → 201
    //   GET    /api/assets/{asset}    ดูรายการเดียว
    //   PUT|PATCH /api/assets/{asset} แก้ไข
    //   DELETE /api/assets/{asset}    soft delete
    //   สิทธิ์ (AssetPolicy): ดู = ทุกคนที่ login · สร้าง/แก้ = admin, supervisor, ทีมช่าง · ลบ = admin, supervisor
    Route::name('api.')->group(function () {
        Route::apiResource('assets', AssetController::class);
    });

    // Repair requests (ใบงานซ่อม) - REST-ish API
    // ที่เมธอด index/store/destroy/transition ใช้ controller เดียวกับหน้าเว็บ → ต้องส่ง Accept: application/json
    Route::prefix('repair-requests')->name('repair-requests.')->group(function () {
        // GET /api/repair-requests   ?status= &q= &asset_id= &sort_by=(request_no|id|request_date) &sort_dir=asc|desc &page=
        //   20 รายการ/หน้า → {data, meta, toast} · member เห็นเฉพาะใบงานที่ตัวเองแจ้ง, admin/supervisor/ทีมช่างเห็นทั้งหมด
        Route::get('/',                   [MaintenanceRequestController::class, 'index'])->name('index');

        // POST /api/repair-requests  แจ้งซ่อมใหม่ (multipart/form-data เมื่อแนบไฟล์) — ทุกบทบาทที่ login แจ้งได้
        //   title*, description, asset_id, department_id, type_id, location_text, reporter_name, reporter_phone,
        //   reporter_email, files[] (สูงสุด 3 ไฟล์), captions[] → 201 {data, toast} · 422 {message, errors}
        Route::post('/',                  [MaintenanceRequestController::class, 'store'])->name('store');

        // GET /api/repair-requests/my-jobs — must stay above `/{req}`, which would take "my-jobs" for a request id
        //   งานของฉัน = ที่ตนเป็นหัวหน้าทีมหรือถูกมอบหมาย (ไม่นับที่ถูกถอนออกจากงาน) · ?search= (เลขที่/หัวข้อ) &status=
        //   (ไม่ส่ง = ทุกสถานะ, status=all = ทุกสถานะยกเว้น closed) → {data: paginator 20/หน้า}
        //   เฉพาะ admin, supervisor, ทีมช่าง (member ได้ 403)
        Route::get('/my-jobs',            [MaintenanceJobController::class, 'myJobs'])->name('my-jobs');

        // GET /api/repair-requests/{req} → {data} รายละเอียดครบเท่าที่หน้ารายละเอียดแสดง · Gate 'view':
        //   admin/supervisor, ผู้แจ้ง, ผู้ที่ถูกมอบหมาย และทีมช่างเมื่องานยังรอรับทราบ/รอผู้รับเรื่อง
        Route::get('/{req}',              [MaintenanceRequestController::class, 'show'])->name('show');

        // PUT /api/repair-requests/{req} — แก้ไขใบงาน · Gate 'update': admin/supervisor เสมอ · ผู้ที่ถูกมอบหมายเมื่อ
        //   accepted/in_progress/on_hold · ผู้แจ้งเมื่อยัง pending/acknowledged และยังไม่มีผู้รับผิดชอบ
        //   ใบงานที่จบแล้ว (resolved/closed/cancelled/rejected) แก้ไม่ได้ ยกเว้น admin/supervisor
        //   ฟิลด์แยกตามบทบาท: ทีมงานเท่านั้นที่ส่ง technician_id / user_ids (ทีม) / status
        //   status = การเปลี่ยนสถานะจริง (เหมือน POST …/transition): ต้องมีสิทธิ์ของขั้นนั้นเท่ากับปุ่ม (เช่น closed = ผู้แจ้ง/admin,
        //   ผู้แจ้งยกเลิกใบ pending ไม่ได้ → 403) แล้วผ่านตาราง ALLOWED_TRANSITIONS (ผิดกติกา = 409 และไม่บันทึกอะไรเลย)
        //   note? = เหตุผลของการเปลี่ยนสถานะ (on_hold ต้องมี)
        Route::put('/{req}',              [MaintenanceRequestController::class, 'update'])->name('update');

        // DELETE /api/repair-requests/{req} — soft delete + บันทึก log delete_request → 200 {deleted:true, toast}
        //   เฉพาะ admin, supervisor (MaintenanceRequestPolicy ไม่มี delete ผู้อื่นจึงถูกปฏิเสธ)
        Route::delete('/{req}',           [MaintenanceRequestController::class, 'destroy'])->name('destroy');

        // POST /api/repair-requests/{req}/transition   body: status*, note?, technician_id?
        //   เปลี่ยนสถานะตาม MaintenanceRequest::ALLOWED_TRANSITIONS · Gate 'transition': admin/supervisor หรือผู้ที่ถูกมอบหมาย
        //   เท่านั้น (ผู้อื่น 403) และต้องมีสิทธิ์ของขั้นนั้นเท่ากับปุ่ม (เช่น closed = ผู้แจ้ง/admin; ช่างในทีมปิดงานแทนผู้แจ้งไม่ได้ → 403)
        //   · ข้อมูลไม่ถูกต้อง = 422 · ย้ายสถานะผิดกติกา/ขาดเหตุผลที่ต้องระบุ = 409
        Route::post('/{req}/transition',  [MaintenanceTransitionController::class, 'transition'])->name('transition');

        // GET /api/repair-requests/{req}/logs — ประวัติการเปลี่ยนแปลง ใหม่→เก่า 20 รายการ/หน้า (paginator ตรงๆ) · Gate 'view'
        Route::get('/{req}/logs',         [MaintenanceLogController::class, 'index'])->name('logs');

        // GET /api/repair-requests/pending/evaluations
        //   ใบงานที่ฉันแจ้ง เสร็จ/ปิดแล้ว ยังไม่ได้ให้คะแนน และยังอยู่ในกรอบ 30 วัน → {data:[...]}
        Route::get(
            '/pending/evaluations',
            [MaintenanceRatingApiController::class, 'pendingEvaluations']
        )->name('pending-evaluations');

        // POST /api/repair-requests/{maintenanceRequest}/rating   body: score* (1–5), comment (บังคับเมื่อ 1–2 ดาว, ≤ 1,000 ตัวอักษร)
        //   ให้ได้เฉพาะผู้แจ้ง (403) · งานต้อง resolved/closed (422) · ให้ซ้ำไม่ได้ (409) · ต้องอยู่ในกรอบ 30 วัน (422)
        //   · ต้องมีหัวหน้าทีมให้ผูกคะแนน (422) → 201; ถ้างานยัง resolved อยู่จะถูกปิดงานอัตโนมัติ
        Route::post(
            '/{maintenanceRequest}/rating',
            [MaintenanceRatingApiController::class, 'store']
        )->name('rating.store');
    });

    // Attachments — เลิกใช้แล้ว ทั้งสองเส้นตอบ 410 เสมอ (เหลือไว้ให้ client เก่าไม่เจอ 404)
    //   แนบไฟล์ตอนแจ้งซ่อม: POST /api/repair-requests (files[]) · เพิ่ม/ลบไฟล์ในใบงานเดิม: ใช้ route ฝั่งเว็บ
    //   POST|DELETE /maintenance/requests/{req}/attachments[/{attachment}] (routes/web.php)
    Route::post('/attachments',                [AttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

    // Chat (กระทู้) — ทุกคนที่ login เห็นทุกกระทู้ ไม่มีสิทธิ์รายกระทู้
    // Threads
    //   GET  /api/threads   ?q= (ค้นจากหัวข้อ) ?scope=mine (เฉพาะกระทู้ที่ตั้งเองหรือเคยตอบ) 15 กระทู้/หน้า → {data, meta}; แต่ละรายการมี latest_message และ unread_count ของผู้เรียก
    //   POST /api/threads   body: title* (≤ 180) → 201
    //   GET  /api/threads/{thread}   กระทู้ + latest_messages (10 ข้อความล่าสุด เรียงเก่า→ใหม่)
    Route::get('/threads',          [ChatController::class, 'index'])->name('threads.index');
    Route::post('/threads',         [ChatController::class, 'store'])->name('threads.store');
    Route::get('/threads/{thread}', [ChatController::class, 'show'])->name('threads.show');

    // Thread messages
    //   GET  .../messages   ?after_id= (เอาเฉพาะข้อความที่ id ใหม่กว่า ใช้ poll) &limit= (1–100, ปริยาย 50)
    //                       ถ้าไม่ส่ง after_id จะได้ข้อความเก่าสุดก่อน ไม่เกิน limit
    //   POST .../messages   body: body* (≤ 3,000) → 201 และ broadcast แบบ real-time · กระทู้ที่ล็อกอยู่ = 403
    Route::get('/threads/{thread}/messages',  [ChatController::class, 'messages'])->name('messages.index');
    Route::post('/threads/{thread}/messages', [ChatController::class, 'storeMessage'])->name('messages.store');

    // Thread lock / unlock — admin และทีม IT / ช่าง (User::workerRoles) เท่านั้น ไม่รวม supervisor และ member (เช็คใน ChatThread::canBeLockedBy)
    Route::post('/threads/{thread}/lock',   [ChatController::class, 'lock'])->name('threads.lock');
    Route::post('/threads/{thread}/unlock', [ChatController::class, 'unlock'])->name('threads.unlock');

    // ซ่อนกระทู้จาก "กระทู้ที่มีส่วนร่วม" ของตัวเอง (ไม่ลบ ไม่กระทบคนอื่น) — DELETE = แสดงอีกครั้ง; กลับมาเองเมื่อเราพิมพ์ในกระทู้นั้น
    //   POST   /api/threads/{thread}/hide   ต้องเคยมีส่วนร่วมในกระทู้นั้น ไม่งั้น 422
    //   DELETE /api/threads/{thread}/hide
    Route::post('/threads/{thread}/hide',    [ChatController::class, 'hide'])->name('threads.hide');
    Route::delete('/threads/{thread}/hide',  [ChatController::class, 'unhide'])->name('threads.unhide');

    // GET /api/chat/my-updates — กระทู้ที่ฉันตั้งหรือเคยตอบ (30 ล่าสุด) พร้อมจำนวนที่ยังไม่ได้อ่าน
    Route::get('/chat/my-updates', [ChatController::class, 'myUpdates'])->name('api.chat.my_updates');

    // Auth (protected)
    //   GET    /api/auth/tokens        token ทั้งหมดของฉัน (id, name, abilities, last_used_at, created_at)
    //   DELETE /api/auth/tokens/{id}   ยกเลิก token ที่ระบุ (ของฉันเท่านั้น) · 404 token_not_found
    //   GET    /api/auth/me            โปรไฟล์ย่อ (id, name, citizen_id, email, role) + abilities
    //   POST   /api/auth/logout        ยกเลิก token ที่ใช้เรียกอยู่
    //   POST   /api/auth/logout-all    ยกเลิก token ทั้งหมดของฉัน (ทุกอุปกรณ์)
    Route::get('/auth/tokens',         [AuthController::class, 'tokens'])->name('auth.tokens');
    Route::delete('/auth/tokens/{id}', [AuthController::class, 'revokeToken'])->name('auth.tokens.revoke');
    Route::get('/auth/me',             [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/logout',        [AuthController::class, 'logout'])->name('auth.logout');
    Route::post('/auth/logout-all',    [AuthController::class, 'logoutAll'])->name('auth.logout-all');

    // Meta — ข้อมูลอ้างอิงสำหรับ dropdown ทุกเส้นตอบ {data:[...]}
    Route::prefix('meta')->name('meta.')->group(function () {
        // ?q= (รหัส/ชื่อไทย/ชื่ออังกฤษ) สูงสุด 50 → {id, code, name, display}
        Route::get('/departments', [MetaController::class, 'departments'])->name('departments');
        // ?q= (ชื่อ/slug) สูงสุด 50 → {id, name, slug, color, description}
        Route::get('/categories',  [MetaController::class, 'categories'])->name('categories');
        // ?role=admin|supervisor|it_support|network|programmer|technician|member (ไม่ส่ง = ทุกบทบาท)
        //   สูงสุด 200 คน ไม่รวมบัญชีที่ถูกระงับ → {id, name, role, department}
        //   ฝ่ายจัดการ (can:maintenance-type-manage) เห็นทุกบทบาท · สมาชิกทั่วไปเห็นเฉพาะเจ้าหน้าที่ (ไม่เห็นสมาชิกคนอื่น)
        Route::get('/users',       [MetaController::class, 'users'])->name('users');
    });

    // Search — สำหรับ autocomplete ตอบ {data:[...]} · ?limit= 1–50 (ปริยาย 10)
    Route::prefix('search')->name('search.')->group(function () {
        // ?q= (รหัส/ชื่อ) &department_id= → {id, code, name, label}  (หมายเหตุ: รวมครุภัณฑ์ที่ถูก soft delete ด้วย)
        Route::get('/assets', [SearchController::class, 'assets'])->name('assets');
        // ?q= (เลขที่/หัวข้อ) &status= → {id, request_no, title, status} · เห็นตามสิทธิ์เดียวกับหน้ารายการ (member เห็นเฉพาะของตนเอง)
        Route::get('/maintenance-requests', [SearchController::class, 'requests'])->name('requests');
    });

    // Stats — ตัวเลขรวมทั้งระบบ ไม่แยกตามผู้ใช้/บทบาท และแคชไว้ 60 วินาที
    //   นับด้วย DB::table ตรงๆ จึงรวมแถวที่ถูก soft delete ด้วย
    Route::prefix('stats')->name('stats.')->group(function () {
        // {assets_total, requests_open, requests_closed, recent_daily: 7 วันล่าสุด [{date, count}]}
        Route::get('/summary',                     [StatsController::class, 'summary'])->name('summary');
        // {data: {<status>: จำนวน}}
        Route::get('/maintenance/status-counts',   [StatsController::class, 'maintenanceStatusCounts'])->name('maintenance.status-counts');
        // ผลงานต่อผู้รับผิดชอบหลัก (สูงสุด 50) → {id, name, total, open, closed, avg_hours}
        //   เฉพาะฝ่ายจัดการ (can:maintenance-type-manage) เหมือนกระดานคะแนนเจ้าหน้าที่ — เป็นผลงานรายบุคคล ไม่ใช่ตัวเลขรวม
        Route::get('/maintenance/technicians',     [StatsController::class, 'technicianSummary'])->name('maintenance.technicians')
            ->middleware('can:maintenance-type-manage');
        // {id, name, count} เรียงจากมากไปน้อย
        Route::get('/assets/by-department',        [StatsController::class, 'assetsByDepartment'])->name('assets.by-department');
    });
});

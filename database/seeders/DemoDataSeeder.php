<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRequestType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo assets and repair requests: ~35 assets and ~70 requests in deliberately different situations, all relative to
 * "now" so the dashboards always look alive, and all deterministic (no faker) so a re-seed gives the same screens.
 *
 * Every request is built the way the application itself would have produced it:
 *  - the timeline only contains the stages the status has reached, in order (request → acknowledged → accepted → started
 *    → [on hold → resumed] → resolved → closed), and `technician_id` is the lead of the team from "accepted" on;
 *  - the team is a set of assignments (first = lead) whose status and response follow the request's;
 *  - one log row per transition ("[from -> to] note", with the same labels the app writes) plus the creation entry;
 *  - SLA due dates come from the request type (plus the time spent on hold);
 *  - a rating exists only on a closed request, from its reporter, for its lead;
 *  - an asset is `in_repair` exactly while it has an open request; a disposed asset only has history.
 * DatabaseSeederIntegrityTest checks all of that, so a change here that breaks a relationship fails a test.
 */
class DemoDataSeeder extends Seeder
{
    private Carbon $now;

    /** @var array<string, User> ROSTER key => user */
    private array $users = [];

    /** @var array<string, int> department code => id */
    private array $departments = [];

    /** @var array<string, MaintenanceRequestType> */
    private array $types = [];

    /** @var array<string, Asset> asset code => asset */
    private array $assets = [];

    /** @var array<int, int> Thai two-digit year => last request number used */
    private array $running = [];

    private array $logs = [];
    private array $assignments = [];
    private array $operationLogs = [];
    private array $ratings = [];

    private const DEFAULT_NOTES = [
        'acknowledged' => 'รับทราบแล้ว',
        'accepted' => 'รับเรื่องแล้ว',
        'in_progress' => 'กำลังดำเนินการ',
        'on_hold' => 'หยุดการซ่อมบำรุงชั่วคราว/รออะไหล่',
        'resolved' => 'ซ่อมเสร็จแล้ว',
        'closed' => 'อนุมัติผลการซ่อมบำรุง',
        'cancelled' => 'ยกเลิก',
        'rejected' => 'ไม่รับเรื่อง',
    ];

    private const LEVELS = ['pending' => 0, 'acknowledged' => 1, 'accepted' => 2, 'in_progress' => 3, 'on_hold' => 3, 'resolved' => 4, 'closed' => 5];

    public function run(): void
    {
        $this->now = now()->startOfMinute();
        $this->users = collect(UserSeeder::ROSTER)->map(fn ($p) => User::where('citizen_id', $p['cid'])->firstOrFail())->all();
        $this->departments = Department::pluck('id', 'code')->all();
        $this->types = MaintenanceRequestType::all()->keyBy('name')->all();

        $this->seedAssets();
        $this->seedRequests();
        $this->syncAssetStatuses();
    }

    // ------------------------------------------------------------------ assets

    private function seedAssets(): void
    {
        $categories = AssetCategory::pluck('id', 'name');
        $vendors = [
            ['บริษัท ไอที โซลูชั่น จำกัด', '02-111-2201'], ['บริษัท เมดิคอล เทค จำกัด', '02-111-2202'],
            ['หจก. ซีเนียร์ เซอร์วิส', '02-111-2203'], ['บริษัท เน็ตเวิร์ค พลัส จำกัด', '02-111-2204'],
        ];

        // code, name, category, dept, brand, model, location, bought (years ago), warranty (years), price, extras
        $rows = [
            ['SRV-IT-01',     'เซิร์ฟเวอร์ระบบ HIS',                 'คอมพิวเตอร์',      'IT',    'Dell',       'PowerEdge R750',      'ห้องเซิร์ฟเวอร์ ชั้น 1',     2.5, 3, 285000, ['his' => true]],
            ['NET-SW-IT-01',  'Core Switch อาคารอำนวยการ',            'เครือข่าย',        'IT',    'Cisco',      'Catalyst 9300',       'ห้องเซิร์ฟเวอร์ ชั้น 1',     3,   5, 168000, ['his' => true]],
            ['NET-AP-OPD-01', 'Access Point ตึกผู้ป่วยนอก',           'เครือข่าย',        'OPD',   'Ubiquiti',   'U6-Pro',              'ทางเดินผู้ป่วยนอก ชั้น 1',   1.5, 3, 6900,   []],
            ['NET-AP-IPD-01', 'Access Point หอผู้ป่วยใน',             'เครือข่าย',        'IPD',   'Ubiquiti',   'U6-Pro',              'ทางเดินหอผู้ป่วยใน ชั้น 3',  1.5, 3, 6900,   []],
            ['UPS-IT-01',     'เครื่องสำรองไฟห้องเซิร์ฟเวอร์',        'ระบบไฟฟ้า',        'IT',    'APC',        'Smart-UPS SRT 6000',  'ห้องเซิร์ฟเวอร์ ชั้น 1',     4,   3, 145000, []],
            ['PC-OPD-01',     'คอมพิวเตอร์ห้องตรวจ 1',                'คอมพิวเตอร์',      'OPD',   'HP',         'ProDesk 400 G7',      'ห้องตรวจ 1 ผู้ป่วยนอก',      3,   3, 24900,  ['his' => true, 'expire' => -20]],
            ['PC-OPD-02',     'คอมพิวเตอร์ห้องตรวจ 2',                'คอมพิวเตอร์',      'OPD',   'Lenovo',     'ThinkCentre M70s',    'ห้องตรวจ 2 ผู้ป่วยนอก',      2,   3, 23500,  ['his' => true]],
            ['PC-OPD-03',     'คอมพิวเตอร์จุดคัดกรอง',                'คอมพิวเตอร์',      'OPD',   'Dell',       'OptiPlex 3080',       'จุดคัดกรองผู้ป่วยนอก',       3.5, 3, 22000,  []],
            ['PRN-OPD-01',    'เครื่องพิมพ์เลเซอร์ห้องบัตร',          'เครื่องพิมพ์',     'OPD',   'Brother',    'HL-L6400DW',          'ห้องเวชระเบียน ผู้ป่วยนอก',  2,   2, 17900,  []],
            ['AC-OPD-01',     'เครื่องปรับอากาศห้องตรวจ 3',           'เครื่องปรับอากาศ', 'OPD',   'Daikin',     'FTKM24',              'ห้องตรวจ 3 ผู้ป่วยนอก',      4,   5, 32000,  []],
            ['PC-IPD-01',     'คอมพิวเตอร์เคาน์เตอร์พยาบาล 1',        'คอมพิวเตอร์',      'IPD',   'Dell',       'OptiPlex 5090',       'เคาน์เตอร์พยาบาล ชั้น 3',    2.2, 3, 25900,  ['his' => true]],
            ['PC-IPD-02',     'คอมพิวเตอร์เคาน์เตอร์พยาบาล 2',        'คอมพิวเตอร์',      'IPD',   'HP',         'ProDesk 400 G9',      'เคาน์เตอร์พยาบาล ชั้น 4',    1,   3, 26900,  []],
            ['PRN-IPD-01',    'เครื่องพิมพ์ใบสั่งยา',                  'เครื่องพิมพ์',     'IPD',   'Epson',      'LQ-310',              'เคาน์เตอร์พยาบาล ชั้น 3',    3,   1, 8900,   []],
            ['MON-IPD-01',    'เครื่องติดตามสัญญาณชีพ ห้องผู้ป่วยหนัก', 'เครื่องมือแพทย์',  'IPD',   'Mindray',    'uMEC12',              'ห้องผู้ป่วยหนัก ชั้น 3',     3,   3, 185000, ['his' => true, 'expire' => 25]],
            ['AC-IPD-01',     'เครื่องปรับอากาศหอผู้ป่วยใน',          'เครื่องปรับอากาศ', 'IPD',   'Carrier',    '42TSA',               'หอผู้ป่วยใน ชั้น 3',         3,   5, 54000,  []],
            ['BED-IPD-01',    'เตียงผู้ป่วยไฟฟ้า',                     'เฟอร์นิเจอร์',     'IPD',   'Linet',      'Eleganza 3',          'ห้องผู้ป่วย 301',            2,   2, 98000,  ['expire' => 12]],
            ['PC-ER-01',      'คอมพิวเตอร์ห้องฉุกเฉิน',               'คอมพิวเตอร์',      'ER',    'HP',         'EliteDesk 800 G6',    'จุดคัดแยกผู้ป่วยฉุกเฉิน',    2,   3, 27900,  ['his' => true]],
            ['PRN-ER-01',     'เครื่องพิมพ์ใบเสร็จห้องฉุกเฉิน',       'เครื่องพิมพ์',     'ER',    'Epson',      'TM-T88VI',            'จุดลงทะเบียนห้องฉุกเฉิน',    1,   1, 12500,  ['expire' => 5]],
            ['MON-ER-01',     'เครื่องติดตามสัญญาณชีพ ห้องฉุกเฉิน',   'เครื่องมือแพทย์',  'ER',    'Philips',    'IntelliVue MX450',    'ห้องช่วยชีวิต',              5,   3, 210000, []],
            ['DEF-ER-01',     'เครื่องกระตุกหัวใจไฟฟ้า',              'เครื่องมือแพทย์',  'ER',    'Zoll',       'R Series',            'ห้องช่วยชีวิต',              2,   3, 420000, ['his' => true]],
            ['PC-LAB-01',     'คอมพิวเตอร์ห้องปฏิบัติการ',            'คอมพิวเตอร์',      'LAB',   'Lenovo',     'ThinkCentre M90t',    'ห้องรับสิ่งส่งตรวจ',         2,   3, 28900,  ['his' => true]],
            ['ANL-LAB-01',    'เครื่องวิเคราะห์เคมีคลินิก',           'เครื่องมือแพทย์',  'LAB',   'Roche',      'cobas c311',          'ห้อง Chemistry',             4,   5, 1250000, ['his' => true]],
            ['REF-LAB-01',    'ตู้เย็นเก็บสารเคมีและน้ำยา',           'ระบบไฟฟ้า',        'LAB',   'Haier',      'HYC-390',             'ห้อง Chemistry',             3,   3, 58000,  []],
            ['PRN-LAB-01',    'เครื่องพิมพ์ฉลากบาร์โค้ด',             'เครื่องพิมพ์',     'LAB',   'Zebra',      'ZD421',               'ห้องรับสิ่งส่งตรวจ',         1,   2, 15900,  []],
            ['PC-PH-01',      'คอมพิวเตอร์ห้องจ่ายยา',                'คอมพิวเตอร์',      'PHARM', 'Dell',       'OptiPlex 3090',       'ห้องจ่ายยาผู้ป่วยนอก',       2,   3, 25900,  ['his' => true]],
            ['PRN-PH-01',     'เครื่องพิมพ์ฉลากยา',                    'เครื่องพิมพ์',     'PHARM', 'Zebra',      'ZD621',               'ห้องจ่ายยาผู้ป่วยนอก',       1.5, 2, 18900,  []],
            ['XRAY-RAD-01',   'เครื่องเอกซเรย์ดิจิทัล',               'เครื่องมือแพทย์',  'RAD',   'Siemens',    'Ysio X.pree',         'ห้องเอกซเรย์ 1',             3,   5, 3800000, ['his' => true]],
            ['PC-ADM-01',     'คอมพิวเตอร์ห้องผู้บริหาร',             'คอมพิวเตอร์',      'ADM',   'Dell',       'OptiPlex 7090',       'ห้องผู้อำนวยการ ชั้น 2',     1,   3, 31900,  []],
            ['PRN-ADM-01',    'เครื่องพิมพ์ห้องธุรการ',               'เครื่องพิมพ์',     'ADM',   'Canon',      'imageRUNNER 2425',    'ห้องธุรการ ชั้น 2',          2,   2, 42000,  []],
            ['PC-FIN-01',     'คอมพิวเตอร์ห้องการเงิน',               'คอมพิวเตอร์',      'FIN',   'HP',         'ProDesk 400 G6',      'ห้องการเงิน ชั้น 2',         4,   3, 23900,  []],
            ['PRN-FIN-01',    'เครื่องพิมพ์ใบเสร็จการเงิน',           'เครื่องพิมพ์',     'FIN',   'Epson',      'TM-U220',             'ห้องการเงิน ชั้น 2',         2,   2, 9500,   []],
            ['CAR-FAC-01',    'รถพยาบาลฉุกเฉิน',                       'ยานพาหนะ',         'FAC',   'Toyota',     'Commuter',            'โรงจอดรถโรงพยาบาล',          5,   3, 1450000, []],
            ['GEN-FAC-01',    'เครื่องกำเนิดไฟฟ้าสำรอง',              'ระบบไฟฟ้า',        'FAC',   'Cummins',    'C550D5',              'อาคารเครื่องกำเนิดไฟฟ้า',    6,   5, 1980000, ['his' => true]],
            // disposed: only history, never an open request
            ['PC-OLD-01',     'คอมพิวเตอร์เก่า (จำหน่ายแล้ว)',        'คอมพิวเตอร์',      'OPD',   'Acer',       'Veriton X',           'คลังพัสดุ',                  9,   3, 15000,  ['disposed' => true]],
            ['PRN-OLD-01',    'เครื่องพิมพ์เก่า (จำหน่ายแล้ว)',       'เครื่องพิมพ์',     'FIN',   'HP',         'LaserJet 1320',       'คลังพัสดุ',                  11,  1, 9000,   ['disposed' => true]],
        ];

        $typeOf = ['เครื่องมือแพทย์' => 'Medical', 'เครื่องปรับอากาศ' => 'Facility', 'ระบบไฟฟ้า' => 'Facility', 'เฟอร์นิเจอร์' => 'Furniture', 'ยานพาหนะ' => 'Vehicle'];

        Asset::unguarded(function () use ($rows, $categories, $vendors, $typeOf) {
            foreach ($rows as $i => [$code, $name, $category, $dept, $brand, $model, $location, $years, $warranty, $price, $extra]) {
                $bought = $this->now->copy()->subDays((int) round($years * 365))->startOfDay();
                $expire = isset($extra['expire'])
                    ? $this->now->copy()->addDays($extra['expire'])->startOfDay()
                    : $bought->copy()->addYears($warranty);
                [$vendor, $vendorPhone] = $vendors[$i % count($vendors)];

                $this->assets[$code] = Asset::create([
                    'asset_code' => $code,
                    'his_asset_id' => ($extra['his'] ?? false) ? 'HIS-'.$code : null,
                    'his_synced_at' => ($extra['his'] ?? false) ? $this->now->copy()->subDays(2) : null,
                    'name' => $name,
                    'type' => $typeOf[$category] ?? 'IT',
                    'category_id' => $categories[$category],
                    'brand' => $brand,
                    'model' => $model,
                    'serial_number' => 'SN'.strtoupper(substr(md5($code), 0, 10)),
                    'location' => $location,
                    'internal_phone' => sprintf('02-555-%04d', 1000 + $i * 7),
                    'vendor_name' => $vendor,
                    'vendor_phone' => $vendorPhone,
                    'price' => $price,
                    'department_id' => $this->departments[$dept],
                    'purchase_date' => $bought,
                    'warranty_start' => $bought,
                    'warranty_expire' => $expire,
                    'status' => ($extra['disposed'] ?? false) ? Asset::STATUS_DISPOSED : Asset::STATUS_ACTIVE, // settled by syncAssetStatuses()
                    'created_at' => $bought->copy()->addDays(3),
                    'updated_at' => $bought->copy()->addDays(3),
                ]);
            }
        });
    }

    // ---------------------------------------------------------------- requests

    private function seedRequests(): void
    {
        $lastYear = $this->now->dayOfYear; // `age` of 1 January: lastYear + n days ago is n days into the previous year, counting back

        $plans = [];
        foreach ($this->scenarios($lastYear) as $i => $scenario) {
            $scenario['t0'] = $this->startOf($scenario['age'], $i);
            $plans[] = $scenario;
        }
        usort($plans, fn ($a, $b) => $a['t0'] <=> $b['t0']); // oldest first: ids and request numbers grow with time

        foreach ($plans as $plan) {
            $this->buildRequest($plan);
        }

        foreach (array_chunk($this->logs, 200) as $chunk) {
            DB::table('maintenance_logs')->insert($chunk);
        }
        DB::table('maintenance_assignments')->insert($this->assignments);
        DB::table('maintenance_operation_logs')->insert($this->operationLogs);
        DB::table('maintenance_ratings')->insert($this->ratings);
    }

    /** Recent requests keep their exact age (SLA states depend on it); older ones land in working hours of their day. */
    private function startOf(float $age, int $i): Carbon
    {
        if ($age < 1) {
            return $this->now->copy()->subMinutes((int) round($age * 1440));
        }

        return $this->now->copy()->subDays((int) floor($age))->startOfDay()->setTime(8 + ($i * 3) % 9, ($i * 11) % 60);
    }

    private function buildRequest(array $s): void
    {
        $status = $s['s'];
        $reporter = $this->users[$s['by']];
        $asset = isset($s['a']) ? $this->assets[$s['a']] : null;
        $type = isset($s['t']) ? $this->types[$s['t']] : null;
        $team = array_map(fn ($key) => $this->users[$key], $s['team'] ?? []);
        $lead = $team[0] ?? null;
        $t0 = $s['t0'];

        // how far the request got: cancelled / rejected requests stopped somewhere on the way
        $stage = in_array($status, ['cancelled', 'rejected'], true) ? ($s['from'] ?? 'pending') : $status;
        $level = self::LEVELS[$stage];
        if ($level >= 2 && ! $lead) {
            throw new \LogicException("Scenario \"{$s['title']}\" reaches {$stage} without a team.");
        }

        // ---- timeline
        $ackAt = $level >= 1 ? $t0->copy()->addMinutes($s['ack'] ?? 20) : null;
        $acceptedAt = $level >= 2 ? $ackAt->copy()->addMinutes($s['acc'] ?? 35) : null;
        $startedAt = $level >= 3 ? $acceptedAt->copy()->addMinutes($s['start'] ?? 25) : null;
        $holdAt = $resumeAt = null;
        $heldMinutes = 0;
        if ($level >= 3 && ($status === 'on_hold' || isset($s['held']))) {
            $holdAt = $startedAt->copy()->addMinutes($s['hold_after'] ?? 45);
            if ($status !== 'on_hold') {
                $heldMinutes = (int) $s['held'];
                $resumeAt = $holdAt->copy()->addMinutes($heldMinutes);
            }
        }
        $resolvedAt = $level >= 4 ? ($resumeAt ?? $startedAt)->copy()->addMinutes($s['work'] ?? 240) : null;
        $closedAt = $level >= 5 ? $resolvedAt->copy()->addMinutes($s['approve'] ?? 300) : null;

        // ---- the transitions the log will show
        $steps = [];
        $step = function (string $to, Carbon $at, User $by, ?string $note = null) use (&$steps) {
            $steps[] = ['from' => $steps ? end($steps)['to'] : 'pending', 'to' => $to, 'at' => $at, 'by' => $by, 'note' => $note];
        };
        if ($level >= 1) {
            $step('acknowledged', $ackAt, $this->users[$s['acker'] ?? 'sup']);
        }
        if ($level >= 2) {
            $step('accepted', $acceptedAt, $lead);
        }
        if ($level >= 3) {
            $step('in_progress', $startedAt, $lead);
        }
        if ($holdAt) {
            $step('on_hold', $holdAt, $lead, $s['hold_note'] ?? null);
            if ($resumeAt) {
                $step('in_progress', $resumeAt, $lead);
            }
        }
        if ($level >= 4) {
            $step('resolved', $resolvedAt, $lead);
        }
        if ($level >= 5) {
            $step('closed', $closedAt, $this->users[$s['approver'] ?? 'sup']);
        }
        if ($status === 'cancelled') {
            $step('cancelled', ($steps ? end($steps)['at'] : $t0)->copy()->addMinutes($s['cancel_after'] ?? 60), $this->users[$s['cancel_by'] ?? $s['by']], $s['reason'] ?? null);
        }
        if ($status === 'rejected') {
            $step('rejected', ($steps ? end($steps)['at'] : $t0)->copy()->addMinutes($s['reject_after'] ?? 45), $this->users[$s['rejecter'] ?? 'sup'], $s['reason'] ?? null);
        }

        $last = $steps ? end($steps) : null;
        $lastAt = $last['at'] ?? $t0;

        // ---- SLA targets come from the type; time spent on hold moves the resolution target
        $responseDue = $type?->default_response_minutes ? $t0->copy()->addMinutes($type->default_response_minutes) : null;
        $slaDue = $type?->default_resolution_minutes ? $t0->copy()->addMinutes($type->default_resolution_minutes + $heldMinutes) : null;

        // ---- request number: Thai year (2 digits) + "10" + a running number per year
        $yy = ($t0->year + 543) % 100;
        $this->running[$yy] = ($this->running[$yy] ?? 0) + 1;
        $requestNo = sprintf('%02d10%05d', $yy, $this->running[$yy]);

        $resolvedRow = $level >= 4;

        $request = MaintenanceRequest::withoutEvents(fn () => MaintenanceRequest::unguarded(fn () => MaintenanceRequest::create([
            'request_no' => $requestNo,
            'asset_id' => $asset?->id,
            'reporter_id' => $reporter->id,
            'reporter_name' => $reporter->name,
            'reporter_phone' => $this->phoneOf($reporter),
            'reporter_email' => $reporter->email,
            'department_id' => $asset?->department_id ?? ($this->departments[$reporter->department] ?? null),
            'type_id' => $type?->id,
            'location_text' => $s['place'] ?? $asset?->location,
            'title' => $s['title'],
            'description' => $s['desc'],
            'status' => $status,
            'status_updated_at' => $lastAt,
            'status_updated_by' => $last['by']->id ?? null,
            'technician_id' => $level >= 2 ? $lead->id : null,
            'request_date' => $t0,
            'assigned_date' => $acceptedAt,
            'acknowledged_at' => $ackAt,
            'accepted_at' => $acceptedAt,
            'started_at' => $startedAt,
            'on_hold_at' => $status === 'on_hold' ? $holdAt : null,
            'resolved_at' => $resolvedAt,
            'closed_at' => $closedAt,
            'completed_date' => $closedAt, // set when a request is closed
            'response_due_date' => $responseDue,
            'sla_due_date' => $slaDue,
            'paused_duration_minutes' => $heldMinutes,
            'remark' => in_array($status, ['cancelled', 'rejected'], true) ? ($s['reason'] ?? null) : null,
            'resolution_note' => $resolvedRow ? ($s['note'] ?? 'ตรวจสอบและแก้ไขปัญหาเรียบร้อย ทดสอบใช้งานปกติ') : null,
            'cost' => $resolvedRow ? ($s['cost'] ?? null) : null,
            'source' => 'web',
            'created_at' => $t0,
            'updated_at' => $lastAt,
        ])));

        $this->guardAgainstFuture($s['title'], [$ackAt, $acceptedAt, $startedAt, $holdAt, $resumeAt, $resolvedAt, $closedAt, $lastAt]);

        // ---- log
        $labels = MaintenanceRequest::statusLabels();
        $this->logs[] = $this->logRow($request->id, $reporter->id, 'create_request', 'สร้างใบแจ้งซ่อม', null, 'pending', $t0);
        foreach ($steps as $st) {
            $note = trim("[{$labels[$st['from']]} -> {$labels[$st['to']]}] ".($st['note'] ?? self::DEFAULT_NOTES[$st['to']]));
            if ($st['to'] === 'accepted') {
                $note .= ' • เจ้าหน้าที่: '.$lead->name;
            }
            $this->logs[] = $this->logRow($request->id, $st['by']->id, 'transition', $note, $st['from'], $st['to'], $st['at']);

            // acknowledged with a team picked but nobody has accepted yet: the assignment is logged right after the acknowledgement
            if ($st['to'] === 'acknowledged' && ($s['assign_only'] ?? false) && $team) {
                $this->logs[] = $this->logRow($request->id, $this->users['sup']->id, 'assign_technician',
                    'มอบหมายเจ้าหน้าที่: '.implode(', ', array_map(fn ($u) => $u->name, $team)), null, null, $ackAt->copy()->addMinutes(4));
            }
        }

        // ---- team
        $assignedAt = $ackAt ? $ackAt->copy()->addMinutes(4) : $t0;
        $assignmentStatus = match (true) {
            in_array($status, ['resolved', 'closed'], true) => 'done',
            in_array($status, ['cancelled', 'rejected'], true) => 'cancelled',
            default => 'in_progress',
        };
        foreach ($team as $index => $member) {
            $this->assignments[] = [
                'maintenance_request_id' => $request->id,
                'user_id' => $member->id,
                'role' => $member->role,
                'is_lead' => $index === 0,
                'assigned_at' => $assignedAt,
                'response_status' => $level >= 2 ? 'accepted' : 'pending',
                'responded_at' => $level >= 2 ? $acceptedAt : null,
                'remark' => null,
                'status' => $assignmentStatus,
                'created_at' => $assignedAt,
                'updated_at' => $lastAt,
            ];
        }
        foreach ($s['dropped'] ?? [] as $key => $why) { // handed over: this person declined and left the team
            $this->assignments[] = [
                'maintenance_request_id' => $request->id,
                'user_id' => $this->users[$key]->id,
                'role' => $this->users[$key]->role,
                'is_lead' => false,
                'assigned_at' => $assignedAt,
                'response_status' => 'rejected',
                'responded_at' => $ackAt->copy()->addMinutes(10),
                'remark' => $why,
                'status' => 'cancelled',
                'created_at' => $assignedAt,
                'updated_at' => $ackAt->copy()->addMinutes(10),
            ];
        }

        // ---- the operation report the lead files once work has started
        if ($level >= 3) {
            $methods = ['requisition', 'service_fee', 'other'];
            $this->operationLogs[] = [
                'maintenance_request_id' => $request->id,
                'user_id' => $lead->id,
                'operation_date' => $startedAt->toDateString(),
                'operation_method' => $methods[$request->id % 3],
                'property_code' => $asset?->asset_code,
                'require_precheck' => $request->id % 2 === 0,
                'remark' => $s['op'] ?? 'ตรวจสอบอาการตามที่แจ้งและดำเนินการแก้ไข',
                'issue_software' => $type?->name === 'Software',
                'issue_hardware' => $type !== null && $type->name !== 'Software',
                'created_at' => $startedAt,
                'updated_at' => $resolvedAt ?? $lastAt,
            ];
        }

        // ---- the reporter's rating of a closed request, credited to its lead
        if ($status === 'closed' && isset($s['rate'])) {
            [$score, $comment] = $s['rate'];
            $ratedAt = $closedAt->copy()->addHours($s['rate_after'] ?? 18);
            $this->guardAgainstFuture($s['title'].' (rating)', [$ratedAt]);
            $this->ratings[] = [
                'maintenance_request_id' => $request->id,
                'rater_id' => $reporter->id,
                'technician_id' => $lead->id,
                'score' => $score,
                'comment' => $comment,
                'created_at' => $ratedAt,
                'updated_at' => $ratedAt,
            ];
        }
    }

    private function logRow(int $requestId, int $userId, string $action, string $note, ?string $from, ?string $to, Carbon $at): array
    {
        return [
            'request_id' => $requestId, 'user_id' => $userId, 'action' => $action, 'note' => $note,
            'from_status' => $from, 'to_status' => $to, 'created_at' => $at, 'updated_at' => $at,
        ];
    }

    private function phoneOf(User $user): string
    {
        $n = (int) substr($user->citizen_id, -3);

        return sprintf('08%d-%03d-%04d', 1 + $n % 8, 200 + ($n * 7) % 700, 1000 + ($n * 131) % 9000);
    }

    /** A scenario whose steps do not fit inside its age would put events in the future: fail loudly here. */
    private function guardAgainstFuture(string $title, array $moments): void
    {
        foreach (array_filter($moments) as $moment) {
            if ($moment->gt($this->now)) {
                throw new \LogicException("Scenario \"{$title}\": {$moment} is in the future — increase its age or shorten its steps.");
            }
        }
    }

    /** An asset is in repair exactly while it has an open request; a disposed asset stays disposed. */
    private function syncAssetStatuses(): void
    {
        foreach (Asset::all() as $asset) {
            if ($asset->status === Asset::STATUS_DISPOSED) {
                continue;
            }

            $asset->status = MaintenanceRequest::where('asset_id', $asset->id)->whereIn('status', MaintenanceRequest::OPEN_STATUSES)->exists()
                ? Asset::STATUS_IN_REPAIR
                : Asset::STATUS_ACTIVE;
            $asset->saveQuietly();
        }
    }

    // --------------------------------------------------------------- scenarios

    /**
     * s status · age days ago · by reporter · a asset · t type · team (first = lead) · title / desc
     * optional: place, from (stage a cancelled/rejected request stopped at), ack / acc / start / work / approve minutes,
     * held (minutes on hold, then resumed) · hold_note · note (resolution) · cost · rate [score, comment] · rate_after (h)
     * reason · cancel_by · rejecter · dropped [key => why] · assign_only · acker · approver · op
     *
     * @param  int  $ly  days ago of 1 January, so `$ly + n` is n days before the start of this year (= last year)
     */
    private function scenarios(int $ly): array
    {
        return [
            // ================= pending: fresh, response-SLA breached, stale, no asset, no type
            ['s' => 'pending', 'age' => 0.08, 'by' => 'opd', 'a' => 'PC-OPD-01', 't' => 'Hardware',
                'title' => 'เครื่องคอมพิวเตอร์ห้องตรวจ 1 เปิดไม่ติด', 'desc' => 'กดปุ่มเปิดเครื่องแล้วไม่มีไฟขึ้น ตรวจสายไฟแล้วเสียบแน่นดี คนไข้รอตรวจอยู่'],
            ['s' => 'pending', 'age' => 0.3, 'by' => 'ipd', 'a' => 'PRN-IPD-01', 't' => 'Hardware',
                'title' => 'เครื่องพิมพ์ใบสั่งยากระดาษติดบ่อย', 'desc' => 'พิมพ์ใบสั่งยาแล้วกระดาษยับติดที่ลูกกลิ้ง ต้องดึงออกทุก 2-3 แผ่น ทำให้จ่ายยาล่าช้า'],
            ['s' => 'pending', 'age' => 1.2, 'by' => 'lab', 'a' => 'PC-LAB-01', 't' => 'Software',
                'title' => 'โปรแกรม LIS ค้างที่หน้าล็อกอิน', 'desc' => 'เปิดโปรแกรม LIS แล้วค้างที่หน้าเข้าสู่ระบบ รอเกิน 5 นาที ต้องปิดแล้วเปิดใหม่หลายครั้ง'],
            ['s' => 'pending', 'age' => 3.5, 'by' => 'fin', 't' => 'Network', 'place' => 'ห้องการเงิน ชั้น 2',
                'title' => 'อินเทอร์เน็ตห้องการเงินหลุดๆ หายๆ', 'desc' => 'ตั้งแต่เช้าเน็ตหลุดทุกครึ่งชั่วโมง ทำให้ส่งเบิกจ่ายไม่ทัน ใช้กับเครื่องทุกเครื่องในห้อง'],
            ['s' => 'pending', 'age' => 0.5, 'by' => 'you',
                'title' => 'สอบถามการเพิ่มสิทธิ์ผู้ใช้ระบบ HIS', 'desc' => 'ขอทราบขั้นตอนการขอเพิ่มสิทธิ์ผู้ใช้ใหม่ 3 คน (ยังไม่ระบุประเภทงาน)'],

            // ================= acknowledged: waiting for the team to accept / nobody assigned yet
            ['s' => 'acknowledged', 'age' => 0.6, 'by' => 'pharm', 'a' => 'PRN-PH-01', 't' => 'Hardware', 'team' => ['it1'], 'assign_only' => true, 'ack' => 25,
                'title' => 'เครื่องพิมพ์ฉลากยาพิมพ์ตัวอักษรไม่ชัด', 'desc' => 'ฉลากยาที่พิมพ์ออกมาจางและตัวหนังสือขาดหาย ต้องพิมพ์ซ้ำหลายรอบ'],
            ['s' => 'acknowledged', 'age' => 2.0, 'by' => 'er', 'a' => 'PC-ER-01', 't' => 'Hardware', 'ack' => 200,
                'title' => 'เครื่องคอมพิวเตอร์ห้องฉุกเฉินทำงานช้ามาก', 'desc' => 'เปิดโปรแกรมทะเบียนผู้ป่วยใช้เวลานานกว่า 3 นาที ช่วงคนไข้เยอะทำให้คิวติด'],
            ['s' => 'acknowledged', 'age' => 1.0, 'by' => 'opd', 'a' => 'NET-AP-OPD-01', 't' => 'Network', 'team' => ['net'], 'assign_only' => true,
                'title' => 'WiFi ตึกผู้ป่วยนอกสัญญาณอ่อน', 'desc' => 'บริเวณห้องตรวจ 4-6 สัญญาณ WiFi อ่อน แท็บเล็ตของแพทย์หลุดบ่อย'],

            // ================= accepted: team took it, not started (one with a two-person team)
            ['s' => 'accepted', 'age' => 0.8, 'by' => 'ipd', 'a' => 'PC-IPD-01', 't' => 'Hardware', 'team' => ['it2'],
                'title' => 'เมาส์และคีย์บอร์ดเครื่องพยาบาลเวรดึกใช้ไม่ได้', 'desc' => 'ปุ่มคีย์บอร์ดหลายปุ่มกดไม่ติด เมาส์เลื่อนไม่สม่ำเสมอ'],
            ['s' => 'accepted', 'age' => 1.5, 'by' => 'ipd', 'a' => 'AC-IPD-01', 't' => 'Facilities', 'team' => ['tech1', 'tech2'],
                'title' => 'แอร์หอผู้ป่วยไม่เย็น มีน้ำหยด', 'desc' => 'แอร์ทำงานแต่ไม่เย็น มีน้ำหยดจากคอยล์เย็นลงพื้นห้องผู้ป่วย 3 ห้อง'],
            ['s' => 'accepted', 'age' => 0.7, 'by' => 'lab', 'a' => 'ANL-LAB-01', 't' => 'Facilities', 'team' => ['tech2'],
                'title' => 'เครื่องวิเคราะห์เคมีคลินิกแจ้งเตือน error E-104', 'desc' => 'ขึ้น error E-104 ระหว่างรันตัวอย่าง ต้องหยุดใช้เครื่องรอบเช้า'],

            // ================= in progress: within SLA, about to breach, breached, big team, handed over, paused and resumed
            ['s' => 'in_progress', 'age' => 0.15, 'by' => 'opd', 'a' => 'PC-OPD-02', 't' => 'Software', 'team' => ['dev'], 'ack' => 10, 'acc' => 15, 'start' => 20,
                'title' => 'ติดตั้งโปรแกรม HIS Client เวอร์ชันใหม่', 'desc' => 'ขอให้อัปเดตโปรแกรม HIS Client เป็นเวอร์ชันล่าสุดตามประกาศของกลุ่มงาน'],
            ['s' => 'in_progress', 'age' => 0.86, 'by' => 'er', 'a' => 'PRN-ER-01', 't' => 'Hardware', 'team' => ['it1'],
                'title' => 'เครื่องพิมพ์ใบเสร็จห้องฉุกเฉินพิมพ์ไม่ชัด', 'desc' => 'ใบเสร็จตัวหนังสือจางมากอ่านไม่ออก ผู้ป่วยขอใบเสร็จใหม่หลายราย'],
            ['s' => 'in_progress', 'age' => 1.5, 'by' => 'ipd', 'a' => 'NET-AP-IPD-01', 't' => 'Network', 'team' => ['net'],
                'title' => 'WiFi หอผู้ป่วยในหลุดบ่อย', 'desc' => 'ช่วงกลางคืนแท็บเล็ตพยาบาลหลุดจากเครือข่ายทุก 10-15 นาที'],
            ['s' => 'in_progress', 'age' => 3, 'by' => 'fin', 't' => 'Software', 'team' => ['dev'], 'place' => 'ห้องการเงิน ชั้น 2',
                'title' => 'รายงานการเงินใน HIS ออกไม่ครบ', 'desc' => 'รายงานสรุปรายรับประจำวันขาดรายการของแผนกผู้ป่วยนอก ตัวเลขไม่ตรงกับใบเสร็จ'],
            ['s' => 'in_progress', 'age' => 0.9, 'by' => 'lab', 'a' => 'PC-LAB-01', 't' => 'Hardware', 'team' => ['it1', 'it2', 'net'],
                'title' => 'ย้ายและติดตั้งเครื่องคอมพิวเตอร์ห้องปฏิบัติการ 5 เครื่อง', 'desc' => 'ห้องปฏิบัติการย้ายไปอาคารใหม่ ต้องย้ายเครื่อง 5 เครื่อง เดินสาย LAN และตั้งค่าเครือข่ายใหม่'],
            ['s' => 'in_progress', 'age' => 0.7, 'by' => 'pharm', 't' => 'Network', 'team' => ['net'], 'place' => 'ห้องเภสัชกรรม',
                'dropped' => ['it2' => 'ติดงานติดตั้งคอมพิวเตอร์ห้องปฏิบัติการ ส่งต่อให้ทีมเน็ตเวิร์ก'],
                'title' => 'สาย LAN ห้องเภสัชกรรมชำรุด', 'desc' => 'สาย LAN จุดจ่ายยาถูกหนีบจนขาด เครื่องจ่ายยา 2 เครื่องเชื่อมต่อระบบไม่ได้'],
            ['s' => 'in_progress', 'age' => 0.25, 'by' => 'fin', 't' => 'Facilities', 'team' => ['tech1'], 'place' => 'ห้องประชุม ชั้น 3',
                'title' => 'หลอดไฟห้องประชุมชั้น 3 ขาดหลายดวง', 'desc' => 'หลอดไฟขาด 6 ดวง ห้องมืด ประชุมวันพรุ่งนี้ช่วงเช้า'],
            ['s' => 'in_progress', 'age' => 2.5, 'by' => 'sup', 'a' => 'UPS-IT-01', 't' => 'Hardware', 'team' => ['it1'], 'held' => 300, 'hold_note' => 'รอแบตเตอรี่ชุดใหม่จากผู้แทนจำหน่าย',
                'title' => 'เปลี่ยนแบตเตอรี่ UPS ห้องเซิร์ฟเวอร์', 'desc' => 'UPS แจ้งเตือนแบตเตอรี่เสื่อม สำรองไฟได้เหลือไม่ถึง 5 นาที'],
            ['s' => 'in_progress', 'age' => 1.6, 'by' => 'opd', 't' => 'Network', 'team' => ['net'], 'held' => 90, 'hold_note' => 'รอปิดบริการช่วงพักเที่ยงเพื่อเปลี่ยนอุปกรณ์', 'place' => 'ตึกผู้ป่วยนอก ชั้น 2',
                'title' => 'เปลี่ยนสวิตช์ชั้น 2 ตึกผู้ป่วยนอก', 'desc' => 'สวิตช์ชั้น 2 พอร์ตเสียหลายพอร์ต เครื่องในห้องตรวจ 4 เครื่องออกเน็ตไม่ได้'],

            // ================= on hold
            ['s' => 'on_hold', 'age' => 3, 'by' => 'er', 'a' => 'XRAY-RAD-01', 't' => 'Facilities', 'team' => ['tech2'], 'hold_after' => 120,
                'hold_note' => 'รออะไหล่จากผู้ผลิต (ประมาณ 7 วัน)',
                'title' => 'เครื่องเอกซเรย์จอแสดงผลเสีย', 'desc' => 'จอ display ของเครื่องเอกซเรย์ดิจิทัลเป็นเส้นสีเขียว มองภาพไม่ชัด'],
            ['s' => 'on_hold', 'age' => 1.2, 'by' => 'ipd', 'a' => 'MON-IPD-01', 't' => 'Facilities', 'team' => ['tech2'], 'hold_note' => 'รอเครื่องสำรองจากบริษัทมาเปลี่ยน',
                'title' => 'จอมอนิเตอร์ผู้ป่วยหนักภาพกระพริบ', 'desc' => 'หน้าจอเครื่องติดตามสัญญาณชีพกระพริบเป็นช่วงๆ ค่าที่แสดงอ่านยาก'],
            ['s' => 'on_hold', 'age' => 2, 'by' => 'ipd', 't' => 'Software', 'team' => ['dev'], 'place' => 'หอผู้ป่วยใน ชั้น 2', 'hold_note' => 'รออนุมัติจากหัวหน้าฝ่ายก่อนติดตั้งโปรแกรม',
                'title' => 'ขอติดตั้งโปรแกรมบันทึกการพยาบาลเพิ่ม', 'desc' => 'ต้องการติดตั้งโปรแกรมบันทึกการพยาบาลบนเครื่องใหม่ 2 เครื่อง'],

            // ================= resolved: waiting for the reporter's side to approve
            ['s' => 'resolved', 'age' => 1.0, 'by' => 'opd', 'a' => 'PC-OPD-03', 't' => 'Hardware', 'team' => ['it2'], 'cost' => 350,
                'title' => 'เครื่องสแกนบัตรประชาชนใช้งานไม่ได้', 'desc' => 'เสียบบัตรแล้วโปรแกรมไม่อ่านข้อมูล ไฟที่เครื่องสแกนไม่ติด',
                'note' => 'เปลี่ยนสาย USB และติดตั้งไดรเวอร์ใหม่ ทดสอบอ่านบัตรได้ปกติ'],
            ['s' => 'resolved', 'age' => 3, 'by' => 'ipd', 'a' => 'BED-IPD-01', 't' => 'Facilities', 'team' => ['tech1'], 'cost' => 1200,
                'title' => 'เตียงผู้ป่วยไฟฟ้าปรับระดับไม่ได้', 'desc' => 'กดปุ่มปรับความสูงแล้วมอเตอร์ไม่ทำงาน ปรับได้เฉพาะพนักพิง',
                'note' => 'เปลี่ยนสวิตช์ควบคุมและตรวจสอบสายมอเตอร์ ใช้งานได้ตามปกติ'],
            ['s' => 'resolved', 'age' => 2, 'by' => 'you', 't' => 'Software', 'team' => ['dev'],
                'title' => 'ปรับปรุงสิทธิ์เข้าถึงรายงานในระบบ', 'desc' => 'ขอให้ผู้ดูแลระบบเข้าดูรายงานสรุปของทุกแผนกได้',
                'note' => 'ปรับกลุ่มสิทธิ์รายงานเรียบร้อย ตรวจสอบการเข้าถึงแล้ว'],
            ['s' => 'resolved', 'age' => 5, 'by' => 'lab', 't' => 'Network', 'team' => ['net'], 'place' => 'ห้อง LAB',
                'title' => 'จุด LAN ห้อง LAB ใช้งานไม่ได้', 'desc' => 'จุด LAN ผนังฝั่งซ้ายเสียบแล้วไม่ขึ้นสัญญาณ',
                'note' => 'เปลี่ยนหัว RJ45 และทดสอบสัญญาณผ่านแล้ว'],

            // ================= closed, this year: rated 5 … 1, SLA met and breached
            ['s' => 'closed', 'age' => 4, 'by' => 'opd', 'a' => 'PC-OPD-01', 't' => 'Hardware', 'team' => ['it1'], 'cost' => 1800, 'rate' => [5, 'ซ่อมเร็ว อธิบายชัดเจน ขอบคุณครับ'],
                'title' => 'เปลี่ยน RAM เครื่องตรวจโรค', 'desc' => 'เครื่องช้าและค้างบ่อย ตรวจแล้วพบ RAM เสีย', 'note' => 'เปลี่ยน RAM 8GB ทดสอบแล้วเครื่องทำงานปกติ'],
            ['s' => 'closed', 'age' => 6, 'by' => 'ipd', 'a' => 'PRN-IPD-01', 't' => 'Hardware', 'team' => ['it2'], 'cost' => 2450, 'rate' => [4, 'งานเรียบร้อย'],
                'title' => 'เปลี่ยนชุดดรัมเครื่องพิมพ์', 'desc' => 'พิมพ์ออกมามีรอยดำเป็นเส้นตลอดหน้า', 'note' => 'เปลี่ยนชุดดรัมและทำความสะอาดเครื่อง'],
            ['s' => 'closed', 'age' => 7, 'by' => 'er', 'a' => 'PC-ER-01', 't' => 'Software', 'team' => ['dev'], 'rate' => [5, null],
                'title' => 'ติดตั้ง Windows ใหม่และโปรแกรม HIS', 'desc' => 'เครื่องติดไวรัส ระบบรวน ขอติดตั้งใหม่ทั้งเครื่อง', 'note' => 'ติดตั้ง Windows และโปรแกรมที่จำเป็น ย้ายข้อมูลกลับครบ'],
            ['s' => 'closed', 'age' => 9, 'by' => 'lab', 'a' => 'ANL-LAB-01', 't' => 'Facilities', 'team' => ['tech2'], 'cost' => 8500, 'rate' => [4, 'บริการดี แต่ต้องหยุดเครื่องนานพอสมควร'], 'work' => 600,
                'title' => 'ตรวจสภาพและสอบเทียบเครื่องวิเคราะห์ประจำปี', 'desc' => 'ครบกำหนดตรวจสภาพและสอบเทียบเครื่องประจำปี', 'note' => 'ตรวจสภาพ เปลี่ยนหลอดไฟและสอบเทียบเรียบร้อย'],
            ['s' => 'closed', 'age' => 10, 'by' => 'pharm', 'a' => 'PC-PH-01', 't' => 'Hardware', 'team' => ['it1'], 'cost' => 2200, 'rate' => [5, 'เร็วมาก เครื่องเร็วขึ้นเยอะ'],
                'title' => 'เปลี่ยนฮาร์ดดิสก์เป็น SSD', 'desc' => 'เครื่องเปิดโปรแกรมจ่ายยาช้ามาก ขอปรับปรุงให้เร็วขึ้น', 'note' => 'เปลี่ยนเป็น SSD 256GB และโคลนระบบเดิม'],
            ['s' => 'closed', 'age' => 12, 'by' => 'fin', 'a' => 'PC-FIN-01', 't' => 'Hardware', 'team' => ['it2'], 'cost' => 650, 'rate' => [3, 'แก้ได้ แต่รอนานไปนิด'], 'work' => 1500,
                'title' => 'เครื่องดับเองบ่อย', 'desc' => 'ทำงานได้ 20-30 นาทีเครื่องก็ดับเอง', 'note' => 'พบพัดลมระบายความร้อนเสีย เปลี่ยนพัดลมและทาซิลิโคนใหม่'],
            ['s' => 'closed', 'age' => 14, 'by' => 'ipd', 'a' => 'AC-IPD-01', 't' => 'Facilities', 'team' => ['tech1'], 'cost' => 1500, 'rate' => [5, 'ห้องเย็นขึ้นมาก ขอบคุณครับ'],
                'title' => 'ล้างแอร์และเติมน้ำยา', 'desc' => 'แอร์เย็นไม่พอ ครบรอบล้างแอร์ประจำปี', 'note' => 'ล้างคอยล์ เติมน้ำยา ตรวจสอบระบบระบายน้ำ'],
            ['s' => 'closed', 'age' => 15, 'by' => 'opd', 'a' => 'NET-AP-OPD-01', 't' => 'Network', 'team' => ['net'], 'rate' => [4, null],
                'title' => 'ย้ายตำแหน่ง Access Point', 'desc' => 'ขอย้าย Access Point ไปติดกลางทางเดินเพื่อให้สัญญาณครอบคลุม', 'note' => 'ย้ายตำแหน่งและปรับกำลังส่งใหม่ ทดสอบสัญญาณครบทุกห้อง'],
            ['s' => 'closed', 'age' => 18, 'by' => 'er', 'a' => 'MON-ER-01', 't' => 'Facilities', 'team' => ['tech2'], 'cost' => 3200, 'work' => 4300,
                'rate' => [2, 'ใช้เวลาซ่อมหลายวัน กระทบการทำงานห้องช่วยชีวิต'],
                'title' => 'สายวัดสัญญาณชีพมอนิเตอร์เสีย', 'desc' => 'สายวัด SpO2 อ่านค่าไม่ได้ เครื่องมอนิเตอร์ใช้งานได้ไม่ครบ', 'note' => 'สั่งสายวัดใหม่จากผู้แทนจำหน่ายและเปลี่ยนให้'],
            ['s' => 'closed', 'age' => 20, 'by' => 'lab', 'a' => 'PRN-LAB-01', 't' => 'Hardware', 'team' => ['it1'], 'cost' => 900, 'rate' => [1, 'ซ่อมแล้วแต่เสียซ้ำภายในสองวัน ต้องแจ้งใหม่'],
                'title' => 'เครื่องพิมพ์ฉลากไม่ตัดกระดาษ', 'desc' => 'พิมพ์ฉลากแล้วไม่ตัดอัตโนมัติ', 'note' => 'ทำความสะอาดใบมีดและปรับเซ็นเซอร์'],
            ['s' => 'closed', 'age' => 22, 'by' => 'ipd', 'a' => 'PC-IPD-02', 't' => 'Software', 'team' => ['dev'], 'rate' => [5, null],
                'title' => 'ติดตั้ง Antivirus และตั้งค่าอัปเดตอัตโนมัติ', 'desc' => 'เครื่องยังไม่มีโปรแกรมป้องกันไวรัส', 'note' => 'ติดตั้งและตั้งค่าอัปเดตอัตโนมัติเรียบร้อย'],

            // the admin's own requests: four finished and not yet rated (the "evaluate" page), one rated
            ['s' => 'closed', 'age' => 3.5, 'by' => 'you', 'a' => 'PC-ADM-01', 't' => 'Hardware', 'team' => ['it1'], 'cost' => 2900,
                'title' => 'จอคอมพิวเตอร์ห้องผู้บริหารเป็นเส้น', 'desc' => 'จอมีเส้นแนวตั้งสีขาวพาดกลางหน้าจอ', 'note' => 'เปลี่ยนจอภาพใหม่ ทดสอบแล้วภาพปกติ'],
            ['s' => 'closed', 'age' => 8, 'by' => 'you', 't' => 'Facilities', 'team' => ['tech1'], 'place' => 'ห้องผู้อำนวยการ', 'cost' => 400,
                'title' => 'เปลี่ยนหลอดไฟและซ่อมบานพับประตูห้อง', 'desc' => 'หลอดไฟดับ 2 ดวงและประตูปิดไม่สนิท', 'note' => 'เปลี่ยนหลอดไฟ LED และปรับบานพับ'],
            ['s' => 'closed', 'age' => 13, 'by' => 'you', 'a' => 'PRN-ADM-01', 't' => 'Hardware', 'team' => ['it2'], 'cost' => 1100,
                'title' => 'เครื่องพิมพ์ห้องธุรการกระดาษติด', 'desc' => 'กระดาษติดทุกครั้งที่พิมพ์สองหน้า', 'note' => 'เปลี่ยนลูกยางดึงกระดาษและทำความสะอาด'],
            ['s' => 'closed', 'age' => 19, 'by' => 'you', 't' => 'Software', 'team' => ['dev'],
                'title' => 'ขอเพิ่มสิทธิ์รายงานระบบ HIS', 'desc' => 'ขอสิทธิ์ดูรายงานสถิติผู้ป่วยรายเดือน', 'note' => 'เพิ่มสิทธิ์รายงานสถิติเรียบร้อย'],
            ['s' => 'closed', 'age' => 24, 'by' => 'you', 'a' => 'PC-ADM-01', 't' => 'Network', 'team' => ['net'], 'rate' => [5, 'รวดเร็ว'],
                'title' => 'ต่อสาย LAN ห้องประชุมเพิ่ม', 'desc' => 'ขอเพิ่มจุด LAN ในห้องประชุมใหญ่ 2 จุด', 'note' => 'เดินสายและทดสอบสัญญาณเรียบร้อย'],

            ['s' => 'closed', 'age' => 27, 'by' => 'opd', 'a' => 'AC-OPD-01', 't' => 'Facilities', 'team' => ['tech1'], 'cost' => 2100, 'rate' => [4, null],
                'title' => 'แอร์ห้องตรวจ 3 น้ำรั่ว', 'desc' => 'น้ำหยดจากแอร์ลงโต๊ะตรวจ', 'note' => 'เปลี่ยนท่อน้ำทิ้งและอุดรอยรั่ว'],
            ['s' => 'closed', 'age' => 29, 'by' => 'fin', 'a' => 'PRN-FIN-01', 't' => 'Hardware', 'team' => ['it2'], 'cost' => 1450, 'rate' => [5, null],
                'title' => 'เปลี่ยนตลับผ้าหมึกและเคลียร์กระดาษติด', 'desc' => 'ใบเสร็จพิมพ์ไม่ติดและกระดาษติดในเครื่อง', 'note' => 'เปลี่ยนตลับผ้าหมึกและทำความสะอาดหัวพิมพ์'],

            // closed more than 30 days ago and never rated: the rating window has passed
            ['s' => 'closed', 'age' => 45, 'by' => 'pharm', 'a' => 'PRN-PH-01', 't' => 'Hardware', 'team' => ['it1'], 'cost' => 800,
                'title' => 'เครื่องพิมพ์ฉลากยาไม่ตอบสนอง', 'desc' => 'ส่งพิมพ์แล้วเครื่องไม่ทำงาน ไฟกระพริบแดง', 'note' => 'รีเซ็ตเฟิร์มแวร์และเปลี่ยนสาย USB'],
            ['s' => 'closed', 'age' => 60, 'by' => 'lab', 'a' => 'REF-LAB-01', 't' => 'Facilities', 'team' => ['tech2'], 'cost' => 4200,
                'title' => 'ตู้เย็นเก็บสารเคมีอุณหภูมิไม่คงที่', 'desc' => 'อุณหภูมิในตู้ขึ้นลงไม่คงที่ กระทบการเก็บน้ำยา', 'note' => 'เปลี่ยนเทอร์โมสตัทและตรวจเช็กคอมเพรสเซอร์'],
            ['s' => 'closed', 'age' => 75, 'by' => 'ipd', 'a' => 'PC-IPD-01', 't' => 'Hardware', 'team' => ['it2'], 'rate' => [4, null],
                'title' => 'อัปเกรด RAM เคาน์เตอร์พยาบาล', 'desc' => 'เครื่องช้าเมื่อเปิดโปรแกรมพร้อมกันหลายตัว', 'note' => 'เพิ่ม RAM เป็น 16GB'],

            // worked by someone who has since left (suspended): history stays, nothing open
            ['s' => 'closed', 'age' => 90, 'by' => 'opd', 'a' => 'PC-OPD-02', 't' => 'Hardware', 'team' => ['gone'], 'cost' => 6500, 'rate' => [4, null],
                'title' => 'เปลี่ยนเมนบอร์ดเครื่องห้องตรวจ 2', 'desc' => 'เครื่องเปิดไม่ติด ไฟเลี้ยงมาแต่จอไม่ขึ้นภาพ', 'note' => 'เปลี่ยนเมนบอร์ดและติดตั้งระบบใหม่'],
            ['s' => 'closed', 'age' => 120, 'by' => 'er', 'a' => 'PRN-ER-01', 't' => 'Hardware', 'team' => ['gone'], 'rate' => [5, null],
                'title' => 'เครื่องพิมพ์ไม่พบในเครือข่าย', 'desc' => 'ส่งพิมพ์จากเครื่องอื่นไม่ได้ ขึ้นว่าไม่พบเครื่องพิมพ์', 'note' => 'กำหนด IP ใหม่และติดตั้งไดรเวอร์'],
            ['s' => 'closed', 'age' => 150, 'by' => 'fin', 'a' => 'PC-FIN-01', 't' => 'Hardware', 'team' => ['gone'], 'cost' => 1200, 'rate' => [3, null],
                'title' => 'อัปเกรด RAM เครื่องการเงิน', 'desc' => 'เปิดไฟล์ Excel ขนาดใหญ่แล้วค้าง', 'note' => 'เพิ่ม RAM 8GB'],

            // a type that has since been switched off, and a request that never had a type or an asset
            ['s' => 'closed', 'age' => 200, 'by' => 'opd', 't' => 'ระบบเดิม', 'team' => ['dev'], 'rate' => [4, null], 'place' => 'ห้องบัตร ผู้ป่วยนอก',
                'title' => 'ขอรหัสผ่านระบบรายงานเก่า', 'desc' => 'ลืมรหัสผ่านระบบรายงานตัวเดิมที่เคยใช้', 'note' => 'รีเซ็ตรหัสผ่านและแจ้งผู้ใช้'],
            ['s' => 'closed', 'age' => 100, 'by' => 'ipd', 'team' => ['tech1'], 'place' => 'เคาน์เตอร์พยาบาล', 'rate' => [3, null],
                'title' => 'ประสานย้ายจุดโทรศัพท์ภายใน', 'desc' => 'ขอย้ายเครื่องโทรศัพท์ภายในไปอีกฝั่งของเคาน์เตอร์', 'note' => 'ย้ายเครื่องโทรศัพท์และเดินสายใหม่'],

            // ================= closed last year (year-over-year figures), incl. two assets that were later disposed
            ['s' => 'closed', 'age' => $ly + 15, 'by' => 'opd', 'a' => 'PC-OPD-03', 't' => 'Hardware', 'team' => ['it1'], 'cost' => 1900, 'rate' => [4, null],
                'title' => 'เปลี่ยนพาวเวอร์ซัพพลายเครื่องจุดคัดกรอง', 'desc' => 'เครื่องดับกลางคัน', 'note' => 'เปลี่ยนพาวเวอร์ซัพพลาย'],
            ['s' => 'closed', 'age' => $ly + 45, 'by' => 'ipd', 'a' => 'PRN-IPD-01', 't' => 'Hardware', 'team' => ['gone'], 'cost' => 2600, 'rate' => [5, null],
                'title' => 'เปลี่ยนหัวพิมพ์เครื่องพิมพ์ใบสั่งยา', 'desc' => 'พิมพ์ขาดเส้น', 'note' => 'เปลี่ยนหัวพิมพ์'],
            ['s' => 'closed', 'age' => $ly + 80, 'by' => 'er', 'a' => 'PC-ER-01', 't' => 'Hardware', 'team' => ['it2'], 'rate' => [4, null],
                'title' => 'เครื่องห้องฉุกเฉินไม่เห็นเครือข่าย', 'desc' => 'ขึ้นว่าไม่ได้เชื่อมต่อ', 'note' => 'เปลี่ยนสาย LAN'],
            ['s' => 'closed', 'age' => $ly + 120, 'by' => 'lab', 'a' => 'ANL-LAB-01', 't' => 'Facilities', 'team' => ['tech2'], 'cost' => 12000, 'rate' => [5, null],
                'title' => 'เปลี่ยนชุดปั๊มน้ำเครื่องวิเคราะห์', 'desc' => 'เครื่องแจ้งเตือนแรงดันน้ำต่ำ', 'note' => 'เปลี่ยนชุดปั๊มน้ำ'],
            ['s' => 'closed', 'age' => $ly + 150, 'by' => 'pharm', 'a' => 'PRN-PH-01', 't' => 'Hardware', 'team' => ['gone'], 'rate' => [4, null],
                'title' => 'ตั้งค่าเครื่องพิมพ์ฉลากใหม่หลังย้ายห้อง', 'desc' => 'ย้ายห้องจ่ายยา ต้องตั้งค่าเครื่องพิมพ์ใหม่', 'note' => 'ตั้งค่าเครือข่ายและทดสอบพิมพ์'],
            ['s' => 'closed', 'age' => $ly + 190, 'by' => 'fin', 'a' => 'PC-FIN-01', 't' => 'Software', 'team' => ['dev'], 'rate' => [3, null],
                'title' => 'ติดตั้งโปรแกรมบัญชีเวอร์ชันใหม่', 'desc' => 'ขอติดตั้งโปรแกรมบัญชีตามที่กรมบัญชีกลางกำหนด', 'note' => 'ติดตั้งและย้ายข้อมูล'],
            ['s' => 'closed', 'age' => $ly + 230, 'by' => 'opd', 'a' => 'NET-AP-OPD-01', 't' => 'Network', 'team' => ['net'], 'rate' => [5, null],
                'title' => 'ติดตั้ง Access Point ตึกผู้ป่วยนอก', 'desc' => 'ขอติดตั้ง Access Point เพิ่มบริเวณห้องตรวจ', 'note' => 'ติดตั้งและตั้งค่าเรียบร้อย'],
            ['s' => 'closed', 'age' => $ly + 270, 'by' => 'ipd', 'a' => 'AC-IPD-01', 't' => 'Facilities', 'team' => ['tech1'], 'cost' => 1800, 'rate' => [4, null],
                'title' => 'ล้างแอร์หอผู้ป่วยใน', 'desc' => 'ล้างแอร์ประจำปี', 'note' => 'ล้างแอร์และเติมน้ำยา'],
            ['s' => 'closed', 'age' => $ly + 310, 'by' => 'er', 'a' => 'MON-ER-01', 't' => 'Facilities', 'team' => ['tech2'], 'rate' => [2, 'ซ่อมช้า ต้องรอนานหลายวัน'], 'work' => 5000,
                'title' => 'มอนิเตอร์ห้องช่วยชีวิตปิดเองระหว่างใช้งาน', 'desc' => 'เครื่องปิดเองโดยไม่มีสาเหตุ', 'note' => 'เปลี่ยนแบตเตอรี่และบอร์ดจ่ายไฟ'],
            ['s' => 'closed', 'age' => $ly + 330, 'by' => 'opd', 'a' => 'PC-OLD-01', 't' => 'Hardware', 'team' => ['it1'], 'cost' => 800, 'rate' => [4, null],
                'title' => 'เปลี่ยนแป้นพิมพ์และเมาส์เครื่องเก่า', 'desc' => 'ปุ่มคีย์บอร์ดหลุดหลายปุ่ม', 'note' => 'เปลี่ยนชุดคีย์บอร์ดเมาส์'],
            ['s' => 'closed', 'age' => $ly + 300, 'by' => 'fin', 'a' => 'PRN-OLD-01', 't' => 'Hardware', 'team' => ['it2'], 'rate' => [3, null],
                'title' => 'ทำความสะอาดเครื่องพิมพ์เก่า', 'desc' => 'พิมพ์มีรอยเปื้อน', 'note' => 'ทำความสะอาดลูกกลิ้ง'],

            // ================= cancelled at different stages
            ['s' => 'cancelled', 'age' => 2, 'by' => 'opd', 't' => 'Hardware', 'place' => 'ห้องตรวจ 2', 'cancel_after' => 90, 'reason' => 'แก้ไขเองได้แล้ว (สายไฟหลวม)',
                'title' => 'เครื่องคอมพิวเตอร์ห้องตรวจ 2 เปิดไม่ติด', 'desc' => 'เปิดเครื่องแล้วไม่มีไฟ'],
            ['s' => 'cancelled', 'from' => 'accepted', 'age' => 4, 'by' => 'ipd', 't' => 'Hardware', 'team' => ['it2'], 'cancel_by' => 'you', 'place' => 'หอผู้ป่วยใน ชั้น 4',
                'reason' => 'งบประมาณไม่ได้รับอนุมัติ ยกเลิกการติดตั้งเครื่องพิมพ์เพิ่ม',
                'title' => 'ขอติดตั้งเครื่องพิมพ์เพิ่มที่หอผู้ป่วยใน', 'desc' => 'ต้องการเครื่องพิมพ์อีก 1 เครื่องที่เคาน์เตอร์ชั้น 4'],
            ['s' => 'cancelled', 'from' => 'in_progress', 'age' => 6, 'by' => 'er', 't' => 'Network', 'team' => ['net'], 'cancel_by' => 'sup', 'place' => 'ห้องฉุกเฉิน',
                'reason' => 'แจ้งซ้ำกับใบงานอื่น ปัญหาเดียวกัน',
                'title' => 'ตรวจสอบสัญญาณ WiFi ห้องฉุกเฉิน', 'desc' => 'WiFi ห้องฉุกเฉินสัญญาณไม่นิ่ง'],
            ['s' => 'cancelled', 'from' => 'acknowledged', 'age' => 8, 'by' => 'lab', 'a' => 'PC-LAB-01', 't' => 'Hardware', 'reason' => 'ไม่ต้องการแล้ว เปลี่ยนไปใช้เครื่องสำรอง',
                'title' => 'ขอเปลี่ยนเครื่องคอมพิวเตอร์ห้อง LAB', 'desc' => 'เครื่องเก่าช้า ขอเปลี่ยนเป็นเครื่องใหม่'],

            // ================= rejected: out of scope
            ['s' => 'rejected', 'age' => 3, 'by' => 'fin', 'place' => 'ห้องการเงิน ชั้น 2', 'reason' => 'ไม่อยู่ในขอบเขตงาน — เป็นอุปกรณ์ส่วนตัว',
                'title' => 'ขอให้ซ่อมโทรศัพท์มือถือส่วนตัว', 'desc' => 'โทรศัพท์มือถือจอแตก ขอให้ช่วยดูให้'],
            ['s' => 'rejected', 'from' => 'acknowledged', 'age' => 5, 'by' => 'opd', 't' => 'Facilities', 'place' => 'ตึกผู้ป่วยนอก', 'reason' => 'เป็นงานของการไฟฟ้า ให้ประสานหน่วยงานภายนอก',
                'title' => 'มิเตอร์ไฟฟ้าอาคารผู้ป่วยนอกเดินผิดปกติ', 'desc' => 'ค่ามิเตอร์ไฟฟ้าเพิ่มเร็วผิดปกติ'],
            ['s' => 'rejected', 'age' => 12, 'by' => 'er', 'place' => 'ห้องฉุกเฉิน', 'reason' => 'ไม่ใช่งานซ่อมบำรุง — ให้ทำเรื่องขอเบิกครุภัณฑ์กับฝ่ายพัสดุ',
                'title' => 'ขอเปลี่ยนโต๊ะทำงานใหม่', 'desc' => 'โต๊ะเก่าเล็กเกินไป ขอเปลี่ยนเป็นตัวใหม่'],
        ];
    }
}

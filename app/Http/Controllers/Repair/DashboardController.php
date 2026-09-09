<?php

namespace App\Http\Controllers\Repair;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $req)
    {
        $hasReqDate       = Cache::remember('schema.has_mr_request_date', 3600, fn() => Schema::hasColumn('maintenance_requests','request_date'));
        $hasCreatedAt     = Cache::remember('schema.has_mr_created_at', 3600, fn() => Schema::hasColumn('maintenance_requests','created_at'));
        $hasCompletedDate = Cache::remember('schema.has_mr_completed_date', 3600, fn() => Schema::hasColumn('maintenance_requests','completed_date'));
        $hasCompletedAt   = Cache::remember('schema.has_mr_completed_at', 3600, fn() => Schema::hasColumn('maintenance_requests','completed_at'));
        $hasMrDeptId      = Cache::remember('schema.has_mr_department_id', 3600, fn() => Schema::hasColumn('maintenance_requests','department_id'));

        $hasAssets        = Cache::remember('schema.has_assets_table', 3600, fn() => Schema::hasTable('assets'));
        $hasType          = $hasAssets && Cache::remember('schema.has_assets_type', 3600, fn() => Schema::hasColumn('assets','type'));
        $hasAssetDeptId   = $hasAssets && Cache::remember('schema.has_assets_department_id', 3600, fn() => Schema::hasColumn('assets','department_id'));

        $hasDeptTbl       = Cache::remember('schema.has_departments_table', 3600, fn() => Schema::hasTable('departments'));
        $hasDeptNameTh    = $hasDeptTbl && Cache::remember('schema.has_departments_name_th', 3600, fn() => Schema::hasColumn('departments','name_th'));
        $hasDeptNameEn    = $hasDeptTbl && Cache::remember('schema.has_departments_name_en', 3600, fn() => Schema::hasColumn('departments','name_en'));

        $base = DB::table('maintenance_requests as mr');

        $status = (string) $req->query('status', '');
        $from   = $req->query('from');
        $to     = $req->query('to');

        $hasFilter = false;

        // ----- Filters -----
        if ($status !== '') {
            $statusMap = [
                'completed'  => ['resolved', 'closed'],
                'processing' => ['acknowledged', 'accepted', 'in_progress', 'on_hold'],
                'cancelled'  => ['cancelled', 'rejected'],
            ];

            if (isset($statusMap[$status])) {
                $base->whereIn('mr.status', $statusMap[$status]);
            } else {
                $base->where('mr.status', $status);
            }
            $hasFilter = true;
        }

        $dateCol = $hasReqDate ? 'mr.request_date' : ($hasCreatedAt ? 'mr.created_at' : null);

        if ($from && $dateCol) {
            try {
                $base->whereDate($dateCol, '>=', Carbon::parse($from)->toDateString());
                $hasFilter = true;
            } catch (\Throwable $e) {}
        }

        if ($to && $dateCol) {
            try {
                $base->whereDate($dateCol, '<=', Carbon::parse($to)->toDateString());
                $hasFilter = true;
            } catch (\Throwable $e) {}
        }

        // ----- Stats card (Overview) -----
        $allStatuses = ['pending', 'acknowledged', 'accepted', 'in_progress', 'on_hold', 'resolved', 'closed'];
        $stats = [
            'total'      => (clone $base)->count(),
            'monthCost'  => 0.0,
        ];

        // Individual counts for all statuses
        $statusCounts = (clone $base)->select('mr.status', DB::raw('count(*) as count'))->groupBy('mr.status')->pluck('count', 'status');
        foreach ($allStatuses as $s) {
            $stats[$s] = $statusCounts->get($s, 0);
        }

        // Grouped stats for convenience
        $stats['processing'] = $stats['acknowledged'] + $stats['accepted'] + $stats['in_progress'] + $stats['on_hold'];
        $stats['completed']  = $stats['resolved'] + $stats['closed'];
        $stats['cancelled']  = 0; // Hide/ignore

        // สำหรับ UI card ที่เขียนว่า "Active"
        $stats['inProgress'] = $stats['processing'];

        // ----- Monthly trend (Default: 12 months) -----
        if ($dateCol) {
            $trendQuery = (clone $base);
            
            // If no FROM date is filtered, default to last 12 months
            if (!$from) {
                $trendQuery->where($dateCol, '>=', now()->startOfMonth()->subMonths(11));
            }

            $monthlyTrend = $trendQuery
                ->selectRaw("DATE_FORMAT($dateCol, '%Y-%m') as ym, COUNT(*) as cnt")
                ->groupBy('ym')
                ->orderBy('ym')
                ->get()
                ->map(fn($r) => [
                    'ym'  => (string)$r->ym,
                    'cnt' => (int)$r->cnt,
                ])
                ->values();
        } else {
            $monthlyTrend = collect();
        }

        // ----- KPI สำหรับการ์ดบนซ้าย (Last month / This month / Completed-this-month) -----
        $kpi = [
            'lastYear'          => 0,
            'thisYear'          => 0,
            'thisYearCompleted' => 0,
            'avgResolveHours'   => null,
        ];

        if ($dateCol) {
            // Year-to-Date vs the same span last year — the KPI cards read
            // "ปีนี้ / ปีที่แล้ว" (the keys used to say "month", which was wrong).
            $startThis = now()->startOfYear();
            $startLast = (clone $startThis)->subYear();

            $endThis = now()->endOfDay();
            $endLast = (clone $startThis)->subSecond();

            $kpiStats = (clone $base)->selectRaw("
                SUM(CASE WHEN $dateCol BETWEEN ? AND ? THEN 1 ELSE 0 END) as this_year,
                SUM(CASE WHEN $dateCol BETWEEN ? AND ? THEN 1 ELSE 0 END) as last_year,
                SUM(CASE WHEN $dateCol BETWEEN ? AND ? AND mr.status IN ('resolved','closed') THEN 1 ELSE 0 END) as this_year_completed,
                SUM(CASE WHEN $dateCol BETWEEN ? AND ? AND mr.status IN ('resolved','closed') THEN 1 ELSE 0 END) as last_year_completed
            ", [$startThis, $endThis, $startLast, $endLast, $startThis, $endThis, $startLast, $endLast])->first();

            $kpi['thisYear'] = (int) $kpiStats->this_year;
            $kpi['lastYear'] = (int) $kpiStats->last_year;
            $kpi['thisYearCompleted'] = (int) $kpiStats->this_year_completed;
            $kpi['lastYearCompleted'] = (int) $kpiStats->last_year_completed;

            // Calculate trends (Percentage like stocks)
            $calcTrend = function($current, $previous) {
                if ($previous == 0) return $current > 0 ? 100 : 0;
                $val = round((($current - $previous) / $previous) * 100, 1);
                return $val > 999 ? 999 : ($val < -999 ? -999 : $val);
            };

            $kpi['totalTrend']     = $calcTrend($kpi['thisYear'], $kpi['lastYear']);
            $kpi['completedTrend'] = $calcTrend($kpi['thisYearCompleted'], $kpi['lastYearCompleted']);
        }

        // avgResolveHours (ถ้ามี completed date/at)
        $compCol = $hasCompletedAt ? 'mr.completed_at' : ($hasCompletedDate ? 'mr.completed_date' : null);
        if ($dateCol && $compCol) {
            // ใช้เฉพาะงาน completed
            $rows = (clone $base)
                ->whereIn('mr.status', ['resolved','closed'])
                ->whereNotNull($compCol)
                ->whereNotNull($dateCol)
                ->selectRaw("TIMESTAMPDIFF(MINUTE, $dateCol, $compCol) as diff_min")
                ->limit(3000)
                ->pluck('diff_min');

            if ($rows->count() > 0) {
                $avgMin = (int) round($rows->avg());
                $kpi['avgResolveHours'] = round($avgMin / 60, 1);
            }
        }

        $totalReq = (int)($stats['total'] ?? 0);

        // สร้าง Base Query สำหรับกราฟเพื่อให้แสดงผลเท่ากัน (Default ย้อนหลัง 12 เดือน)
        $chartBase = clone $base;
        if (!$from && $dateCol) {
            $chartBase->where($dateCol, '>=', now()->startOfMonth()->subMonths(11));
        }

        // ==============================
        //  By asset type
        // ==============================
        if ($hasAssets) {
            $qType = (clone $chartBase)
                ->leftJoin('assets as a', 'a.id', '=', 'mr.asset_id');

            if ($hasType) {
                $assetTypes = $qType
                    ->selectRaw('COALESCE(NULLIF(a.type,""),"ไม่ระบุ") as type, COUNT(*) as cnt')
                    ->groupBy('type')
                    ->orderByDesc('cnt')
                    ->get();
            } else {
                $assetTypes = collect([(object) ['type' => 'ไม่ระบุ', 'cnt' => $chartBase->count()]]);
            }
        } else {
            $assetTypes = collect([(object) ['type' => 'ไม่ระบุ', 'cnt' => $chartBase->count()]]);
        }

        $byAssetType = $assetTypes->map(fn($r) => [
            'type' => (string) $r->type,
            'cnt'  => (int) $r->cnt,
        ])->values();

        // ==============================
        //  By department
        // ==============================
        if ($hasDeptTbl && ($hasDeptNameTh || $hasDeptNameEn)) {
            $qDept = (clone $chartBase);

            if ($hasAssets) {
                $qDept->leftJoin('assets as a', 'a.id', '=', 'mr.asset_id');
            }

            if ($hasMrDeptId) {
                $qDept->leftJoin('departments as d_mr', 'd_mr.id', '=', 'mr.department_id');
            }

            if ($hasAssetDeptId) {
                $qDept->leftJoin('departments as d_a', 'd_a.id', '=', 'a.department_id');
            }

            $labelSqlParts = [];
            if ($hasDeptNameTh) $labelSqlParts[] = "NULLIF(TRIM(d_mr.name_th),'')";
            if ($hasDeptNameEn) $labelSqlParts[] = "NULLIF(TRIM(d_mr.name_en),'')";
            if ($hasDeptNameTh) $labelSqlParts[] = "NULLIF(TRIM(d_a.name_th),'')";
            if ($hasDeptNameEn) $labelSqlParts[] = "NULLIF(TRIM(d_a.name_en),'')";

            $coalesce = 'COALESCE(' . implode(',', $labelSqlParts) . ", 'ไม่ระบุ')";

            $byDept = $qDept
                ->selectRaw("$coalesce as dept, COUNT(*) as cnt")
                ->groupBy('dept')
                ->orderByDesc('cnt')
                ->get()
                ->map(fn($r) => [
                    'dept' => (string) $r->dept,
                    'cnt'  => (int) $r->cnt,
                ])
                ->values();
        } else {
            $byDept = $totalReq > 0
                ? collect([['dept' => 'ไม่ระบุ', 'cnt' => $totalReq]])
                : collect();
        }

        // ----- Toast เมื่อมีการใช้ตัวกรอง -----
        if ($hasFilter) {
            if ($stats['total'] > 0) {
                $req->session()->flash('toast', [
                    'type'     => 'success',
                    'message'  => "ค้นหาแล้ว: พบ {$stats['total']} รายการ",
                    'position' => 'tc',
                    'timeout'  => 2800,
                    'size'     => 'md',
                ]);
            } else {
                $req->session()->flash('toast', [
                    'type'     => 'warning',
                    'message'  => 'ไม่พบรายการตามเงื่อนไขที่ค้นหา',
                    'position' => 'tc',
                    'timeout'  => 3200,
                    'size'     => 'md',
                ]);
            }
        }

        // ----- Technician Workload -----
        $hasTechId = Schema::hasColumn('maintenance_requests', 'technician_id');
        $hasUsers  = Schema::hasTable('users') && Schema::hasColumn('users', 'name');

        if ($hasTechId && $hasUsers) {
            $techRows = DB::table('maintenance_requests as mr')
                ->join('users as t', 't.id', '=', 'mr.technician_id')
                ->whereNotNull('mr.technician_id')
                ->whereNotIn('mr.status', ['resolved', 'closed', 'cancelled'])
                ->selectRaw("t.id as tech_id, COUNT(*) as total")
                ->groupBy('t.id')
                ->orderByDesc('total')
                ->limit(15)
                ->get();

            $techIds = $techRows->pluck('tech_id')->all();
            $users   = \App\Models\User::whereIn('id', $techIds)->get()->keyBy('id');

            $techWorkload = $techRows->map(function ($r) use ($users) {
                $user = $users->get($r->tech_id);
                return [
                    'id'         => (int) $r->tech_id,
                    'name'       => $user ? $user->clean_name : 'Unknown',
                    'role_label' => $user ? $user->role_label : '',
                    'total'      => (int) $r->total,
                    'avatar'     => $user ? $user->avatar_thumb_url : '',
                ];
            })->values();
        } else {
            $techWorkload = collect();
        }

        return view('repair.dashboard',
            compact('stats','monthlyTrend','byAssetType','byDept','kpi','techWorkload')
        );
    }
}

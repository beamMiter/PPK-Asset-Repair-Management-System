<?php

namespace App\Http\Controllers\Repair;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** The date every dashboard number is grouped / filtered by. */
    private const DATE_COL = 'mr.request_date';

    private const UNSPECIFIED = 'ไม่ระบุ';

    public function index(Request $req)
    {
        $base = DB::table('maintenance_requests as mr');
        $from = $req->query('from');

        $hasFilter = $this->applyFilters($base, $req);

        $stats = $this->stats($base);

        // Charts default to the last 12 months when no start date is chosen.
        $chartBase = clone $base;
        if (! $from) {
            $chartBase->where(self::DATE_COL, '>=', now()->startOfMonth()->subMonths(11));
        }

        $monthlyTrend = $this->monthlyTrend($chartBase);
        $kpi = $this->kpi($base);
        $byAssetType = $this->byAssetType($chartBase);
        $byDept = $this->byDepartment($chartBase, (int) $stats['total']);

        if ($hasFilter) {
            $req->session()->flash('toast', $stats['total'] > 0
                ? ['type' => 'success', 'message' => "ค้นหาแล้ว: พบ {$stats['total']} รายการ", 'position' => 'tc', 'timeout' => 2800, 'size' => 'md']
                : ['type' => 'warning', 'message' => 'ไม่พบรายการตามเงื่อนไขที่ค้นหา', 'position' => 'tc', 'timeout' => 3200, 'size' => 'md']);
        }

        $techWorkload = $this->technicianWorkload();

        return view('repair.dashboard', compact('stats', 'monthlyTrend', 'byAssetType', 'byDept', 'kpi', 'techWorkload'));
    }

    /** Applies ?status / ?from / ?to to the base query; true when at least one filter was applied. */
    private function applyFilters(Builder $base, Request $req): bool
    {
        $hasFilter = false;
        $status = (string) $req->query('status', '');

        if ($status !== '') {
            $statusMap = [
                'completed' => ['resolved', 'closed'],
                'processing' => ['acknowledged', 'accepted', 'in_progress', 'on_hold'],
                'cancelled' => ['cancelled', 'rejected'],
            ];

            isset($statusMap[$status])
                ? $base->whereIn('mr.status', $statusMap[$status])
                : $base->where('mr.status', $status);

            $hasFilter = true;
        }

        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            $value = $req->query($key);
            if (! $value) {
                continue;
            }

            try {
                $base->whereDate(self::DATE_COL, $operator, Carbon::parse($value)->toDateString());
                $hasFilter = true;
            } catch (\Throwable) {
                // an unparsable date is ignored, as before
            }
        }

        return $hasFilter;
    }

    /** @return array<string, int|float> */
    private function stats(Builder $base): array
    {
        $counts = (clone $base)
            ->select('mr.status', DB::raw('count(*) as count'))
            ->groupBy('mr.status')
            ->pluck('count', 'status');

        $stats = ['total' => (clone $base)->count()];

        foreach (['pending', 'acknowledged', 'accepted', 'in_progress', 'on_hold', 'resolved', 'closed'] as $status) {
            $stats[$status] = (int) $counts->get($status, 0);
        }

        $stats['processing'] = $stats['acknowledged'] + $stats['accepted'] + $stats['in_progress'] + $stats['on_hold'];
        $stats['completed'] = $stats['resolved'] + $stats['closed'];
        $stats['cancelled'] = 0;
        $stats['inProgress'] = $stats['processing']; // the "Active" card

        return $stats;
    }

    private function monthlyTrend(Builder $chartBase): Collection
    {
        return (clone $chartBase)
            ->selectRaw("DATE_FORMAT(".self::DATE_COL.", '%Y-%m') as ym, COUNT(*) as cnt")
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->map(fn ($r) => ['ym' => (string) $r->ym, 'cnt' => (int) $r->cnt])
            ->values();
    }

    /**
     * Year-to-date vs the same span last year, plus the average time from request to completion.
     *
     * @return array<string, int|float|null>
     */
    private function kpi(Builder $base): array
    {
        $date = self::DATE_COL;
        $startThis = now()->startOfYear();
        $startLast = (clone $startThis)->subYear();
        $endThis = now()->endOfDay();
        $endLast = (clone $startThis)->subSecond();

        $row = (clone $base)->selectRaw("
            SUM(CASE WHEN $date BETWEEN ? AND ? THEN 1 ELSE 0 END) as this_year,
            SUM(CASE WHEN $date BETWEEN ? AND ? THEN 1 ELSE 0 END) as last_year,
            SUM(CASE WHEN $date BETWEEN ? AND ? AND mr.status IN ('resolved','closed') THEN 1 ELSE 0 END) as this_year_completed,
            SUM(CASE WHEN $date BETWEEN ? AND ? AND mr.status IN ('resolved','closed') THEN 1 ELSE 0 END) as last_year_completed
        ", [$startThis, $endThis, $startLast, $endLast, $startThis, $endThis, $startLast, $endLast])->first();

        $kpi = [
            'thisYear' => (int) $row->this_year,
            'lastYear' => (int) $row->last_year,
            'thisYearCompleted' => (int) $row->this_year_completed,
            'lastYearCompleted' => (int) $row->last_year_completed,
            'avgResolveHours' => $this->averageResolveHours($base),
        ];

        // percentage change, capped so one empty year cannot print "+38000%"
        $trend = function (int $current, int $previous): float|int {
            if ($previous === 0) {
                return $current > 0 ? 100 : 0;
            }

            return max(-999, min(999, round((($current - $previous) / $previous) * 100, 1)));
        };

        $kpi['totalTrend'] = $trend($kpi['thisYear'], $kpi['lastYear']);
        $kpi['completedTrend'] = $trend($kpi['thisYearCompleted'], $kpi['lastYearCompleted']);

        return $kpi;
    }

    /**
     * Mean hours from the request date to `completed_date` (set when a request is closed) over every finished
     * request the filters allow. This used to average an unordered `limit(3000)` sample, so past 3000 rows the number
     * depended on whichever rows the database happened to return.
     */
    private function averageResolveHours(Builder $base): ?float
    {
        $minutes = (clone $base)
            ->whereIn('mr.status', ['resolved', 'closed'])
            ->whereNotNull('mr.completed_date')
            ->whereNotNull(self::DATE_COL)
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, '.self::DATE_COL.', mr.completed_date)) as avg_min')
            ->value('avg_min');

        return $minutes === null ? null : round(((int) round((float) $minutes)) / 60, 1);
    }

    private function byAssetType(Builder $chartBase): Collection
    {
        return (clone $chartBase)
            ->leftJoin('assets as a', 'a.id', '=', 'mr.asset_id')
            ->selectRaw('COALESCE(NULLIF(a.type,""),"'.self::UNSPECIFIED.'") as type, COUNT(*) as cnt')
            ->groupBy('type')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn ($r) => ['type' => (string) $r->type, 'cnt' => (int) $r->cnt])
            ->values();
    }

    /** A request's department, falling back to its asset's department; Thai name first, then English. */
    private function byDepartment(Builder $chartBase, int $total): Collection
    {
        $label = "COALESCE(NULLIF(TRIM(d_mr.name_th),''), NULLIF(TRIM(d_mr.name_en),''), "
            ."NULLIF(TRIM(d_a.name_th),''), NULLIF(TRIM(d_a.name_en),''), '".self::UNSPECIFIED."')";

        return (clone $chartBase)
            ->leftJoin('assets as a', 'a.id', '=', 'mr.asset_id')
            ->leftJoin('departments as d_mr', 'd_mr.id', '=', 'mr.department_id')
            ->leftJoin('departments as d_a', 'd_a.id', '=', 'a.department_id')
            ->selectRaw("$label as dept, COUNT(*) as cnt")
            ->groupBy('dept')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn ($r) => ['dept' => (string) $r->dept, 'cnt' => (int) $r->cnt])
            ->values();
    }

    /** Open requests per assigned technician, busiest first (top 15). */
    private function technicianWorkload(): Collection
    {
        $rows = DB::table('maintenance_requests as mr')
            ->join('users as t', 't.id', '=', 'mr.technician_id')
            ->whereNotIn('mr.status', ['resolved', 'closed', 'cancelled'])
            ->selectRaw('t.id as tech_id, COUNT(*) as total')
            ->groupBy('t.id')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        $users = User::whereIn('id', $rows->pluck('tech_id'))->get()->keyBy('id');

        return $rows->map(function ($r) use ($users) {
            $user = $users->get($r->tech_id);

            return [
                'id' => (int) $r->tech_id,
                'name' => $user ? $user->clean_name : 'Unknown',
                'role_label' => $user ? $user->role_label : '',
                'total' => (int) $r->total,
                'avatar' => $user ? $user->avatar_thumb_url : '',
            ];
        })->values();
    }
}

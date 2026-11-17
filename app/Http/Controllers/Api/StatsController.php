<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function summary()
    {
        $key = 'stats:summary:v1';
        $payload = Cache::remember($key, 60, function () {
            $assetTotal = DB::table('assets')->whereNull('deleted_at')->count();
            // Open / closed sets must match the model's canonical split
            // (MaintenanceRequest::syncAssetStatus): 'acknowledged' is open,
            // 'rejected' is terminal — both were missing here.
            $openRequests = DB::table('maintenance_requests')->whereNull('deleted_at')->whereIn('status', [
                'pending','acknowledged','accepted','in_progress','on_hold'
            ])->count();
            $closedRequests = DB::table('maintenance_requests')->whereNull('deleted_at')->whereIn('status', [
                'resolved','closed','cancelled','rejected'
            ])->count();


            $recent = DB::table('maintenance_requests')
                ->whereNull('deleted_at')
                ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
                ->where('created_at','>=', now()->subDays(6)->startOfDay())
                ->groupBy('d')
                ->orderBy('d','asc')
                ->pluck('c','d')
                ->all();
            $recentSeries = [];
            for ($i=6; $i>=0; $i--) {
                $day = now()->subDays($i)->format('Y-m-d');
                $recentSeries[] = ['date' => $day, 'count' => (int)($recent[$day] ?? 0)];
            }

            return [
                'assets_total'    => $assetTotal,
                'requests_open'   => $openRequests,
                'requests_closed' => $closedRequests,
                'recent_daily'    => $recentSeries,
            ];
        });

        return response()->json($payload);
    }

    public function maintenanceStatusCounts()
    {
        $raw = Cache::remember('stats:maintenance_status_counts:v1', 60, function () {
            return DB::table('maintenance_requests')
                ->whereNull('deleted_at')
                ->selectRaw('status, COUNT(*) as c')
                ->groupBy('status')
                ->pluck('c','status')
                ->all();
        });
        return response()->json(['data' => $raw]);
    }

    public function technicianSummary()
    {
        // Per person on the job, not per person in charge: everybody on a request's team (and not taken off it) carries it, and the one in
        // charge with no team row (older data) still counts, once (UNION, not UNION ALL: a (person, request) pair is one row). The team
        // dialog leaves `technician_id` empty, so counting only that left out every job it had been used on. v2: the meaning changed.
        $rows = Cache::remember('stats:technician_summary:v2', 60, function () {
            $onTheTeam = DB::table('maintenance_assignments as a')
                ->join('maintenance_requests as m', 'm.id', '=', 'a.maintenance_request_id')
                ->whereNull('m.deleted_at')
                ->where('a.status', '!=', 'cancelled')
                ->select('a.user_id as person_id', 'm.id as request_id');
            $inCharge = DB::table('maintenance_requests as m')
                ->whereNull('m.deleted_at')
                ->whereNotNull('m.technician_id')
                ->select('m.technician_id as person_id', 'm.id as request_id');

            return DB::query()
                ->fromSub($onTheTeam->union($inCharge), 'p')
                ->join('maintenance_requests as mr', 'mr.id', '=', 'p.request_id')
                ->selectRaw('p.person_id as id,
                    SUM(CASE WHEN mr.status IN (\'pending\',\'acknowledged\',\'accepted\',\'in_progress\',\'on_hold\') THEN 1 ELSE 0 END) as open_count,
                    SUM(CASE WHEN mr.status IN (\'resolved\',\'closed\',\'cancelled\',\'rejected\') THEN 1 ELSE 0 END) as closed_count,
                    COUNT(*) as total_count,
                    AVG(CASE WHEN mr.resolved_at IS NOT NULL AND mr.started_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, mr.started_at, mr.resolved_at) END) as avg_hours
                ')
                ->groupBy('p.person_id')
                ->orderByDesc('total_count')
                ->orderBy('p.person_id')
                ->limit(50)
                ->get();
        });

        $ids = $rows->pluck('id')->filter()->all();
        $names = $ids ? DB::table('users')->whereIn('id',$ids)->pluck('name','id')->all() : [];

        $mapped = $rows->map(fn($r) => [
            'id'         => $r->id,
            'name'       => $names[$r->id] ?? null,
            'total'      => (int) $r->total_count,
            'open'       => (int) $r->open_count,
            'closed'     => (int) $r->closed_count,
            'avg_hours'  => $r->avg_hours !== null ? round((float) $r->avg_hours, 2) : null,
        ]);

        return response()->json(['data' => $mapped]);
    }

    public function assetsByDepartment()
    {
        $rows = Cache::remember('stats:assets_by_department:v1', 60, function () {
            return DB::table('assets')
            ->selectRaw('department_id as id, COUNT(*) as c')
            ->whereNotNull('department_id')
            ->whereNull('deleted_at')
            ->groupBy('department_id')
            ->orderByDesc('c')
            ->get();
        });
        $ids = $rows->pluck('id')->all();
        // departments have name_th / name_en, not `name` — this endpoint answered 500 every time
        $deptNames = $ids ? Department::whereIn('id', $ids)->get()->pluck('display_name', 'id')->all() : [];
        $mapped = $rows->map(fn($r) => [
            'id'    => $r->id,
            'name'  => $deptNames[$r->id] ?? null,
            'count' => (int) $r->c,
        ]);
        return response()->json(['data' => $mapped]);
    }
}

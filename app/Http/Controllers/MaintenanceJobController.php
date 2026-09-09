<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MaintenanceRequest as MR;
use App\Models\MaintenanceAssignment;
use App\Models\User;
use App\Traits\ApiResponseWithToast;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Support\Toast;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceJobController extends Controller
{
    use ApiResponseWithToast;

    public function myJobsPage(Request $request)
    {
        $user = Auth::user();
        if (!$user || !($user->isAdmin() || $user->isSupervisor() || $user->isTechnician())) {
            abort(403, 'ไม่มีสิทธิ์เข้าถึงหน้านี้');
        }

        $userId = (int) $user->id;

        // Input Filters
        $filter = $request->string('filter', 'all')->toString(); 
        $status = strtolower($request->string('status')->toString());
        $tech   = $request->integer('tech');
        $q      = $request->string('q')->toString();
        $resp   = strtolower($request->string('resp')->toString());
        $typeId = $request->integer('type');

        // Base query for both list and stats
        $base = MR::query()
            ->leftJoin('maintenance_assignments as ma', function ($join) use ($userId) {
                $join->on('ma.maintenance_request_id', '=', 'maintenance_requests.id')
                    ->where('ma.user_id', '=', $userId);
            })
            ->select([
                'maintenance_requests.*',
                DB::raw('ma.response_status as my_response_status'),
                DB::raw('ma.responded_at as my_responded_at'),
            ]);

        // 1. Build List Query
        $query = (clone $base)
            ->with(['department', 'type', 'asset', 'reporter:id,name', 'technician:id,name'])
            // Filter: My Jobs
            ->when($filter === 'my', function ($qb) use ($userId) {
                $qb->where(function ($qq) use ($userId) {
                    $qq->where('maintenance_requests.technician_id', $userId)
                       ->orWhereNotNull('ma.maintenance_request_id');
                });
            })
            // Filter: Available (Acknowledged but no main tech)
            ->when($filter === 'available', function ($qb) {
                $qb->whereNull('maintenance_requests.technician_id')
                   ->where('maintenance_requests.status', MR::STATUS_ACKNOWLEDGED);
            })
            // Extra Filters
            ->when($status, fn($qb) => $qb->where('maintenance_requests.status', $status))
            ->when($tech,   fn($qb) => $qb->where('maintenance_requests.technician_id', $tech))
            ->when($typeId, fn($qb) => $qb->where('maintenance_requests.type_id', $typeId))
            ->when($q,      fn($qb) => $qb->search($q))
            ->when($resp,   fn($qb) => $qb->where('ma.response_status', $resp))
            // Default: Hide closed/cancelled if no status filter
            ->when(!$status, fn($qb) => $qb->whereNotIn('maintenance_requests.status', [MR::STATUS_CLOSED, MR::STATUS_CANCELLED]))
            
            ->orderBy('maintenance_requests.created_at', 'desc')
            ->orderBy('maintenance_requests.id', 'desc');

        $jobs = $query->paginate(20)->withQueryString();

        // 2. Calculate Stats
        // Use the base query so stats match the current filter scope (All vs My Jobs)
        $statsRow = (clone $base)
            ->select([
                DB::raw("SUM(CASE WHEN maintenance_requests.status = 'pending' THEN 1 ELSE 0 END) as pending_count"),
                DB::raw("SUM(CASE WHEN maintenance_requests.status = 'acknowledged' THEN 1 ELSE 0 END) as acknowledged_count"),
                DB::raw("SUM(CASE WHEN maintenance_requests.status = 'accepted' THEN 1 ELSE 0 END) as accepted_count"),
                DB::raw("SUM(CASE WHEN maintenance_requests.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_count"),
                DB::raw("SUM(CASE WHEN maintenance_requests.status = 'on_hold' THEN 1 ELSE 0 END) as on_hold_count"),
                DB::raw("SUM(CASE WHEN maintenance_requests.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count"),
                DB::raw("SUM(CASE WHEN maintenance_requests.status = 'closed' THEN 1 ELSE 0 END) as closed_count"),
            ])
            ->when($filter === 'my', function ($qb) use ($userId) {
                $qb->where(function ($qq) use ($userId) {
                    $qq->where('maintenance_requests.technician_id', $userId)
                       ->orWhereNotNull('ma.maintenance_request_id');
                });
            })
            ->when($filter === 'available', function ($qb) {
                $qb->whereNull('maintenance_requests.technician_id')
                   ->where('maintenance_requests.status', MR::STATUS_ACKNOWLEDGED);
            })
            ->when($tech,   fn($qb) => $qb->where('maintenance_requests.technician_id', $tech))
            ->when($typeId, fn($qb) => $qb->where('maintenance_requests.type_id', $typeId))
            ->when($q,    fn($qb) => $qb->search($q))
            ->first();

        $stats = [
            'pending'      => (int) ($statsRow->pending_count ?? 0),
            'acknowledged' => (int) ($statsRow->acknowledged_count ?? 0),
            'accepted'     => (int) ($statsRow->accepted_count ?? 0),
            'in_progress'  => (int) ($statsRow->in_progress_count ?? 0),
            'on_hold'      => (int) ($statsRow->on_hold_count ?? 0),
            'resolved'     => (int) ($statsRow->resolved_count ?? 0),
            'closed'       => (int) ($statsRow->closed_count ?? 0),
        ];

        // 2.1 Calculate Monthly Average & Current Month Stats
        $thisMonthStart = now()->startOfMonth();
        $monthlyStats = (clone $base)
            ->select([
                DB::raw("SUM(CASE WHEN maintenance_requests.status = 'closed' AND maintenance_requests.closed_at >= '{$thisMonthStart}' THEN 1 ELSE 0 END) as closed_this_month"),
                DB::raw("MIN(maintenance_requests.closed_at) as first_closed_at"),
            ])
            ->when($filter === 'my', function ($qb) use ($userId) {
                $qb->where(function ($qq) use ($userId) {
                    $qq->where('maintenance_requests.technician_id', $userId)
                       ->orWhereNotNull('ma.maintenance_request_id');
                });
            })
            ->first();

        $closedThisMonth = (int) ($monthlyStats->closed_this_month ?? 0);
        $firstClosed = $monthlyStats->first_closed_at ? \Carbon\Carbon::parse($monthlyStats->first_closed_at) : null;
        // Carbon 3: diffInMonths() is signed — $firstClosed is in the past, so
        // pass `true` for an absolute month count (Carbon 2's default).
        $monthsActive = $firstClosed ? max(1, (int) now()->diffInMonths($firstClosed, true) + 1) : 1;
        $avgClosedPerMonth = round($stats['closed'] / $monthsActive, 1);

        $stats['closed_this_month'] = $closedThisMonth;
        $stats['closed_avg_per_month'] = $avgClosedPerMonth;

        // 3. Team list for Filter
        $team = User::whereIn('role', ['admin', 'supervisor', 'technician'])
            ->orderBy('name')
            ->get(['id', 'name']);

        // 4. Job Types for inline editing
        $types = \App\Models\MaintenanceRequestType::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('repair.my-jobs', [
            'list'   => $jobs,
            'stats'  => $stats,
            'team'   => $team,
            'types'  => $types,
            'filter' => $filter,
            'status' => $status,
            'tech'   => $tech,
            'typeId' => $typeId,
            'q'      => $q,
            'resp'   => $resp,
        ]);
    }

    public function myJobs(Request $request)
    {
        $user = $request->user();
        if (!$user || !($user->isAdmin() || $user->isSupervisor() || $user->isTechnician())) {
            return response()->json(['message' => 'ไม่มีสิทธิ์เข้าถึง'], 403);
        }

        $userId = $user->id;
        $search = $request->search;
        $status = $request->status;

        $jobs = MR::with(['department', 'type', 'asset'])
            ->where(function ($q) use ($userId) {
                $q->where('technician_id', $userId)
                  ->orWhereHas('assignments', function ($sq) use ($userId) {
                      $sq->where('user_id', $userId)
                         ->where('status', '!=', MaintenanceAssignment::STATUS_CANCELLED);
                  });
            })
            ->when($search, function ($q, $search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('request_no', 'like', "%{$search}%")
                       ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->when($status, function ($q, $status) {
                if ($status !== 'all') {
                    $q->where('status', $status);
                } else {
                    $q->whereNotIn('status', [MR::STATUS_CLOSED]);
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return response()->json(['data' => $jobs]);
    }

    public function queuePage(Request $request)
    {
        $user = $request->user();
        if (!$user || !($user->isAdmin() || $user->isSupervisor() || $user->isTechnician())) {
            abort(403);
        }

        $technicians = User::whereIn('role', ['admin', 'supervisor', 'technician'])
            ->orderBy('department')
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'department']);

        return view('repair.queue', compact('technicians'));
    }
}

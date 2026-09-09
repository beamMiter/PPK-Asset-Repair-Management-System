<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRequest;
use App\Models\MaintenanceRating;
use App\Models\User;
use App\Services\MaintenanceTransitionService;
use App\Traits\HandlesMaintenanceRating;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class MaintenanceRatingController extends Controller
{
    use HandlesMaintenanceRating;

    public function evaluateList()
    {
        /** @var User $user */
        $user = Auth::user();

        $limitDate = now()->subDays($this->ratingDeadlineDays);

        $baseQuery = MaintenanceRequest::query()
            ->where('reporter_id', $user->id)
            ->where('status', MaintenanceRequest::STATUS_CLOSED);

        // งานที่ยังไม่ให้คะแนน (Paginated: 10 per page)
        // Match withinRatingWindow() exactly: the *first* of
        // closed_at / resolved_at / completed_date, in the past, within the
        // deadline. An OR across the three columns used to surface rows the
        // rating guard then rejected with "เลยระยะเวลา".
        $pendingRequests = (clone $baseQuery)
            ->with(['technician:id,name', 'assignments.user:id,name,role'])
            ->whereDoesntHave('rating')
            ->whereRaw(
                'COALESCE(closed_at, resolved_at, completed_date) BETWEEN ? AND ?',
                [$limitDate, now()]
            )
            ->latest('id')
            ->paginate(10, ['*'], 'pending_page')
            ->withQueryString();

        // งานที่ให้คะแนนแล้ว (Paginated: 10 per page)
        $ratedRequests = (clone $baseQuery)
            ->with([
                'technician:id,name',
                'rating',
            ])
            ->whereHas('rating')
            ->latest('closed_at')
            ->paginate(10, ['*'], 'history_page')
            ->withQueryString();

        // NEW: Calculate Stats for the reporter
        $totalRatedCount = $ratedRequests->total();
        $pendingCount    = $pendingRequests->total();
        
        // ดึงคะแนนเฉลี่ยที่ผู้ใช้คนนี้เคยให้ (คำนวณจากสถิติจริง ไม่ใช่แค่ในหน้า)
        $avgScore = $totalRatedCount > 0 
            ? round(MaintenanceRating::where('rater_id', $user->id)->avg('score'), 1)
            : 0;

        $submissionRate = ($totalRatedCount + $pendingCount) > 0 
            ? round(($totalRatedCount / ($totalRatedCount + $pendingCount)) * 100, 1)
            : 100;

        Log::info("User ID {$user->id} viewed their evaluation list."); // บันทึก Log การเข้าดูรายการ

        return view('maintenance.rating.evaluate', [
            'pendingRequests' => $pendingRequests,
            'ratedRequests'   => $ratedRequests,
            'avgScore'        => $avgScore,
            'totalRatedCount' => $totalRatedCount,
            'pendingCount'    => $pendingCount,
            'submissionRate'  => $submissionRate,
        ]);
    }

    // Dashboard คะแนนของเจ้าหน้าที่ (รองรับ Sorting)
    public function technicianDashboard(Request $request)
    {
        $sort = $request->get('sort', 'impact_desc');

        $query = User::query()
            ->whereIn('role', User::workerRoles())
            ->where(function($q) {
                $q->whereHas('technicianAssignments')
                  ->orWhereHas('technicianRatings');
            })
            // Calculate Performance Metrics (Lifetime)
            ->withAvg('technicianRatings as technician_ratings_avg_score', 'score')
            ->withCount('technicianRatings as technician_ratings_count')
            ->withSum('technicianRatings as performance_score', 'score');

        // Sorting
        $query = match ($sort) {
            'score_desc' => $query->orderByDesc('technician_ratings_avg_score')
                                  ->orderByDesc('technician_ratings_count'),
            'count_desc' => $query->orderByDesc('technician_ratings_count'),
            'impact_desc' => $query->orderByDesc('performance_score'),
            default      => $query->orderByDesc('performance_score'),
        };

        // Generate global stats for the dashboard header
        $statsQuery = clone $query;
        $allActiveTechs = $statsQuery->get(['id', 'name', 'technician_ratings_avg_score', 'technician_ratings_count', 'performance_score', 'role']);
        
        $totalTech = $allActiveTechs->count();
        $globalAvg = round($allActiveTechs->avg('technician_ratings_avg_score'), 2);
        $totalReviews = $allActiveTechs->sum('technician_ratings_count');

        // Prepare Top 15 for the Chart (Fixed Top 15 regardless of table page)
        $top15 = $allActiveTechs->take(15);

        // Paginate the results to allow navigating through the full list of active technicians
        $limit = $request->get('limit', 15);
        $technicians = $query->paginate((int)$limit)->withQueryString();

        Log::info("Technician Dashboard viewed. Sorting by: {$sort}"); // บันทึก Log การเข้าดู Dashboard พร้อมค่าการเรียง

        return view('maintenance.rating.technicians-dashboard', [
            'technicians' => $technicians,
            'totalTech'   => $totalTech,
            'globalAvg'   => $globalAvg,
            'totalReviews' => $totalReviews,
            'selectedSort' => $sort, 
            'chartLabels' => $top15->pluck('name'),
            'chartAvg'    => $top15->pluck('technician_ratings_avg_score'),
            'chartCount'  => $top15->pluck('technician_ratings_count'),
        ]);
    }

    // ฟอร์มให้คะแนน
    public function create(MaintenanceRequest $maintenanceRequest)
    {
        /** @var User $user */
        $user = Auth::user();

        // ตรวจสอบสิทธิ์ก่อน Redirect
        if ($redirect = $this->guardRatingAccess($maintenanceRequest, $user)) {
            return $redirect;
        }

        // เปลี่ยนจาก Render form แยก เป็นการพาไปหน้า Show พร้อมเปิด Modal
        return redirect()->route('maintenance.requests.show', $maintenanceRequest)
            ->with('auto_rate', true);
    }

    // บันทึกคะแนน
    public function store(Request $request, MaintenanceRequest $maintenanceRequest)
    {
        /** @var User $user */
        $user = Auth::user();

        if ($redirect = $this->guardRatingAccess($maintenanceRequest, $user)) {
            Log::warning("Unauthorized or invalid rating attempt by User ID {$user->id} for Request ID {$maintenanceRequest->id}"); // บันทึก Log กรณีเข้าถึงไม่ถูกต้อง
            return $redirect;
        }

        $data = $this->validateRating($request);

        $technicianId = $this->resolveTechnicianIdForRating($maintenanceRequest);
        if (! $technicianId) {
            Log::error("Failed to store rating: No technician assigned for Request ID {$maintenanceRequest->id}"); // บันทึก Log กรณีไม่พบเจ้าหน้าที่
            return redirect()
                ->route('maintenance.requests.show', $maintenanceRequest)
                ->with('toast', [
                    'type'    => 'warning',
                    'message' => 'ยังไม่พบเจ้าหน้าที่ที่เกี่ยวข้องกับงานนี้ จึงยังให้คะแนนไม่ได้',
                ]);
        }

        MaintenanceRating::updateOrCreate(
            [
                'maintenance_request_id' => $maintenanceRequest->id,
                'rater_id'               => $user->id,
            ],
            [
                'technician_id'          => $technicianId,
                'score'                  => (int) $data['score'],
                'comment'                => $data['comment'] ?? null,
            ]
        );

        Log::info("Rating stored: User ID {$user->id} rated Technician ID {$technicianId} with score {$data['score']}");


        // Auto-close: หากงานยังอยู่สถานะ 'resolved' ให้ปิดงานอัตโนมัติเมื่อผู้แจ้งให้คะแนนแล้ว
        // (ถือว่าการให้คะแนนคือการยืนยันรับงานคืน)
        $maintenanceRequest->refresh();
        $autoClosed = false;
        if ($maintenanceRequest->status === MaintenanceRequest::STATUS_RESOLVED) {
            try {
                app(MaintenanceTransitionService::class)->applyTransition(
                    $maintenanceRequest,
                    ['status' => MaintenanceRequest::STATUS_CLOSED, 'note' => 'ปิดงานอัตโนมัติหลังผู้แจ้งให้คะแนนเรียบร้อย'],
                    $user->id
                );
                $autoClosed = true;
                Log::info("Auto-closed Request ID {$maintenanceRequest->id} after rating by User ID {$user->id}");
            } catch (\Throwable $e) {
                Log::warning("Auto-close failed for Request ID {$maintenanceRequest->id}: " . $e->getMessage());
            }
        }

        $redirect = redirect()
            ->route('maintenance.requests.show', $maintenanceRequest)
            ->with('toast', [
                'type'    => 'success',
                'message' => $autoClosed ? 'บันทึกคะแนนและปิดงานเรียบร้อยแล้ว' : 'บันทึกคะแนนเรียบร้อย',
            ]);

        if ($autoClosed) {
            $redirect = $redirect->with('show_post_close_modal', true);
        }

        return $redirect;
    }


    // ตรวจสอบสิทธิ์
    protected function guardRatingAccess(MaintenanceRequest $maintenanceRequest, User $user): ?RedirectResponse
    {
        // 1. อนุญาตให้ Admin หรือ Supervisor เข้าถึงได้เสมอ (เพื่อการตรวจสอบหรือช่วยประเมิน)
        $isAdminTeam = $user->isAdmin() || $user->isSupervisor();

        if (!$isAdminTeam) {
            // 2. ถ้าไม่ใช่ Admin ต้องเป็นผู้แจ้งเท่านั้น
            if (!$maintenanceRequest->reporter_id || (int) $maintenanceRequest->reporter_id !== (int) $user->id) {
                abort(403, 'คุณไม่มีสิทธิ์ให้คะแนนงานนี้ คุณต้องเป็นผู้แจ้งซ่อมจึงจะสามารถประเมินได้');
            }
        }

        // 3. ตรวจสอบสถานะ (ต้องเป็น Resolved หรือ Closed เท่านั้น)
        if (!in_array($maintenanceRequest->status, [MaintenanceRequest::STATUS_RESOLVED, MaintenanceRequest::STATUS_CLOSED], true)) {
            abort(403, 'สามารถให้คะแนนได้เฉพาะงานที่ดำเนินการเสร็จสิ้นหรือปิดงานเรียบร้อยแล้วเท่านั้น');
        }

        $alreadyRated = MaintenanceRating::query()
            ->where('maintenance_request_id', $maintenanceRequest->id)
            ->where('rater_id', $user->id)
            ->exists();

        if ($alreadyRated) {
            return redirect()
                ->route('maintenance.requests.show', $maintenanceRequest)
                ->with('toast', [
                    'type'    => 'info',
                    'message' => 'งานนี้มีการให้คะแนนไปแล้ว',
                ]);
        }

        if (! $this->withinRatingWindow($maintenanceRequest)) {
            return redirect()
                ->route('maintenance.requests.show', $maintenanceRequest)
                ->with('toast', [
                    'type'    => 'warning',
                    'message' => 'เลยระยะเวลาที่สามารถให้คะแนนงานนี้ได้แล้ว',
                ]);
        }

        if (! $this->resolveTechnicianIdForRating($maintenanceRequest)) {
            return redirect()
                ->route('maintenance.requests.show', $maintenanceRequest)
                ->with('toast', [
                    'type'    => 'warning',
                    'message' => 'ยังไม่มีการมอบหมายเจ้าหน้าที่ในงานนี้ จึงยังให้คะแนนไม่ได้',
                ]);
        }

        return null;
    }

    // การตรวจสอบความถูกต้องของข้อมูล (Validation)
    protected function validateRating(Request $request): array
    {
        return Validator::make($request->all(), $this->ratingRules())
            ->after(fn ($v) => $this->requireCommentForLowScore($v))
            ->validate();
    }

    public function summary(User $user)
    {
        // โหลดข้อมูลพื้นฐานและสถิติ
        $user->loadCount(['technicianRatings', 'technicianAssignments' => function($q) {
            $q->where('status', 'done');
        }])
        ->loadAvg('technicianRatings', 'score');

        // ข้อมูลสถิติการกระจายคะแนน (Score Distribution)
        $scoreDistribution = $user->technicianRatings()
            ->selectRaw('score, count(*) as count')
            ->groupBy('score')
            ->pluck('count', 'score')
            ->toArray();

        // ทั้งหมด 5-1 ดาว
        $distribution = [];
        for ($i = 5; $i >= 1; $i--) {
            $count = $scoreDistribution[$i] ?? 0;
            $percent = $user->technician_ratings_count > 0 
                ? ($count / $user->technician_ratings_count) * 100 
                : 0;
            $distribution[$i] = [
                'count' => $count,
                'percent' => $percent
            ];
        }

        // ความคิดเห็นล่าสุด (จำกัดเพียง 6 รายการ)
        $reviews = $user->technicianRatings()
            ->with('rater:id,name,role')
            ->latest()
            ->take(6)
            ->get();

        // งานล่าสุดที่ทำเสร็จ
        $recentJobs = $user->technicianAssignments()
            ->with(['maintenanceRequest' => function($q) {
                $q->with('reporter:id,name');
            }])
            ->whereHas('maintenanceRequest', function ($q) {
                $q->where('status', \App\Models\MaintenanceRequest::STATUS_CLOSED);
            })
            ->where('status', 'done')
            ->latest() // ใช้ created_at แทน completed_at ที่ไม่มีในตาราง
            ->take(5)
            ->get();

        if (request()->wantsJson()) {
            return response()->json([
                'id'          => $user->id,
                'name'        => $user->name,
                'avatar_url'  => $user->avatar_thumb_url,
                'role_label'  => $user->role_label,
                'avg_score'   => round((float) $user->technician_ratings_avg_score, 2),
                'total_count' => (int) $user->technician_ratings_count,
                'reviews'     => $reviews->map(fn($r) => [
                    'score'      => $r->score,
                    'comment'    => $r->comment,
                    'created_at' => $r->created_at->format('d M Y'),
                    'rater'      => $r->rater?->name ?? 'ไม่ระบุชื่อ',
                ]),
            ]);
        }

        return view('maintenance.rating.technician-show', [
            'tech' => $user,
            'distribution' => $distribution,
            'reviews' => $reviews,
            'recentJobs' => $recentJobs
        ]);
    }
}

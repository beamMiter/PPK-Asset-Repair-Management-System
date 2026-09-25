<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
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

    /** A job with this many days or fewer left to rate is "about to run out" — the list's amber / red labels and its banner. */
    public const EXPIRING_DAYS = 7;

    /** Rows per page on "ประเมินความพึงพอใจ" — the same as the asset list. */
    private const EVALUATE_PER_PAGE = 20;

    /** The reporter's closed jobs. */
    private function reporterClosedQuery(User $user)
    {
        return MaintenanceRequest::query()
            ->where('reporter_id', $user->id)
            ->where('status', MaintenanceRequest::STATUS_CLOSED);
    }

    /** The reporter's closed jobs that can still be rated: the same set withinRatingWindow() accepts. */
    private function pendingRatingQuery(User $user)
    {
        $limitDate = now()->subDays($this->ratingDeadlineDays);

        return $this->reporterClosedQuery($user)
            ->whereDoesntHave('rating', fn ($rating) => $rating->where('rater_id', $user->id))
            // Match withinRatingWindow() exactly: the *first* of closed_at / resolved_at / completed_date, in the past, within
            // the deadline. An OR across the three columns used to surface rows the rating guard then rejected with
            // "เลยระยะเวลา".
            ->whereRaw('COALESCE(closed_at, resolved_at, completed_date) BETWEEN ? AND ?', [$limitDate, now()]);
    }

    /** Narrow a list to what the search box holds: the job's number, title or place, or its technician's name. */
    private function searchedFor($query, string $term)
    {
        if ($term === '') {
            return $query;
        }

        // `%` and `_` typed into the box are text, not wildcards
        $like = '%' . addcslashes($term, '\\%_') . '%';

        return $query->where(function ($where) use ($like) {
            $where->where('request_no', 'like', $like)
                ->orWhere('title', 'like', $like)
                ->orWhere('location_text', 'like', $like)
                ->orWhereHas('technician', fn ($tech) => $tech->where('name', 'like', $like));
        });
    }

    /** A query-string value as text: a stray `?q[]=x` is "nothing", not an error. */
    private function textParam(Request $request, string $key, int $max = 100): string
    {
        $value = $request->query($key);

        return is_string($value) ? mb_substr(trim($value), 0, $max) : '';
    }

    public function evaluateList(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // One list at a time (a tab), so it stays usable however many jobs there are: search, one filter, 20 to a page.
        $tab = $this->textParam($request, 'tab') === 'rated' ? 'rated' : 'pending';
        $term = $this->textParam($request, 'q');
        $score = in_array($this->textParam($request, 'score'), ['1', '2', '3', '4', '5'], true) ? (int) $request->query('score') : null;
        $soon = $this->textParam($request, 'urgency') === 'soon';

        if ($tab === 'pending') {
            $query = $this->pendingRatingQuery($user)->with(['technician:id,name']);

            if ($soon) {
                $query->whereRaw(
                    'COALESCE(closed_at, resolved_at, completed_date) <= ?',
                    [now()->subDays($this->ratingDeadlineDays - self::EXPIRING_DAYS)]
                );
            }

            // the one that runs out of time first comes first
            $requests = $this->searchedFor($query, $term)
                ->orderByRaw('COALESCE(closed_at, resolved_at, completed_date) asc')
                ->orderBy('id')
                ->paginate(self::EVALUATE_PER_PAGE)
                ->withQueryString();

            $requests->getCollection()->each(
                fn (MaintenanceRequest $r) => $r->setAttribute('rating_days_left', $this->ratingDaysLeft($r))
            );
        } else {
            // the reporter's OWN rating: one an admin gave to the same job is not theirs to see here, nor does it hide the job from
            // the waiting list
            $query = $this->reporterClosedQuery($user)
                ->with(['technician:id,name', 'rating' => fn ($rating) => $rating->where('rater_id', $user->id)])
                ->whereHas('rating', fn ($rating) => $rating->where('rater_id', $user->id)->when($score, fn ($q) => $q->where('score', $score)));

            // newest rating first
            $requests = $this->searchedFor($query, $term)
                ->orderByDesc(
                    MaintenanceRating::select('created_at')
                        ->whereColumn('maintenance_request_id', 'maintenance_requests.id')
                        ->where('rater_id', $user->id)
                        ->latest()
                        ->limit(1)
                )
                ->paginate(self::EVALUATE_PER_PAGE)
                ->withQueryString();
        }

        // The header and the tabs count everything, whatever the search or the filter has narrowed the list to.
        $pendingCount = $this->pendingRatingQuery($user)->count();
        $totalRatedCount = $this->reporterClosedQuery($user)
            ->whereHas('rating', fn ($rating) => $rating->where('rater_id', $user->id))
            ->count();

        // Jobs that are about to run out of time, over the whole list.
        $expiringCount = $this->pendingRatingQuery($user)
            ->whereRaw(
                'COALESCE(closed_at, resolved_at, completed_date) <= ?',
                [now()->subDays($this->ratingDeadlineDays - self::EXPIRING_DAYS)]
            )
            ->count();

        // ดึงคะแนนเฉลี่ยที่ผู้ใช้คนนี้เคยให้ (คำนวณจากสถิติจริง ไม่ใช่แค่ในหน้า)
        $avgScore = $totalRatedCount > 0
            ? round(MaintenanceRating::where('rater_id', $user->id)->avg('score'), 1)
            : 0;

        $submissionRate = ($totalRatedCount + $pendingCount) > 0
            ? round(($totalRatedCount / ($totalRatedCount + $pendingCount)) * 100, 1)
            : 100;

        Log::info("User ID {$user->id} viewed their evaluation list."); // บันทึก Log การเข้าดูรายการ

        return view('maintenance.rating.evaluate', [
            'tab'             => $tab,
            'requests'        => $requests,
            'filters'         => ['q' => $term, 'score' => $score, 'urgency' => $soon ? 'soon' : null],
            'filtered'        => $term !== '' || ($tab === 'rated' && $score !== null) || ($tab === 'pending' && $soon),
            'avgScore'        => $avgScore,
            'totalRatedCount' => $totalRatedCount,
            'pendingCount'    => $pendingCount,
            'expiringCount'   => $expiringCount,
            'expiringDays'    => self::EXPIRING_DAYS,
            'deadlineDays'    => $this->ratingDeadlineDays,
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
        $limit = max(1, min($request->integer('limit', 15), 100)); // ?limit=-1 used to be `LIMIT -1`: a SQL error
        $technicians = $query->paginate($limit)->withQueryString();

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
        // 1. เฉพาะ "ผู้แจ้งซ่อม" ของใบงานนี้เท่านั้นที่ประเมินได้ — ทุกบทบาท รวมถึง Admin / Supervisor. The rate button, the policy and the
        //    API already said so; this door let admins and supervisors in too, and their stars were counted in a technician's average
        //    beside the reporter's (a rating is the reporter's word on the service they received).
        if (! $maintenanceRequest->reporter_id || (int) $maintenanceRequest->reporter_id !== (int) $user->id) {
            abort(403, 'คุณไม่มีสิทธิ์ให้คะแนนงานนี้ คุณต้องเป็นผู้แจ้งซ่อมจึงจะสามารถประเมินได้');
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

        // 1–2 stars: the ratings that had to carry a comment
        $lowCount = (int) ($scoreDistribution[1] ?? 0) + (int) ($scoreDistribution[2] ?? 0);

        // Last six months, oldest first. A month nobody rated in is still listed, so a gap reads as a gap.
        $months = collect(range(5, 0))->map(fn (int $ago) => now()->startOfMonth()->subMonths($ago));
        $byMonth = $user->technicianRatings()
            ->where('created_at', '>=', $months->first())
            ->get(['score', 'created_at'])
            ->groupBy(fn ($rating) => $rating->created_at->format('Y-m'));
        $trend = $months->map(function ($month) use ($byMonth) {
            $rows = $byMonth->get($month->format('Y-m'), collect());

            return [
                'month' => $month,
                'count' => $rows->count(),
                'avg'   => $rows->isNotEmpty() ? round((float) $rows->avg('score'), 2) : null,
            ];
        });

        // ความคิดเห็นล่าสุด — only ratings that HAVE a comment (a card that says "no comment" tells the reader nothing), newest
        // first, six of them. The person is drawn with their own avatar (photo, or the system's initials default), so those columns
        // are loaded with the name.
        $comments = $user->technicianRatings()
            ->whereNotNull('comment')
            ->where('comment', '!=', '')
            ->with(['rater:id,name,role,profile_photo_path,profile_photo_thumb', 'request:id,request_no,title'])
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

        // what each of those jobs was rated
        $jobScores = MaintenanceRating::query()
            ->where('technician_id', $user->id)
            ->whereIn('maintenance_request_id', $recentJobs->pluck('maintenance_request_id'))
            ->pluck('score', 'maintenance_request_id');

        if (request()->wantsJson()) {
            // (unchanged) the latest six ratings, with or without a comment
            $reviews = $user->technicianRatings()->with('rater:id,name,role')->latest()->take(6)->get();

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
            'lowCount' => $lowCount,
            'trend' => $trend,
            'repairTime' => $this->averageRepairTime($user),
            'comments' => $comments,
            'recentJobs' => $recentJobs,
            'jobScores' => $jobScores,
        ]);
    }

    /**
     * How long this person's finished jobs took from start to "repaired", on average — over their latest 200, so a long career
     * does not make the page slow. Null when no finished job has both times.
     *
     * @return array{minutes: int, jobs: int}|null
     */
    private function averageRepairTime(User $user): ?array
    {
        $jobs = MaintenanceRequest::query()
            ->whereHas('assignments', fn ($q) => $q->where('user_id', $user->id)->where('status', 'done'))
            ->whereNotNull('started_at')
            ->whereNotNull('resolved_at')
            ->latest('resolved_at')
            ->limit(200)
            ->get(['id', 'started_at', 'resolved_at']);

        if ($jobs->isEmpty()) {
            return null;
        }

        $minutes = $jobs->avg(fn ($job) => max(0.0, $job->started_at->diffInMinutes($job->resolved_at, true)));

        return ['minutes' => (int) round($minutes), 'jobs' => $jobs->count()];
    }
}

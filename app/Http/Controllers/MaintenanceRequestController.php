<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRequest as MR;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceLog;
use App\Events\MaintenanceRequestCreated;
use App\Models\MaintenanceRating;
use App\Models\MaintenanceOperationLog;
use App\Models\MaintenanceAttachment;
use App\Models\MaintenanceDepartment;
use App\Models\MaintenanceRequestType;
use App\Models\OperationLog;
use App\Models\Attachment;
use App\Models\Department;
use App\Models\Asset;
use App\Models\User;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Auth\Access\AuthorizationException;
use Carbon\Carbon;
use App\Support\Toast;

class MaintenanceRequestController extends Controller
{
    use \App\Traits\ApiResponseWithToast;

    public function create()
    {
        return $this->createPage();
        
    }

    public function indexPage(Request $request)
    {
        $user     = Auth::user();
        $userId   = (int) Auth::id();

        $status   = strtolower(trim($request->string('status')->toString()));
        $status   = strtolower(trim($request->string('status')->toString()));
        $q        = trim($request->string('q')->toString());
        $assetId  = $request->integer('asset_id');

        // NEW: type filter
        $typeId = $request->input('type_id'); // อาจเป็น null, '__null__', หรือ id

        // ---- ใช้ helper ดึงค่าการเรียง + จัดการ session ต่อ user ----
        [$sortBy, $sortDir] = $this->resolveSort($request);

        // NEW: dropdown options (Maintenance Types)
        $types = Cache::remember('maintenance_request_types', 3600, function () {
            return \App\Models\MaintenanceRequestType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']);
        });

        $query = MR::query()

            // เพิ่ม join เพื่อเอา my_response_status มาใช้บนหน้า index (เหมือน myJobsPage)
            ->leftJoin('maintenance_assignments as ma', function ($join) use ($userId) {
                $join->on('ma.maintenance_request_id', '=', 'maintenance_requests.id')
                    ->where('ma.user_id', '=', $userId);
            })

            // ต้อง select เองเพราะมี join
            ->select([
                'maintenance_requests.*',
                DB::raw('ma.response_status as my_response_status'),
                DB::raw('ma.responded_at as my_responded_at'),
            ])

            // NEW: eager load type relation
            ->with([
                'type', // <<<< สำคัญ
                'asset',
                'reporter:id,name,email',
                'technician:id,name',
                'attachments' => fn($qq) => $qq
                    ->select('id', 'attachable_id', 'attachable_type', 'file_id', 'original_name', 'is_private', 'order_column')
                    ->with(['file:id,path,disk,mime,size']),
            ])

            // จำกัดเฉพาะผู้ใช้ระดับ Member ให้เห็นงานที่ตนแจ้งเท่านั้น
            ->when(
                ($user && !$user->isAdmin() && !$user->isSupervisor() && !$user->isTechnician()),
                fn($qb) => $qb->where('maintenance_requests.reporter_id', $user->id)
            )

            // filter อื่น ๆ
            ->when($assetId, fn($qb) => $qb->where('maintenance_requests.asset_id', $assetId))
            ->when(filled($status), fn($qb) => $qb->where('maintenance_requests.status', $status))
            ->when(filled($q), fn($qb) => $qb->search($q))

            // NEW: filter type_id
            ->when(filled($typeId), function ($qb) use ($typeId) {
                if ($typeId === '__null__') {
                    $qb->whereNull('maintenance_requests.type_id');
                } else {
                    $qb->where('maintenance_requests.type_id', (int) $typeId);
                }
            });

        // ---- Sorting Logic ----
        $dir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'request_no') {
            // 1) เอา request_no ว่าง/NULL ไปท้ายเสมอ
            $query->orderByRaw("CASE WHEN maintenance_requests.request_no IS NULL OR maintenance_requests.request_no = '' THEN 1 ELSE 0 END ASC");
            // 2) เรียงตาม request_no
            $query->orderBy('maintenance_requests.request_no', $dir);
            // 3) tie-breaker
            $query->orderBy('maintenance_requests.id', $dir);
        } else {
            $allowed = ['id', 'request_date', 'status', 'updated_at', 'created_at', 'title'];
            if (!in_array($sortBy, $allowed, true)) {
                $sortBy = 'id';
            }
            $query->orderBy('maintenance_requests.' . $sortBy, $dir);
            
            // Add ID tie-breaker if not already sorting by ID
            if ($sortBy !== 'id') {
                $query->orderBy('maintenance_requests.id', $dir);
            }
        }

        // Add logging to debug why filters return 0 rows in production
        \Illuminate\Support\Facades\Log::info('MaintenanceRequests Search Query', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
            // 'count' removed to optimize loading speed
            'q' => $q,
            'status' => $status,
            'user' => $user?->email,
        ]);

        $list = $query
            ->paginate(20)

            ->withQueryString();

        return view('maintenance.requests.index', compact(
            'list',
            'types',
            'typeId',
            'status',
            'q',
            'sortBy',
            'sortDir'
        ));
    }

    public function showPage(MR $req)
    {
        Gate::authorize('view', $req);

        $viewer = Auth::user();
        Log::info('Viewed maintenance request', [
            'request_id' => $req->id,
            'user_id'    => $viewer?->id,
            'ip_address' => request()->ip(),
        ]);

        $req->loadMissing([
            'type',
            'asset',
            'department',
            'reporter:id,name,email',
            'technician:id,name',
            'assignments' => function ($query) {
                $query->where('status', '!=', \App\Models\MaintenanceAssignment::STATUS_CANCELLED)
                    ->with('user:id,name,role,profile_photo_path,profile_photo_thumb');
            },
            'attachments' => fn($q) => $q->with('file'),
            'logs.user:id,name',
            'rating',
            'rating.rater:id,name',
            'operationLog.user:id,name',
        ]);

        $techUsers = $this->suggestTechUsersForRequest($req);

        $types = Cache::remember('maintenance_request_types', 3600, function () {
            return MaintenanceRequestType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']);
        });

        $suggestRole = strtolower(trim((string) $req->type?->default_role_code));

        return view('maintenance.requests.show', [
            'req'         => $req,
            'techUsers'   => $techUsers,
            'types'       => $types,
            'suggestRole' => $suggestRole,
        ]);
    }

    public function createPage()
    {
        $assets = \App\Models\Asset::orderBy('asset_code')->get(['id', 'asset_code', 'name']);
        $users  = \App\Models\User::orderBy('name')->get(['id', 'name']);
        $depts  = \App\Models\Department::orderBy('name_th')->get(['id', 'code', 'name_th', 'name_en']);

        $types = Cache::remember('maintenance_request_types', 3600, function () {
            return \App\Models\MaintenanceRequestType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']);
        });

        return view('maintenance.requests.create', compact('assets', 'users', 'depts', 'types'));
    }

    public function index(Request $request)
    {
        $status   = $request->string('status')->toString();
        $q        = trim($request->string('q')->toString());
        $assetId  = $request->integer('asset_id');

        $user = $request->user();

        [$sortBy, $sortDir] = $this->resolveSort($request);

        $query = MR::query()
            ->with(['asset', 'reporter:id,name,email', 'technician:id,name'])

            // API/Web: บังคับ filter เหมือนกัน สำหรับ Member เท่านั้น
            ->when(
                ($user && !$user->isAdmin() && !$user->isSupervisor() && !$user->isTechnician()),
                fn($qb) => $qb->where('reporter_id', $user->id)
            )

            ->when($assetId, fn($qb) => $qb->where('asset_id', $assetId))
            ->when($status, fn($qb) => $qb->where('status', $status))

            ->when($q !== '', fn($qb) => $qb->search($q));

        // ---- Sorting Logic ----
        $dir = strtolower($sortDir) === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'request_no') {
            $query->orderByRaw("CASE WHEN request_no IS NULL OR request_no = '' THEN 1 ELSE 0 END ASC");
            $query->orderBy('request_no', $dir);
            $query->orderBy('id', $dir);
        } else {
            $allowed = ['id', 'request_date', 'status', 'updated_at', 'created_at'];
            if (!in_array($sortBy, $allowed, true)) {
                $sortBy = 'id';
            }
            $query->orderBy($sortBy, $dir);

            if ($sortBy !== 'id') {
                $query->orderBy('id', $dir);
            }
        }

        $list = $query
            ->paginate(20)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $list->items(),
                'meta' => [
                    'current_page' => $list->currentPage(),
                    'per_page'     => $list->perPage(),
                    'total'        => $list->total(),
                    'last_page'    => $list->lastPage(),
                ],
                'toast' => [
                    'type' => 'info',
                    'message' => 'โหลดรายการคำขอบำรุงรักษาแล้ว',
                    'position' => 'tc',
                    'timeout' => 1200,
                    'size' => 'sm',
                ],
            ]);
        }

        return view('maintenance.requests.index', compact('list', 'status', 'q', 'sortBy', 'sortDir'));
    }

    /**
     * API: รายละเอียดใบงานซ่อม (REST: GET /api/repair-requests/{req})
     */
    public function show(Request $request, MR $req)
    {
        Gate::authorize('view', $req);

        $req->loadMissing([
            'type',
            'asset',
            'department',
            'reporter:id,name,email',
            'technician:id,name',
            'assignments' => function ($query) {
                $query->where('status', '!=', \App\Models\MaintenanceAssignment::STATUS_CANCELLED)
                    ->with('user:id,name,role,profile_photo_path,profile_photo_thumb');
            },
            'attachments.file',
            'logs.user:id,name',
            'rating',
            'rating.rater:id,name',
            'operationLog.user:id,name',
        ]);

        return response()->json([
            'data' => $req,
        ]);
    }

    public function store(Request $request, \App\Services\MaintenanceRequestService $service)
    {
        $maxKb        = (int) config('uploads.max_kb', 10240);
        $allowedMimes = config('uploads.mimes', ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'pdf']);

        $rules = [
            'title'          => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string', 'max:5000'],
            'asset_id'       => ['nullable', 'integer', 'exists:assets,id'],
            'department_id'  => ['nullable', 'integer', 'exists:departments,id'],
            'type_id'        => ['nullable', 'integer', 'exists:maintenance_request_types,id'],
            'location_text'  => ['nullable', 'string', 'max:255'],
            'reporter_name'  => ['nullable', 'string', 'max:255'],
            'reporter_phone' => ['nullable', 'string', 'max:30'],
            'reporter_email' => ['nullable', 'email', 'max:255'],
            'files'          => ['nullable', 'array', 'max:3'],
            'files.*'        => ['file', "max:{$maxKb}", 'mimes:' . implode(',', $allowedMimes)],
            'captions'       => ['nullable', 'array'],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('toast', Toast::warning($validator->errors()->first(), 3000));
        }

        try {
            $req = $service->createRequest(
                $validator->validated(),
                $request->user(),
                $request->file('files') ?? [],
                $request->input('captions') ?? []
            );

            $req->load(['type', 'asset', 'attachments.file']);

            if ($request->expectsJson()) {
                return response()->json([
                    'data'  => $req,
                    'toast' => Toast::success('สร้างคำขอเรียบร้อย', 1800),
                ], 201);
            }

            return redirect()->route('maintenance.requests.show', $req)
                ->with('toast', Toast::success('สร้างคำขอเรียบร้อย', 1800));

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return redirect()->back()
                ->withInput()
                ->with('toast', Toast::warning($e->getMessage(), 3000));
        }
    }

    public function update(Request $request, MR $req, \App\Services\MaintenanceRequestService $service)
    {
        Gate::authorize('update', $req);

        $user    = $request->user();
        $isTeam  = $user && ($user->isAdmin() || $user->isSupervisor() || $user->isTechnician());

        $maxKb     = config('uploads.max_kb', 10240);
        $mimetypes = implode(',', config('uploads.mimes', ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'pdf']));
        $fileRules = ['file', "max:{$maxKb}", 'mimes:' . $mimetypes];

        $rules = [
            'title'           => ['sometimes', 'required', 'string', 'max:255'],
            'description'     => ['nullable', 'string', 'max:5000'],
            'asset_id'        => ['nullable', 'integer', 'exists:assets,id'],
            'request_date'    => ['nullable', 'date'],
            'reporter_name'   => ['nullable', 'string', 'max:255'],
            'reporter_phone'  => ['nullable', 'string', 'max:30'],
            'reporter_email'  => ['nullable', 'email', 'max:255'],
            'department_id'   => ['nullable', 'integer', 'exists:departments,id'],
            'type_id'         => ['nullable', 'integer', 'exists:maintenance_request_types,id'],
            'location_text'   => ['nullable', 'string', 'max:255'],
            'resolution_note' => ['nullable', 'string', 'max:5000'],
            'cost'            => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'files.*'         => $fileRules,
            'technician_id'   => array_values(array_filter([
                'bail',
                \Illuminate\Validation\Rule::prohibitedIf(!$isTeam),
                'nullable',
                'integer',
                'exists:users,id',
            ])),
            'status' => $isTeam
                ? ['nullable', \Illuminate\Validation\Rule::in(['pending', 'acknowledged', 'accepted', 'in_progress', 'on_hold', 'resolved', 'closed', 'cancelled', 'rejected'])]
                : ['nullable', \Illuminate\Validation\Rule::in(['cancelled'])],
            'operation_date'   => ['nullable', 'date'],
            'operation_method' => ['nullable', \Illuminate\Validation\Rule::in(['requisition', 'service_fee', 'other'])],
            'property_code'    => ['nullable', 'string', 'max:100'],
            'require_precheck' => ['nullable', 'boolean'],
            'remark'           => ['nullable', 'string', 'max:5000'],
            'issue_software'   => ['nullable', 'boolean'],
            'issue_hardware'   => ['nullable', 'boolean'],
            'user_ids'         => ['nullable', 'array'],
            'user_ids.*'       => ['integer', 'exists:users,id'],
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('toast', \App\Support\Toast::warning($validator->errors()->first(), 3000));
        }

        try {
            $req = $service->updateRequest(
                $req,
                $validator->validated(),
                $user,
                $request->file('files') ?? [],
                $request->input('captions') ?? [],
                $request->input('remove_attachments') ?? []
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'data'  => $req,
                    'toast' => \App\Support\Toast::success('อัปเดตคำขอเรียบร้อย', 1600),
                ]);
            }

            return redirect()->route('maintenance.requests.show', $req)
                ->with('toast', \App\Support\Toast::success('อัปเดตคำขอเรียบร้อย', 1600));

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            return redirect()->back()
                ->withInput()
                ->with('toast', \App\Support\Toast::warning($e->getMessage(), 3000));
        }
    }

    // public function queuePage(Request $request)
    // {
    //     \\Gate::authorize('view-repair-dashboard');
    //     $status = (string) $request->string('status');
    //     $q      = (string) $request->string('q');
    //     $just   = (int) $request->query('just');

    //     $base = MR::query()
    //         ->with(['asset','reporter:id,name,email','technician:id,name'])
    //         ->whereIn('status', ['pending','accepted','in_progress','on_hold']);

    //     $list = (clone $base)
    //         ->when($status, fn($qb) => $qb->where('status', $status))
    //         ->when($q, function ($qb) use ($q) {
    //             $qb->where(function ($w) use ($q) {
    //                 $w->where('title','like',"%{$q}%")
    //                     ->orWhere('description','like',"%{$q}%")
    //                     ->orWhere('request_no','like',"%{$q}%")
    //                     ->orWhereHas('reporter', fn($qr) => $qr->where('name','like',"%{$q}%")->orWhere('email','like',"%{$q}%"))
    //                     ->orWhereHas('asset', fn($qa) => $qa->where('name','like',"%{$q}%")->orWhere('asset_code','like',"%{$q}%"));
    //             });
    //         })
    //         ->orderByRaw("FIELD(priority,'urgent','high','medium','low')")
    //         ->orderByDesc('request_date')
    //         ->paginate(20)
    //         ->withQueryString();

    //     $stats = [
    //         'total'       => (clone $base)->count(),
    //         'pending'     => (clone $base)->where('status','pending')->count(),
    //         'in_progress' => (clone $base)->where('status','in_progress')->count(),
    //         'completed'   => MR::query()->whereIn('status', ['resolved','closed'])->count(),
    //     ];

    //     return view('repair.queue', compact('list','stats','just'));
    // }

    // public function myJobsPage(Request $request)
    // {
    //     if (Auth::user()?->role === 'member') {
    //         abort(403, 'Unauthorized Access: Members cannot access the My Jobs page.');
    //     }

    //     $userId = (int) Auth::id();

    //     $filter = $request->string('filter')->toString(); // my | available | all
    //     $status = strtolower($request->string('status')->toString()); // filter by MR.status
    //     $tech   = $request->integer('tech');
    //     $q      = $request->string('q')->toString();
    //     $resp   = strtolower($request->string('resp')->toString()); // ma.response_status

    //     if ($filter === '') $filter = 'all';

    //     // // สถานะที่ "ไม่ให้แสดงใน list"
    //     $excludedList = [
    //         MR::STATUS_CLOSED,
    //         MR::STATUS_CANCELLED, MR::STATUS_REJECTED,
    //     ];

    //     // // whitelist: สถานะใบงานจริง (เพิ่ม acknowledged)
    //     $validReqStatus = [
    //         MR::STATUS_PENDING,
    //         MR::STATUS_ACKNOWLEDGED,
    //         MR::STATUS_ACCEPTED,
    //         MR::STATUS_IN_PROGRESS,
    //         MR::STATUS_ON_HOLD,
    //         MR::STATUS_RESOLVED,
    //         MR::STATUS_CLOSED,
    //         MR::STATUS_CANCELLED, MR::STATUS_REJECTED,
    //     ];

    //     if ($status !== '' && !in_array($status, $validReqStatus, true)) {
    //         $status = '';
    //     }

    //     // // whitelist: response_status ของฉัน
    //     $validResp = [
    //         MaintenanceAssignment::RESP_PENDING,
    //         MaintenanceAssignment::RESP_ACCEPTED,
    //         MaintenanceAssignment::RESP_ACKNOWLEDGED,
    //         MaintenanceAssignment::RESP_REJECTED,
    //     ];

    //     if ($resp !== '' && !in_array($resp, $validResp, true)) {
    //         $resp = '';
    //     }

    //     // // base query (ใช้ร่วมกับ list และ stats)
    //     // // join assignment ของ user คนนี้เท่านั้น
    //     $base = MR::query()
    //         ->from('maintenance_requests')
    //         ->leftJoin('maintenance_assignments as ma', function ($join) use ($userId) {
    //             $join->on('ma.maintenance_request_id', '=', 'maintenance_requests.id')
    //                 ->where('ma.user_id', '=', $userId);
    //         });

    //     // // LIST QUERY
    //     $query = (clone $base)
    //         ->select([
    //             'maintenance_requests.id',
    //             'maintenance_requests.request_no',
    //             'maintenance_requests.request_date',
    //             'maintenance_requests.title',
    //             'maintenance_requests.description',
    //             'maintenance_requests.status',
    //             'maintenance_requests.priority',
    //             'maintenance_requests.updated_at',
    //             'maintenance_requests.created_at',
    //             'maintenance_requests.asset_id',
    //             'maintenance_requests.department_id',
    //             'maintenance_requests.location_text',
    //             'maintenance_requests.reporter_id',
    //             'maintenance_requests.reporter_name',
    //             'maintenance_requests.reporter_phone',
    //             'maintenance_requests.technician_id',

    //             DB::raw('ma.response_status as my_response_status'),
    //             DB::raw('ma.responded_at as my_responded_at'),
    //             DB::raw('ma.status as my_assignment_status'),
    //         ])
    //         ->with([
    //             'asset:id,name,asset_code',
    //             'department',
    //             'reporter:id,name,email',
    //             'technician:id,name',
    //         ])
    //         ->when($filter === 'my', function ($qb) use ($userId) {
    //             $qb->where(function ($qq) use ($userId) {
    //                 $qq->where('maintenance_requests.technician_id', $userId)
    //                     ->orWhereNotNull('ma.maintenance_request_id');
    //             });
    //         })
    //         ->when($filter === 'available', function ($qb) {
    //             // // งานที่ "พร้อมรับเรื่อง" จริง = acknowledged + ยังไม่มีช่าง
    //             $qb->whereNull('maintenance_requests.technician_id')
    //                 ->where('maintenance_requests.status', MR::STATUS_ACKNOWLEDGED);
    //         })
    //         ->when(!empty($tech), fn($qb) => $qb->where('maintenance_requests.technician_id', $tech))
    //         ->when($status !== '', fn($qb) => $qb->where('maintenance_requests.status', $status))
    //         ->when($q !== '', fn($qb) => $qb->search($q))
    //         ->when($status === '', fn($qb) => $qb->whereNotIn('maintenance_requests.status', $excludedList))
    //         ->when($resp !== '', function ($qb) use ($resp) {
    //             if ($resp === MaintenanceAssignment::RESP_PENDING) {
    //                 $qb->where(function ($qq) use ($resp) {
    //                     $qq->whereNull('ma.response_status')
    //                         ->orWhere('ma.response_status', $resp);
    //                 });
    //             } else {
    //                 $qb->where('ma.response_status', $resp);
    //             }
    //         })
    //         ->orderByDesc('maintenance_requests.updated_at');

    //     $jobs = $query->paginate(15)->withQueryString();

    //     // // STATS
    //     $statsRow = (clone $base)
    //         ->selectRaw("
    //             SUM(CASE WHEN maintenance_requests.status = 'pending' THEN 1 ELSE 0 END) as pending,
    //             SUM(CASE WHEN maintenance_requests.status IN ('accepted','in_progress') THEN 1 ELSE 0 END) as in_progress,
    //             SUM(CASE WHEN maintenance_requests.status IN ('resolved','closed') THEN 1 ELSE 0 END) as completed
    //         ")
    //         ->when($filter === 'my', function ($qb) use ($userId) {
    //             $qb->where(function ($qq) use ($userId) {
    //                 $qq->where('maintenance_requests.technician_id', $userId)
    //                     ->orWhereNotNull('ma.maintenance_request_id');
    //             });
    //         })
    //         ->when($filter === 'available', function ($qb) {
    //             $qb->whereNull('maintenance_requests.technician_id')
    //                 ->where('maintenance_requests.status', MR::STATUS_ACKNOWLEDGED);
    //         })
    //         ->when(!empty($tech), fn($qb) => $qb->where('maintenance_requests.technician_id', $tech))
    //         ->when($q !== '', fn($qb) => $qb->search($q))
    //         ->first();

    //     $stats = [
    //         'pending'     => (int)($statsRow->pending ?? 0),
    //         'in_progress' => (int)($statsRow->in_progress ?? 0),
    //         'completed'   => (int)($statsRow->completed ?? 0),
    //     ];

    //     // // Log สำหรับการตรวจสอบการเข้าถึงหน้า Job List
    //     Log::debug('[MaintenanceRequest::myJobsPage] user access', [
    //         'user_id' => $userId,
    //         'filter'  => $filter,
    //         'status'  => $status,
    //         'total'   => $jobs->total(),
    //     ]);

    //     return view('repair.my-jobs', [
    //         'list'   => $jobs,
    //         'filter' => $filter,
    //         'status' => $status,
    //         'tech'   => $tech,
    //         'q'      => $q,
    //         'resp'   => $resp,
    //         'stats'  => $stats,
    //     ]);
    // }
    /**
     * API: ลบใบงานซ่อม (REST: DELETE /api/repair-requests/{req})
     */
    public function destroy(Request $request, MR $req)
    {
        Gate::authorize('delete', $req);

        $actorId = (int) Auth::id();

        DB::transaction(function () use ($req, $actorId) {
            $id = $req->id;

            $req->delete();

            MaintenanceLog::create([
                'request_id' => $id,
                'user_id'    => $actorId ?: null,
                'action'     => 'delete_request',
                'note'       => 'ลบใบงานซ่อม (soft delete) ผ่าน API',
            ]);
        });

        if (!$request->expectsJson()) {
            return redirect()
                ->route('maintenance.requests.index')
                ->with('toast', Toast::success('ลบใบงานเรียบร้อย', 1600));
        }

        return response()->json([
            'deleted' => true,
            'toast' => Toast::success('ลบใบงานเรียบร้อย', 1600),
        ], Response::HTTP_OK);
    }
    public function edit($id)
    {
        // โหลดข้อมูลหลักพร้อม Relations ที่จำเป็นสำหรับหน้าแก้ไข
        $mr = MR::with([
            'asset',
            'reporter',
            'attachments.file',
            'operationLog',
        ])->findOrFail($id);
    
        Gate::authorize('update', $mr);
    
        // เตรียมข้อมูล Master Data สำหรับ Dropdown ในหน้า View
        $assets = Asset::orderBy('asset_code')->get(['id', 'asset_code', 'name']);
        $users  = User::orderBy('name')->get(['id', 'name']);
        $depts  = Department::orderBy('name_th')->get(['id', 'code', 'name_th', 'name_en']);
    
        // ดึงข้อมูลไฟล์แนบโดยเลือกเฉพาะคอลัมน์ที่จำเป็น
        $attachments = $mr->attachments()
            ->select([
                'id',
                'file_id',
                'original_name',
                'is_private',
                'order_column',
                'attachable_id',
                'attachable_type',
            ])
            ->with(['file:id,path,disk,mime,size'])
            ->get();
    
        $techUsers   = $this->suggestTechUsersForRequest($mr);
        $suggestRole = strtolower(trim((string) $mr->type?->default_role_code));
        
        $types = Cache::remember('maintenance_request_types', 3600, function () {
            return \App\Models\MaintenanceRequestType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']);
        });
    
        return view('maintenance.requests.edit', compact(
            'mr',
            'assets',
            'users',
            'attachments',
            'depts',
            'techUsers',
            'suggestRole',
            'types'
        ));
    }
    protected function resolveSort(Request $request): array
    {
        $user   = $request->user();
        $userId = $user?->id;
    
        // กำหนด Key สำหรับเก็บค่าใน Session แยกตาม User ID หรือ Guest
        $sessionSortByKey  = $userId ? "maintenance_sort_by_user_{$userId}"  : 'maintenance_sort_by_guest';
        $sessionSortDirKey = $userId ? "maintenance_sort_dir_user_{$userId}" : 'maintenance_sort_dir_guest';
    
        $allowedSorts = ['request_no', 'id', 'request_date'];
    
        $sortByReq  = $request->query('sort_by');
        $sortDirReq = strtolower((string) $request->query('sort_dir'));
    
        // 1. จัดการการเรียงลำดับคอลัมน์
        if (in_array($sortByReq, $allowedSorts, true)) {
            $sortBy = $sortByReq;
            session([$sessionSortByKey => $sortBy]);
        } else {
            $sortBy = session($sessionSortByKey, 'request_no');
        }
    
        // 2. จัดการทิศทางการเรียงลำดับ
        if (in_array($sortDirReq, ['asc', 'desc'], true)) {
            $sortDir = $sortDirReq;
            session([$sessionSortDirKey => $sortDir]);
        } else {
            $sortDir = session($sessionSortDirKey, 'desc');
        }
    
        return [$sortBy, $sortDir];
    }
    protected function suggestTechUsersForRequest(MR $req): \Illuminate\Support\Collection
    {
        $req->loadMissing(['type']);
        $type = $req->type;
    
        // กำหนดคอลัมน์ที่จำเป็นเพื่อลดภาระการดึงข้อมูลจาก Database
        $selectCols = [
            'id',
            'name',
            'role',
            'department',
            'profile_photo_thumb',
            'profile_photo_path',
        ];
    
        // เตรียม Query พื้นฐานสำหรับกลุ่มทีมงาน
        $base = User::query()
            ->inRoles(User::teamRoles())
            ->with(['roleRef'])
            ->select($selectCols)
            ->orderBy('name');
    
        // หากไม่มีประเภทงานระบุมา ให้คืนค่าเจ้าหน้าที่ทั้งหมดในระบบ
        if (!$type) {
            return $base->get();
        }
    
        $suggested = collect();
    
        // 1) ดึงรายชื่อเจ้าหน้าที่ที่เป็น Default สำหรับงานประเภทนี้
        if (!empty($type->default_user_id)) {
            $u = User::query()
                ->with(['roleRef'])
                ->whereKey((int) $type->default_user_id)
                ->first($selectCols);
    
            if ($u) {
                $suggested->push($u);
            }
        }
    
        // 2) กรองรายชื่อเจ้าหน้าที่ตามหน่วยงานหรือบทบาทที่กำหนดไว้ในประเภทงาน
        $filterQuery = clone $base;
    
        if (!empty($type->default_department_code)) {
            $filterQuery->where('department', trim((string) $type->default_department_code));
        }
    
        if (!empty($type->default_role_code)) {
            $roleCode = strtolower(trim((string) $type->default_role_code));
            $filterQuery->whereRaw('LOWER(role) = ?', [$roleCode]);
        }
    
        $filteredUsers = $filterQuery->get();
    
        // 3) Fallback: หากกรองแล้วไม่เจอใครเลย ให้แสดงรายชื่อทีมทั้งหมดแทน
        if ($filteredUsers->isEmpty()) {
            $filteredUsers = $base->get();
        }
    
        // NEW: รวมทุกคนที่เป็นทีมงานเพื่อให้ Frontend สามารถเลือก "ทั้งหมด" ได้
        // ใช้ Query ใหม่เพื่อให้แน่ใจว่าไม่มี Filter อื่นค้างอยู่
        $allTeam = User::query()
            ->inRoles(User::teamRoles())
            ->with(['roleRef'])
            ->select($selectCols)
            ->orderBy('name')
            ->get();

        // รวมผลลัพธ์ ตัดรายชื่อที่ซ้ำออก และจัดลำดับ Index ใหม่
        return $suggested
            ->merge($filteredUsers)
            ->merge($allTeam)
            ->unique('id')
            ->values();
    }
    
    public function updateType(Request $request, MR $req)
    {
        try {
            $response = Gate::inspect('setType', $req);
            
            if ($response->denied()) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => $response->message() ?: 'คุณไม่มีสิทธิ์เปลี่ยนประเภทใบงานนี้'], 403);
                }
                return back()->with('toast', \App\Support\Toast::warning($response->message() ?: 'คุณไม่มีสิทธิ์เปลี่ยนประเภทใบงานนี้', 3000));
            }

            $validator = Validator::make($request->all(), [
                'type_id' => ['nullable', 'integer', 'exists:maintenance_request_types,id'],
            ]);

            if ($validator->fails()) {
                if ($request->expectsJson()) {
                    return response()->json(['errors' => $validator->errors()], 422);
                }
                return back()->withErrors($validator)
                    ->with('toast', \App\Support\Toast::warning($validator->errors()->first(), 3000));
            }

        $data = $validator->validated();
    
        DB::transaction(function () use ($req, $data, $request) {
            $oldId = (int) ($req->type_id ?? 0);
            $newId = (int) ($data['type_id'] ?? 0);
    
            // หากไม่มีการเปลี่ยนแปลงค่า ไม่ต้องดำเนินการต่อ
            if ($oldId === $newId) {
                return;
            }
    
            $req->type_id = $data['type_id'] ?? null;
            $req->save();
    
            // บันทึกประวัติการเปลี่ยนแปลงลงใน MaintenanceLog
            if (class_exists(MaintenanceLog::class)) {
                $oldType = \App\Models\MaintenanceRequestType::find($oldId)?->name ?? $oldId;
                $newType = \App\Models\MaintenanceRequestType::find($newId)?->name ?? $newId;
                
                MaintenanceLog::create([
                    'request_id'  => $req->id,
                    'action'      => MaintenanceLog::ACTION_UPDATE,
                    'note'        => "เปลี่ยนประเภทใบงาน: [{$oldType}] -> [{$newType}]",
                    'user_id'     => $request->user()?->id,
                    'from_status' => null,
                    'to_status'   => null,
                ]);
            }
    
            Log::info('[MaintenanceRequest::updateType] type updated', [
                'mr_id'  => $req->id,
                'old_id' => $oldId,
                'new_id' => $newId,
                'actor'  => $request->user()?->id,
            ]);
        });
    
            if ($request->expectsJson()) {
                return response()->json([
                    'data' => $req->fresh(['type']),
                    'toast' => Toast::success('อัปเดตประเภทใบงานแล้ว', 1600),
                ], Response::HTTP_OK);
            }
        
            return redirect()->route('maintenance.requests.show', $req)
                ->with('toast', Toast::success('อัปเดตประเภทใบงานแล้ว', 1600));

        } catch (\Exception $e) {
            Log::error('[MaintenanceRequest::updateType] Error', [
                'mr_id'   => $req->id,
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง',
                    'error' => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }

            return back()->with('toast', \App\Support\Toast::error('เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง', 4000));
        }
    }
}

<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRequest as MR;
use App\Models\MaintenanceAssignment;
use App\Models\MaintenanceLog;
use App\Models\MaintenanceRequestType;
use App\Models\Department;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use App\Support\Toast;

class MaintenanceRequestController extends Controller
{
    use \App\Traits\ApiResponseWithToast;

    public function indexPage(Request $request)
    {
        $user   = $request->user();
        $status = strtolower(trim($request->string('status')->toString()));
        $q      = trim($request->string('q')->toString());
        $typeId = $request->input('type_id'); // null, '__null__' (no type) or an id

        [$sortBy, $sortDir] = $this->resolveSort($request);

        $types = MaintenanceRequestType::activeForSelect();

        $list = MR::query()
            // the viewer's own assignment answer, shown on the row (as on My Jobs)
            ->leftJoin('maintenance_assignments as ma', function ($join) use ($user) {
                $join->on('ma.maintenance_request_id', '=', 'maintenance_requests.id')
                    ->where('ma.user_id', '=', $user->id);
            })
            ->select([
                'maintenance_requests.*',
                DB::raw('ma.response_status as my_response_status'),
                DB::raw('ma.responded_at as my_responded_at'),
            ])
            ->with([
                'type',
                'department',
                'asset.department',
                'reporter:id,name,email',
                'technician:id,name',
                'attachments' => fn ($qq) => $qq
                    ->select('id', 'attachable_id', 'attachable_type', 'file_id', 'original_name', 'is_private', 'order_column')
                    ->with(['file:id,path,disk,mime,size']),
            ])
            ->visibleTo($user)
            ->when($request->integer('asset_id'), fn ($qb, $assetId) => $qb->where('maintenance_requests.asset_id', $assetId))
            ->when(filled($status), fn ($qb) => $qb->where('maintenance_requests.status', $status))
            ->when(filled($q), fn ($qb) => $qb->search($q))
            ->when(filled($typeId), fn ($qb) => $typeId === '__null__'
                ? $qb->whereNull('maintenance_requests.type_id')
                : $qb->where('maintenance_requests.type_id', (int) $typeId))
            ->orderedForList($sortBy, $sortDir)
            ->paginate(20)
            ->withQueryString();

        return view('maintenance.requests.index', compact('list', 'types', 'typeId', 'status', 'q', 'sortBy', 'sortDir'));
    }

    public function showPage(MR $req)
    {
        Gate::authorize('view', $req);

        Log::info('Viewed maintenance request', [
            'request_id' => $req->id,
            'user_id'    => Auth::id(),
            'ip_address' => request()->ip(),
        ]);

        $this->loadDetail($req);

        return view('maintenance.requests.show', [
            'req'         => $req,
            'techUsers'   => $this->suggestTechUsersForRequest($req),
            'types'       => MaintenanceRequestType::activeForSelect(),
            'suggestRole' => strtolower(trim((string) $req->type?->default_role_code)),
        ]);
    }

    /** Everything the detail page and the JSON detail endpoint show about a request. */
    private function loadDetail(MR $req): MR
    {
        return $req->loadMissing([
            'type',
            'asset',
            'department',
            'reporter:id,name,email',
            'technician:id,name',
            'assignments' => fn ($query) => $query
                ->where('status', '!=', MaintenanceAssignment::STATUS_CANCELLED)
                ->with('user:id,name,role,profile_photo_path,profile_photo_thumb'),
            'attachments.file',
            'logs.user:id,name',
            'rating',
            'rating.rater:id,name',
            'operationLog.user:id,name',
        ]);
    }

    public function createPage()
    {
        $assets = Asset::orderBy('asset_code')->get(['id', 'asset_code', 'name', 'his_asset_id']);
        $users  = User::orderBy('name')->get(['id', 'name']);
        $depts  = Department::orderBy('name_th')->get(['id', 'code', 'name_th', 'name_en']);
        $types  = MaintenanceRequestType::activeForSelect();

        return view('maintenance.requests.create', compact('assets', 'users', 'depts', 'types'));
    }

    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $q      = trim($request->string('q')->toString());

        [$sortBy, $sortDir] = $this->resolveSort($request);

        $list = MR::query()
            ->with(['asset', 'reporter:id,name,email', 'technician:id,name'])
            ->visibleTo($request->user())
            ->when($request->integer('asset_id'), fn ($qb, $assetId) => $qb->where('asset_id', $assetId))
            ->when($status, fn ($qb) => $qb->where('status', $status))
            ->when($q !== '', fn ($qb) => $qb->search($q))
            ->orderedForList($sortBy, $sortDir)
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
                'toast' => Toast::make('โหลดรายการคำขอบำรุงรักษาแล้ว', 'info', 'tc', 1200, 'sm'),
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

        return response()->json([
            'data' => $this->loadDetail($req),
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
            $msg = $this->friendlyMessage($e);

            if ($request->expectsJson()) {
                return response()->json(['message' => $msg], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return redirect()->back()
                ->withInput()
                ->with('toast', Toast::warning($msg, 3000));
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
            'note'            => ['nullable', 'string', 'max:2000'], // the reason / remark that goes with a `status` change (required for on_hold)
            'operation_date'   => ['nullable', 'date'],
            'operation_method' => ['nullable', \Illuminate\Validation\Rule::in(['requisition', 'service_fee', 'other'])],
            'property_code'    => ['nullable', 'string', 'max:100'],
            'require_precheck' => ['nullable', 'boolean'],
            'remark'           => ['nullable', 'string', 'max:5000'],
            'issue_software'   => ['nullable', 'boolean'],
            'issue_hardware'   => ['nullable', 'boolean'],
            'user_ids'         => ['nullable', 'array'],
            'user_ids.*'       => ['integer', Rule::exists('users', 'id')->whereNull('suspended_at')->whereIn('role', User::teamRoles())],
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

        $validated = $validator->validated();

        // a status change is a transition: it needs the permission of the button for that step (the state map is checked
        // when it is applied, in the service)
        if (! empty($validated['status']) && $validated['status'] !== $req->status) {
            Gate::authorize('moveTo', [$req, $validated['status']]);
        }

        try {
            $req = $service->updateRequest(
                $req,
                $validated,
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
                // a refused status move is the state map's abort(409), as on the transition endpoint
                $code = $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $e->getStatusCode() : 422;

                return response()->json(['message' => $this->friendlyMessage($e)], $code);
            }
            return redirect()->back()
                ->withInput()
                ->with('toast', \App\Support\Toast::warning($this->friendlyMessage($e), 3000));
        }
    }

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
        $assets = Asset::orderBy('asset_code')->get(['id', 'asset_code', 'name', 'his_asset_id']);
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
        $types       = MaintenanceRequestType::activeForSelect();
    
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
    /**
     * Who to offer in the assign-team picker: the type's default person first, then the people matching the type's
     * department / role (the whole team when nobody matches), then the rest of the team. Suspended accounts are
     * never offered — the default person included.
     */
    protected function suggestTechUsersForRequest(MR $req): Collection
    {
        $type = $req->loadMissing('type')->type;

        $columns = ['id', 'name', 'role', 'department', 'profile_photo_thumb', 'profile_photo_path'];

        $team = fn () => User::query()
            ->active()
            ->inRoles(User::teamRoles())
            ->with('roleRef')
            ->select($columns)
            ->orderBy('name');

        $everyone = $team()->get();

        if (! $type) {
            return $everyone;
        }

        $default = $type->default_user_id
            ? User::query()->active()->with('roleRef')->whereKey((int) $type->default_user_id)->first($columns)
            : null;

        $matching = $team()
            ->when(! empty($type->default_department_code), fn ($q) => $q->where('department', trim((string) $type->default_department_code)))
            ->when(! empty($type->default_role_code), fn ($q) => $q->whereRaw('LOWER(role) = ?', [strtolower(trim((string) $type->default_role_code))]))
            ->get();

        return collect([$default])->filter()
            ->merge($matching->isEmpty() ? $everyone : $matching)
            ->merge($everyone)
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

<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Attachment;
use App\Models\Department;
use App\Models\File as FileModel;
use Illuminate\Http\Request;
use Illuminate\Validation\Validator as ValidatorInstance;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Support\AssetInput;
use App\Support\Toast;
use App\Services\HisAssetSyncService;
use App\Support\Like;

class AssetController extends Controller
{
    private function jsonOptions(Request $request): int
    {
        return JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | ($request->boolean('pretty') ? JSON_PRETTY_PRINT : 0);
    }

    public function index(Request $request)
    {
        $q          = trim($request->string('q')->toString());
        $status     = $request->string('status')->toString();
        $type       = $request->string('type')->toString();
        $categoryId = $request->integer('category_id');
        $deptId     = $request->integer('department_id');
        $location   = $request->string('location')->toString();

        $perPageInput = (int) $request->integer('per_page', 20);
        $perPage      = max(1, min($perPageInput, 100));

        $sortMap = [
            'id'              => 'id',
            'asset_code'      => 'asset_code',
            'name'            => 'name',
            'purchase_date'   => 'purchase_date',
            'warranty_expire' => 'warranty_expire',
            'status'          => 'status',
            'created_at'      => 'created_at',
        ];

        [$sortKey, $sortDir] = $this->resolveAssetSort($request, array_keys($sortMap));
        $sortBy = $sortMap[$sortKey] ?? 'id';

        $baseQuery = Asset::query()
            ->with([
                'categoryRef',
                'department',
                // Feed the `hero_image_url` append without an N+1 per row.
                // Mirrors Asset::getHeroImageUrlAttribute()'s image filter;
                // the relation itself already orders by order_column.
                'attachments' => fn ($rel) => $rel
                    ->whereHas('file', fn ($f) => $f->where('mime', 'like', 'image/%'))
                    ->with('file'),
            ])
            ->search($q)
            ->status($status)
            ->category($categoryId)
            ->departmentId($deptId)
            ->type($type)
            ->location($location);

        $this->rankBySearchTerm($baseQuery, $q);

        $filteredTotal = (clone $baseQuery)->toBase()->count();

        $assets = (clone $baseQuery)
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();

        // `attachments` is loaded only to resolve `hero_image_url`; keep it out
        // of the serialized payload so the response shape is unchanged.
        $assets->getCollection()->makeHidden('attachments');

        Log::info('[Asset::index] API listing', [
            'q'          => $q,
            'status'     => $status,
            'category_id'=> $categoryId,
            'dept_id'    => $deptId,
            'total'      => $filteredTotal,
            'actor_id'   => $request->user()?->id,
        ]);

        $payload = [
            'data' => $assets->items(),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'per_page'     => $assets->perPage(),
                'total'        => $assets->total() ?: $filteredTotal,
                'last_page'    => $assets->lastPage(),
            ],
            'sort' => [
                'by'  => $sortKey,
                'dir' => $sortDir,
            ],
            'toast' => Toast::info('โหลดรายการทรัพย์สินแล้ว', 1200),
        ];

        return response()->json($payload, 200, [], $this->jsonOptions($request));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Asset::class);

        $validator = Validator::make($request->all(), AssetInput::rules());

        if ($validator->fails()) {
            return $this->apiValidationFailure($request, $validator, '[Asset::store] validation failed');
        }

        $asset = Asset::create($validator->validated());
        // The request already validates hero_image / files.*; persist them like
        // update() and storePage() do instead of dropping them silently.
        $this->syncAttachments($request, $asset);
        $asset->load(['categoryRef', 'department']);

        Log::info('[Asset::store] API created', [
            'asset_id'   => $asset->id,
            'asset_code' => $asset->asset_code,
            'name'       => $asset->name,
            'actor_id'   => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'created',
            'toast'   => Toast::success('สร้างทรัพย์สินเรียบร้อย', 1600),
            'data'    => $asset,
        ], Response::HTTP_CREATED, [], $this->jsonOptions($request));
    }

    public function show(Asset $asset)
    {
        $asset->load(['categoryRef', 'department']);

        Log::info('[Asset::show] API viewed', [
            'asset_id'   => $asset->id,
            'asset_code' => $asset->asset_code,
            'actor_id'   => request()->user()?->id,
        ]);

        return response()->json([
            'data'  => $asset,
            'toast' => Toast::info('โหลดข้อมูลทรัพย์สินแล้ว', 1000),
        ], 200, [], $this->jsonOptions(request()));
    }

    public function update(Request $request, Asset $asset)
    {
        $this->authorize('update', $asset);

        $validator = Validator::make($request->all(), AssetInput::rules($asset));

        if ($validator->fails()) {
            return $this->apiValidationFailure($request, $validator, '[Asset::update] validation failed', ['asset_id' => $asset->id]);
        }

        $data = $validator->validated();

        if (AssetInput::blocksReactivation($asset, $data)) {
            $msg = AssetInput::REACTIVATION_BLOCKED;

            if (!$request->expectsJson()) {
                return redirect()->back()->withInput()->with('toast', Toast::warning($msg, 3000));
            }

            return response()->json([
                'errors' => ['status' => [$msg]],
                'toast'  => Toast::warning($msg, 3000),
            ], Response::HTTP_UNPROCESSABLE_ENTITY, [], $this->jsonOptions($request));
        }

        $before = $asset->only(['asset_code', 'name', 'status', 'department_id', 'category_id']);
        $asset->update($data);
        $this->syncAttachments($request, $asset);

        Log::info('[Asset::update] API updated', [
            'asset_id'   => $asset->id,
            'asset_code' => $asset->asset_code,
            'before'     => $before,
            'after'      => $asset->only(['asset_code', 'name', 'status', 'department_id', 'category_id']),
            'actor_id'   => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'updated',
            'toast'   => Toast::success('อัปเดตทรัพย์สินเรียบร้อย', 1600),
            'data'    => $asset->load(['categoryRef', 'department']),
        ], Response::HTTP_OK, [], $this->jsonOptions($request));
    }

    public function destroy(Asset $asset)
    {
        $this->authorize('delete', $asset);
        $assetCode = $asset->asset_code;
        $assetId   = $asset->id;

        $asset->delete();

        Log::info('[Asset::destroy] API deleted', [
            'asset_id'   => $assetId,
            'asset_code' => $assetCode,
            'actor_id'   => request()->user()?->id,
        ]);

        return response()->json([
            'message' => 'deleted',
            'toast'   => Toast::success('ลบทรัพย์สินแล้ว', 1600),
        ], Response::HTTP_OK, [], $this->jsonOptions(request()));
    }

    public function indexPage(Request $request)
    {
        $q          = trim($request->string('q')->toString());
        $status     = $request->string('status')->toString();
        $categoryId = $request->integer('category_id');
        $deptId     = $request->integer('department_id');
        $type       = $request->string('type')->toString();
        $location   = $request->string('location')->toString();

        $sortMap = [
            'id'         => 'id',
            'asset_code' => 'asset_code',
            'name'       => 'name',
            'status'     => 'status',
            'category'   => 'category',
        ];

        [$sortBy, $sortDir] = $this->resolveAssetSort($request, array_keys($sortMap));
        $sortCol = $sortMap[$sortBy] ?? 'id';

        $assetsQ = Asset::query()
            ->with(['categoryRef', 'department'])
            ->search($q)
            ->status($status)
            ->category($categoryId)
            ->departmentId($deptId)
            ->type($type)
            ->location($location);

        $this->rankBySearchTerm($assetsQ, $q);

        if ($sortCol === 'category') {
            $assetsQ->orderByRaw(
                "(select name from asset_categories where asset_categories.id = assets.category_id) {$sortDir}"
            );
        } else {
            $assetsQ->orderBy($sortCol, $sortDir);
        }

        $assets      = $assetsQ->paginate(20)->withQueryString();
        $categories  = $this->categoryOptions();
        $departments = $this->departmentOptions()->map(fn ($d) => [
            'id'           => $d->id,
            'display_name' => $d->display_name,
        ]);

        if ($q !== '' && $assets->total() > 0) {
            session()->flash('toast', Toast::success("ค้นหาพบ {$assets->total()} รายการ", 1600));
        } elseif ($q !== '' && $assets->total() === 0) {
            session()->flash('toast', Toast::warning('ไม่พบข้อมูลตามคำค้นหา', 2000));
        }

        return view('assets.index', compact(
            'assets', 'categories', 'departments',
            'sortBy', 'sortDir', 'q', 'status',
            'categoryId', 'deptId', 'type', 'location',
        ));
    }

    public function createPage()
    {
        $this->authorize('create', Asset::class);

        $departments = $this->departmentOptions();
        $categories  = $this->categoryOptions();
        $this->flashIfMasterDataMissing($departments, $categories);

        return view('assets.create', compact('departments', 'categories'));
    }

    public function storePage(Request $request)
    {
        $this->authorize('create', Asset::class);

        $validator = Validator::make($request->all(), AssetInput::rules());

        if ($validator->fails()) {
            return $this->pageValidationFailure($request, $validator, '[Asset::storePage] validation failed');
        }

        $asset = Asset::create($validator->validated());
        $this->syncAttachments($request, $asset);

        Log::info('[Asset::storePage] created', [
            'asset_id'   => $asset->id,
            'asset_code' => $asset->asset_code,
            'name'       => $asset->name,
            'dept_id'    => $asset->department_id,
            'category_id'=> $asset->category_id,
            'actor_id'   => $request->user()?->id,
        ]);

        return redirect()
            ->route('assets.show', $asset)
            ->with('toast', Toast::success("สร้างครุภัณฑ์ {$asset->asset_code} ({$asset->name}) เรียบร้อยแล้ว", 2500));
    }

    public function showPage(Asset $asset)
    {
        $asset->load(['categoryRef', 'department', 'maintenanceRequests.reporter'])
            ->loadCount([
                'maintenanceRequests as maintenance_requests_count',
                'requestAttachments as attachments_count',
            ]);

        $logs = $this->recentLogs($asset);

        $attachments = $asset->requestAttachments()
            ->select('attachments.*')
            ->orderBy('attachments.created_at', 'desc')
            ->get();

        Log::info('[Asset::showPage] viewed', [
            'asset_id'   => $asset->id,
            'asset_code' => $asset->asset_code,
            'mr_count'   => $asset->maintenance_requests_count,
            'actor_id'   => request()->user()?->id,
        ]);

        return view('assets.show', compact('asset', 'logs', 'attachments'));
    }

    public function editPage(Asset $asset)
    {
        $this->authorize('update', $asset);
        $asset->load(['categoryRef', 'department', 'maintenanceRequests.reporter']);

        $departments = $this->departmentOptions();
        $categories  = $this->categoryOptions();
        $this->flashIfMasterDataMissing($departments, $categories);

        $logs = $this->recentLogs($asset);

        return view('assets.edit', compact('asset', 'departments', 'categories', 'logs'));
    }

    public function updatePage(Request $request, Asset $asset)
    {
        $this->authorize('update', $asset);

        $validator = Validator::make($request->all(), AssetInput::rules($asset));

        if ($validator->fails()) {
            return $this->pageValidationFailure($request, $validator, '[Asset::updatePage] validation failed', ['asset_id' => $asset->id]);
        }

        $data = $validator->validated();

        if (AssetInput::blocksReactivation($asset, $data)) {
            return redirect()->back()
                ->withInput()
                ->with('toast', Toast::warning(AssetInput::REACTIVATION_BLOCKED, 4000));
        }

        $before = $asset->only(['asset_code', 'name', 'status', 'department_id', 'category_id', 'location']);

        $asset->update($data);
        $this->syncAttachments($request, $asset);

        Log::info('[Asset::updatePage] updated', [
            'asset_id'   => $asset->id,
            'asset_code' => $asset->asset_code,
            'before'     => $before,
            'after'      => $asset->only(['asset_code', 'name', 'status', 'department_id', 'category_id', 'location']),
            'actor_id'   => $request->user()?->id,
        ]);

        return redirect()
            ->route('assets.show', $asset)
            ->with('toast', Toast::success("อัปเดตข้อมูล {$asset->asset_code} ({$asset->name}) เรียบร้อยแล้ว", 2500));
    }

    public function destroyPage(Asset $asset)
    {
        $this->authorize('delete', $asset);
        $assetCode = $asset->asset_code;
        $assetId   = $asset->id;
        $assetName = $asset->name;

        $asset->delete();

        Log::info('[Asset::destroyPage] deleted', [
            'asset_id'   => $assetId,
            'asset_code' => $assetCode,
            'name'       => $assetName,
            'actor_id'   => request()->user()?->id,
        ]);

        return redirect()
            ->route('assets.index')
            ->with('toast', Toast::success("ลบข้อมูลครุภัณฑ์ {$assetCode} ({$assetName}) เรียบร้อยแล้ว"));
    }

    public function printPage(Request $request, Asset $asset)
    {
        $asset->load(['categoryRef', 'department'])
            ->loadCount([
                'maintenanceRequests as maintenance_requests_count',
                'requestAttachments as attachments_count',
            ]);

        Log::info('[Asset::printPage] print PDF', [
            'asset_id'   => $asset->id,
            'asset_code' => $asset->asset_code,
            'actor_id'   => $request->user()?->id,
        ]);

        $hospital = [
            'name_th'  => 'โรงพยาบาลพระปกเกล้า',
            'name_en'  => 'PHRAPOKKLAO HOSPITAL',
            'subtitle' => 'Asset Repair Management',
            'logo'     => asset('images/logoppk1.png'),
        ];

        $pdf = Pdf::loadView('assets.print', [
            'asset'    => $asset,
            'hospital' => $hospital,
        ])->setPaper('A4', 'portrait');

        return $pdf->stream('asset-' . $asset->asset_code . '.pdf');
    }

    /**
     * GET /assets/fetch-his?his_id=xxx
     * ดึงข้อมูลครุภัณฑ์จาก HIS (Mock) เพื่อ auto-fill form
     *
     * Validation: his_id required|string|max:50
     * Response: { status: 'found'|'not_found', data: {...} }
     */
    public function fetchHisData(Request $request)
    {
        $validated = $request->validate([
            'his_id' => ['required', 'string', 'max:50'],
        ]);

        $hisId = trim($validated['his_id']);

        $mockData = app(HisAssetSyncService::class)->getMockHisData($hisId);

        if ($mockData === null) {
            return response()->json([
                'status'  => 'not_found',
                'message' => 'ไม่พบข้อมูล HIS สำหรับเลขนี้',
                'toast'   => Toast::warning('ไม่พบข้อมูล HIS สำหรับเลขนี้', 2200),
            ], 404, [], $this->jsonOptions($request));
        }

        Log::info('[AssetController] fetchHisData (mock)', [
            'his_id'   => $hisId,
            'actor_id' => $request->user()?->id,
        ]);

        return response()->json([
            'status' => 'found',
            'data'   => [
                'name'           => $mockData['name']            ?? null,
                'asset_code'     => $mockData['asset_no']        ?? null,
                'type'           => $mockData['type']            ?? 'เครื่องมือแพทย์',
                'brand'          => $mockData['brand']           ?? null,
                'model'          => $mockData['model']           ?? null,
                'serial_number'  => $mockData['serial']          ?? null,
                'vendor_name'    => $mockData['vendor_name']     ?? null,
                'vendor_phone'   => $mockData['vendor_phone']    ?? null,
                'internal_phone' => $mockData['internal_phone']  ?? null,
                'price'          => $mockData['price']           ?? null,
                'purchase_date'  => $mockData['warranty_start']  ?? null,
                'warranty_start' => $mockData['warranty_start']  ?? null,
                'warranty_expire'=> $mockData['warranty_expire'] ?? null,
                'category_id'    => $mockData['category_id']     ?? null,
                'department_id'  => $mockData['department_id']   ?? null,
                'status'         => $mockData['status']          ?? null,
                'note'           => $mockData['note']            ?? null,
            ],
            'toast' => Toast::success('ดึงข้อมูล HIS สำเร็จ', 1600),
        ], 200, [], $this->jsonOptions($request));
    }

    /** Exact code first, then code / HIS id prefix, then name / serial contains — only when searching. */
    private function rankBySearchTerm($query, string $q): void
    {
        if ($q === '') {
            return;
        }

        $query->orderByRaw("
            CASE
                WHEN assets.asset_code = ? THEN 0
                WHEN assets.his_asset_id = ? THEN 1
                WHEN assets.asset_code LIKE ? THEN 2
                WHEN assets.his_asset_id LIKE ? THEN 3
                WHEN assets.name LIKE ? THEN 4
                WHEN assets.serial_number LIKE ? THEN 5
                ELSE 9
            END
        ", [$q, $q, Like::startsWith($q), Like::startsWith($q), Like::contains($q), Like::contains($q)]);
    }

    private function departmentOptions()
    {
        return Department::query()
            ->select(['id', 'code', 'name_th', 'name_en'])
            ->orderByRaw('COALESCE(name_th, name_en, code) asc')
            ->get();
    }

    private function categoryOptions()
    {
        return AssetCategory::orderBy('name')->get(['id', 'name']);
    }

    /** The forms are useless without these lists; say so instead of showing empty selects (categories win if both are empty). */
    private function flashIfMasterDataMissing($departments, $categories): void
    {
        if ($departments->isEmpty()) {
            session()->flash('toast', Toast::info('ยังไม่มีข้อมูลหน่วยงาน กรุณา seed หรือเพิ่มใหม่ก่อน', 3200));
        }
        if ($categories->isEmpty()) {
            session()->flash('toast', Toast::info('ยังไม่มีหมวดหมู่ทรัพย์สิน กรุณา seed หรือเพิ่มใหม่ก่อน', 3200));
        }
    }

    /** Latest 20 log lines of the asset's repair requests. */
    private function recentLogs(Asset $asset)
    {
        return $asset->requestLogs()
            ->with(['user', 'request'])
            ->select('maintenance_logs.*')
            ->orderBy('maintenance_logs.created_at', 'desc')
            ->orderBy('maintenance_logs.id', 'desc')
            ->limit(20)
            ->get();
    }

    /** Failed validation on the shared create/update endpoint: back with errors for a browser, 422 for JSON. */
    private function apiValidationFailure(Request $request, ValidatorInstance $validator, string $logTag, array $context = [])
    {
        $errors = $validator->errors();
        $msg    = AssetInput::failureMessage($errors);

        Log::warning($logTag, $context + ['errors' => $errors->toArray(), 'actor_id' => $request->user()?->id]);

        if (!$request->expectsJson()) {
            return redirect()->back()->withErrors($validator)->withInput()->with('toast', Toast::warning($msg, 2200));
        }

        return response()->json([
            'errors' => $errors,
            'toast'  => Toast::warning($msg, 2200),
        ], Response::HTTP_UNPROCESSABLE_ENTITY, [], $this->jsonOptions($request));
    }

    private function pageValidationFailure(Request $request, ValidatorInstance $validator, string $logTag, array $context = [])
    {
        $errors = $validator->errors();
        $msg    = AssetInput::failureMessage($errors);

        Log::warning($logTag, $context + ['errors' => $errors->toArray(), 'actor_id' => $request->user()?->id]);

        return redirect()->back()->withErrors($validator)->withInput()->with('toast', Toast::warning($msg, 3000));
    }

    protected function resolveAssetSort(Request $request, array $allowedKeys): array
    {
        $user   = $request->user();
        $userId = $user?->id;

        $sessionSortByKey  = $userId ? "asset_sort_by_user_{$userId}"  : 'asset_sort_by_guest';
        $sessionSortDirKey = $userId ? "asset_sort_dir_user_{$userId}" : 'asset_sort_dir_guest';

        $sortByReq  = $request->query('sort_by');
        $sortDirReq = strtolower((string) $request->query('sort_dir'));

        if (in_array($sortByReq, $allowedKeys, true)) {
            $sortBy = $sortByReq;
            session([$sessionSortByKey => $sortBy]);
        } else {
            $sortBy = session($sessionSortByKey, 'id');
        }

        if (in_array($sortDirReq, ['asc', 'desc'], true)) {
            $sortDir = $sortDirReq;
            session([$sessionSortDirKey => $sortDir]);
        } else {
            $sortDir = session($sessionSortDirKey, 'desc');
        }

        return [$sortBy, $sortDir];
    }

    private function syncAttachments(Request $request, Asset $asset)
    {
        // 1. Handle Hero Image (Strict Replacement)
        if ($request->hasFile('hero_image')) {
            // Find existing hero image (order_column = -1)
            $oldHero = $asset->attachments()
                ->where('order_column', Attachment::HERO_ORDER)
                ->first();

            if ($oldHero) {
                // Delete physical file and DB records safely
                $oldHero->deleteAndCleanup(true);
            }

            $file = $request->file('hero_image');
            $path = $file->store('assets/hero', 'public');
            $fileModel = FileModel::create([
                'path' => $path,
                'disk' => 'public',
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);

            $asset->attachments()->create([
                'file_id' => $fileModel->id,
                'original_name' => $file->getClientOriginalName(),
                'extension' => $file->getClientOriginalExtension(),
                'order_column' => Attachment::HERO_ORDER,
                'uploaded_by' => Auth::id(),
            ]);
        }

        // 2. Handle Multiple Files
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('assets/attachments', 'public');
                $fileModel = FileModel::create([
                    'path' => $path,
                    'disk' => 'public',
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
                $asset->attachments()->create([
                    'file_id' => $fileModel->id,
                    'original_name' => $file->getClientOriginalName(),
                    'extension' => $file->getClientOriginalExtension(),
                    'uploaded_by' => Auth::id(),
                ]);
            }
        }

        // 3. Handle Removal
        if ($request->has('remove_attachments')) {
            $toRemove = Attachment::whereIn('id', $request->remove_attachments)
                ->where('attachable_type', Asset::class)
                ->where('attachable_id', $asset->id)
                ->get();

            foreach ($toRemove as $att) {
                // This will also cleanup physical files if no other attachment points to same file_id
                $att->deleteAndCleanup(true);
            }
        }
    }
}

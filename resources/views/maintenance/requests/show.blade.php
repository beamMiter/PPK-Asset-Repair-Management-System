@extends('layouts.app')

@section('title', 'Request #' . ($req->request_no ?? $req->id))

@section('page-header')
    @php
        use App\Models\MaintenanceRequest as MR;
        use Carbon\Carbon;

        $line = 'border-slate-200';

        $statusLabels = [
            'pending' => 'รอรับเรื่อง',
            'acknowledged' => 'รับทราบแล้ว',
            'accepted' => 'รับเรื่องแล้ว',
            'in_progress' => 'กำลังดำเนินการ',
            'on_hold' => 'พักชั่วคราว',
            'resolved' => 'เสร็จสิ้น',
            'closed' => 'ปิดงาน',
            'cancelled' => 'ยกเลิก',
        ];

        $status = $req->status;
        $currentStatusTH = $statusLabels[$status] ?? $status;

        $level = 1;
        if ($status === MR::STATUS_ACKNOWLEDGED) {
            $level = 2;
        }
        if ($status === MR::STATUS_ACCEPTED) {
            $level = 3;
        }
        if (in_array($status, [MR::STATUS_IN_PROGRESS, MR::STATUS_ON_HOLD], true)) {
            $level = 4;
        }
        if (in_array($status, [MR::STATUS_RESOLVED, MR::STATUS_CLOSED], true)) {
            $level = 5;
        }
        if ($status === MR::STATUS_CANCELLED) {
            $level = 0;
        }

        $dates = [
            1 => $req->request_date ?? $req->created_at,
            2 => $req->acknowledged_at,
            3 => $req->accepted_at,
            4 => $req->started_at ?? ($level >= 4 ? $req->accepted_at : null),
            5 => $req->resolved_at ?? $req->closed_at,
        ];

        $fmt = fn($d) => $d ? Carbon::parse($d)->format('d/m/Y H:i') : '';

        $widthMap = [1 => '0%', 2 => '25%', 3 => '50%', 4 => '75%', 5 => '100%'];
        $lineWidth = $widthMap[$level] ?? '0%';

        $canAcknowledge = $status === MR::STATUS_PENDING && \Gate::allows('acknowledge', $req);
        $canAccept = $status === MR::STATUS_ACKNOWLEDGED && \Gate::allows('accept', $req);
        $canReject =
            in_array($status, [MR::STATUS_PENDING, MR::STATUS_ACKNOWLEDGED], true) && \Gate::allows('reject', $req);
        $canCancel =
            in_array($status, [MR::STATUS_ACCEPTED, MR::STATUS_IN_PROGRESS, MR::STATUS_ON_HOLD], true) &&
            \Gate::allows('cancel', $req);
        $canStart = $status === MR::STATUS_ACCEPTED && \Gate::allows('startWork', $req);
        $canHold =
            in_array($status, [MR::STATUS_ACCEPTED, MR::STATUS_IN_PROGRESS], true) && \Gate::allows('hold', $req);
        $canResume = $status === MR::STATUS_ON_HOLD && \Gate::allows('resume', $req);
        $canResolve = $status === MR::STATUS_IN_PROGRESS && \Gate::allows('resolve', $req);
        $canClose = $status === MR::STATUS_RESOLVED && \Gate::allows('close', $req);
        $canUpdate = \Gate::allows('update', $req);

        // Calculate Technician Display Name (Multi-tech support)
        $headerWorkers = $req->workers;
        if ($headerWorkers->isNotEmpty()) {
            $lead = $headerWorkers->firstWhere('pivot.is_lead', true) ?? $headerWorkers->first();
            if ($headerWorkers->count() > 1) {
                $techDisplayName = $lead->name . ' + ' . ($headerWorkers->count() - 1) . ' คน';
            } else {
                $techDisplayName = $lead->name;
            }
        } else {
            $techDisplayName = $req->technician?->name ?? 'ยังไม่มีเจ้าหน้าที่รับเรื่อง';
        }
    @endphp

    @include('maintenance.requests.partials._page_header')
@endsection

@section('content')
    @php
        use Illuminate\Support\Facades\Storage;

        $line = 'border-slate-200';

        $textareaStyle = 'min-height:unset;height:auto;';

        $headCls = 'flex items-start gap-3 pb-3 min-h-[56px]';
        $noCls = "w-8 h-8 shrink-0 rounded-full border border-emerald-600 bg-emerald-600
                flex items-center justify-center text-sm font-bold text-white leading-none";
        $titleCls = 'text-base font-semibold text-slate-900 leading-tight';
        $subCls = 'text-sm text-slate-500 leading-snug';
        $accentWrap = 'min-w-0 relative pl-3 pt-[1px]';
        $accentBar = 'absolute left-0 top-[2px] w-[3px] h-9 rounded-full bg-emerald-600/90';

        $select = 'ts-basic w-full';

        $assetName = $req->asset?->name ?? ($req->asset_id ? '#' . $req->asset_id : '—');
        $assetCode = $req->asset?->asset_code;
        $location = $req->location_text ?: $req->department?->name_th ?? ($req->department?->name_en ?? '—');

        $assignments = $req->assignments ?? collect();
        $workers = $assignments->map(fn($a) => $a->user)->filter()->unique('id')->values();

        $atts = $req->attachments ?? collect();
        $opLog = $req->operationLog;

        $allWorkers = $techUsers ?? collect();

        $assignStoreUrl = route('maintenance.requests.assignments.store', $req->id);
        $opLogUrl = route('maintenance.requests.operation-log', $req->id);
        $attachUploadUrl = route('maintenance.requests.attachments', $req->id);

        $suggestRole = strtolower(trim((string) optional($req->type)->default_role_code));

        $fallbackRoleLabels = [
            'it' => 'IT',
            'tech' => 'เจ้าหน้าที่',
            'technician' => 'เจ้าหน้าที่',
            'engineer' => 'วิศวกร',
            'supervisor' => 'หัวหน้างาน',
            'unknown' => 'อื่น ๆ',
        ];

        $roleGroups = $allWorkers->filter()->groupBy(fn($u) => (string) ($u->role ?? 'unknown'));

        $roleLabels = [];
        foreach ($allWorkers as $u) {
            $code = strtolower(trim((string) ($u->role ?? 'unknown')));
            if (!isset($roleLabels[$code])) {
                $roleLabels[$code] = $u->role_label ?? ($fallbackRoleLabels[$code] ?? ucfirst($code));
            }
        }

        $roleGroupsSorted = $roleGroups->sortBy(function ($users, $roleCode) {
            $first = $users->first();
            $sort = $first?->roleRef?->sort_order;
            if ($sort === null) {
                return 9999;
            }
            return (int) $sort;
        });
    @endphp

    <div class="mx-auto max-w-screen-2xl px-3 sm:px-6 lg:px-8 pb-8" x-data="{ ratingOpen: {{ request('rate') == 1 || session('auto_rate') || $errors->has('score') || $errors->has('comment') ? 'true' : 'false' }} }"
        x-on:open-rating-modal.window="ratingOpen = true" x-init="if (new URLSearchParams(window.location.search).has('rate')) {
            let url = new URL(window.location.href);
            url.searchParams.delete('rate');
            window.history.replaceState({}, '', url);
        }">
        @include('maintenance.requests.partials._summary_cards')

        @include('maintenance.requests.partials._modal_assign')
        @include('maintenance.requests.partials._modal_status_actions')
        @include('maintenance.requests.partials._modal_history')
        @include('maintenance.requests.partials._modal_post_close')
        @include('maintenance.requests.partials._modal_rating')
    </div>
@endsection

@push('scripts')
    @include('maintenance.requests.partials._page_scripts')
@endpush

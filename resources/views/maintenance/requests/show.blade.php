@extends('layouts.app')

@section('title', 'สรุปใบงานซ่อม #' . ($req->request_no ?? $req->id))

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

    <style>
        .animate-spin-slow {
            animation: spin 2s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .ms {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>

    <div class="w-full bg-slate-50 border-b {{ $line }}">
        <div class="mx-auto max-w-screen-2xl px-4 sm:px-6 lg:px-8 py-4">

            {{-- Row 1: Title + Actions --}}
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

                <div class="flex items-center gap-3 min-w-0">
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center">
                        <img src="/icon/maintenance1.webp" class="h-7 w-7 object-contain"
                            style="filter: invert(24%) sepia(87%) saturate(1469%) hue-rotate(139deg) brightness(91%) contrast(102%);"
                            alt="Icon">
                    </span>

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                            <h1 class="text-[18px] sm:text-[20px] font-semibold text-slate-900 leading-tight">
                                ทะเบียนแจ้งซ่อม
                            </h1>
                            <span class="text-slate-400 text-[13px] font-semibold">
                                #{{ $req->request_no ?? $req->id }}
                            </span>
                        </div>
                        <div class="mt-0.5 text-[12px] text-slate-500 flex flex-wrap gap-x-3">
                            @if ($req->updated_at)
                                <span>อัปเดต: <span
                                        class="font-medium text-slate-700">{{ $req->updated_at->format('Y-m-d H:i') }}</span></span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Action buttons: workflow left, nav right --}}
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <div class="w-px h-6 bg-slate-200 mx-1 hidden sm:block"></div>

                    {{-- Workflow buttons --}}
                    @if ($canAcknowledge)
                        <form method="POST" action="{{ route('maintenance.requests.acknowledge', $req->id) }}">
                            @csrf
                            <x-ui.button type="submit" variant="brand" icon="approval_delegation" split>รับทราบ</x-ui.button>
                        </form>
                    @endif

                    @if ($canAccept)
                        <form method="POST" action="{{ route('maintenance.requests.accept', $req->id) }}">
                            @csrf
                            <x-ui.button type="submit" variant="info" icon="check" split>รับเรื่อง</x-ui.button>
                        </form>
                    @endif

                    @if ($canStart)
                        <form method="POST" action="{{ route('maintenance.requests.start', $req->id) }}">
                            @csrf
                            <x-ui.button type="submit" variant="primary" icon="keyboard_arrow_right" split>ดำเนินการ</x-ui.button>
                        </form>
                    @endif

                    @if ($canHold)
                        <x-ui.button id="openHoldModalBtn" variant="warning" icon="pause_circle" split>หยุดชั่วคราว</x-ui.button>
                    @endif

                    @if ($canResume)
                        <form method="POST" action="{{ route('maintenance.requests.resume', $req->id) }}">
                            @csrf
                            <x-ui.button type="submit" variant="info" icon="keyboard_double_arrow_right" split>กลับเข้าดำเนินการ</x-ui.button>
                        </form>
                    @endif

                    @if ($canResolve)
                        <x-ui.button id="openResolveModalBtn" variant="primary" icon="task_alt" split>เสร็จสิ้น</x-ui.button>
                    @endif

                    @if ($canClose)
                        <form method="POST" action="{{ route('maintenance.requests.close', $req->id) }}">
                            @csrf
                            <x-ui.button type="submit" variant="primary" icon="check" split>อนุมัติปิดงาน</x-ui.button>
                        </form>
                    @endif

                    @if ($req->status === \App\Models\MaintenanceRequest::STATUS_CLOSED)
                        @can('rate', $req)
                            <x-ui.button x-data @click="$dispatch('open-rating-modal')" variant="warning" icon="star" split>ประเมินความพึงพอใจ</x-ui.button>
                        @endcan
                    @endif

                    @if ($canReject)
                        <x-ui.button id="openRejectModalBtn" variant="danger" icon="block" split>ไม่รับเรื่อง</x-ui.button>
                    @endif

                    @if ($canCancel)
                        <x-ui.button id="openCancelModalBtn" variant="neutral" icon="cancel" split>ยกเลิกการซ่อมบำรุง</x-ui.button>
                    @endif

                    {{-- Tools + navigation, same order as the asset page: actions first, กลับ last.
                         Direct children of the row above so every gap is the same gap-2. --}}
                    @if ($canUpdate)
                        <x-ui.button :href="route('maintenance.requests.edit', $req->id)" icon="edit">แก้ไข</x-ui.button>
                    @endif

                    <x-ui.button :href="route('maintenance.requests.work-order', $req->id)" target="_blank" icon="print">พิมพ์ PDF</x-ui.button>

                    <x-ui.back-button :fallback="route('maintenance.requests.index')" />
                </div>
            </div>

            {{-- Row 2: Progress + history. The history log is the status timeline, so it sits with the
                 progress bar (labelled, with a count) instead of as an icon in the crowded action row. --}}
            <div class="mt-4 flex items-center justify-between gap-3 px-2 sm:px-4">
                <div class="hidden sm:block text-[13px] font-semibold text-slate-600 whitespace-nowrap">ความคืบหน้า</div>

                <x-ui.button id="openHistoryModalBtn" icon="history" class="ml-auto">
                    ประวัติการดำเนินงาน
                    <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-600">{{ $req->logs->count() }}</span>
                </x-ui.button>
            </div>

            {{-- Progress bar --}}
            <div class="w-full px-2 sm:px-4 mt-3 overflow-x-auto pb-4">
                <div class="relative w-full min-w-[500px] sm:min-w-full">
                    <div class="absolute top-[22px] left-0 w-full h-[6px] bg-slate-200 rounded-full z-0"></div>
                    <div class="absolute top-[22px] left-0 h-[6px] bg-[#1e3a8a] rounded-full z-0 transition-all duration-700 ease-out"
                        style="width: {{ $lineWidth }};"></div>

                    <div class="relative z-10 flex justify-between w-full">
                        @php
                            $steps = [
                                1 => 'แจ้งเรื่อง',
                                2 => 'รับทราบแล้ว',
                                3 => 'รับเรื่องแล้ว',
                                4 => 'กำลังดำเนินการ',
                                5 => 'เสร็จสิ้น',
                            ];
                        @endphp

                        @foreach ($steps as $key => $label)
                            @php
                                $isDone = $level > $key || ($key == 5 && $level >= 5);
                                $isCurrent = $level == $key && $key != 5;
                                $dateVal = $dates[$key] ?? null;
                            @endphp

                            <div class="flex flex-col items-center w-32">
                                <div
                                    class="w-[44px] h-[44px] rounded-full flex items-center justify-center transition-all duration-300
                                    {{ $isDone ? 'bg-[#408a5c] border-4 border-[#408a5c]' : '' }}
                                    {{ $isCurrent ? 'bg-white' : '' }}
                                    {{ !$isDone && !$isCurrent ? 'bg-slate-200 border-4 border-slate-200' : '' }}">
                                    @if ($isDone)
                                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                    @elseif($isCurrent)
                                        <div class="relative w-full h-full">
                                            <div class="absolute inset-0 rounded-full border-[4px] border-slate-100"></div>
                                            <div
                                                class="absolute inset-0 rounded-full border-[4px] border-t-blue-600 border-r-transparent border-b-transparent border-l-transparent animate-spin-slow">
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div class="mt-2 text-center">
                                    <p
                                        class="text-[13px] font-bold {{ $isCurrent || $isDone ? 'text-slate-900' : 'text-slate-400' }}">
                                        {{ $label }}
                                    </p>
                                    @if ($isCurrent)
                                        <p class="text-[11px] font-medium text-[#1e3a8a] mt-0.5">(ปัจจุบัน)</p>
                                    @elseif($dateVal)
                                        <p class="text-[11px] text-slate-400 mt-0.5">{{ $fmt($dateVal) }}</p>
                                    @else
                                        <p class="h-[16px]"></p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

        </div>
    </div>
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
        <div class="mt-4 space-y-6">
            <div class="relative grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                <div class="hidden lg:block absolute inset-y-0 left-1/2 w-px bg-slate-200"></div>

                @include('maintenance.requests.partials._asset_info')

                @include('maintenance.requests.partials._issue_details')
            </div>

            <div class="border-t {{ $line }}"></div>

            <div class="relative grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                <div class="hidden lg:block absolute inset-y-0 left-1/2 w-px bg-slate-200"></div>
                @include('maintenance.requests.partials._reporter_info')
                @include('maintenance.requests.partials._attachments')
            </div>

            <div class="border-t {{ $line }}"></div>

            <div class="relative grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                <div class="hidden lg:block absolute inset-y-0 left-1/2 w-px bg-slate-200"></div>
                @include('maintenance.requests.partials._assigned_team')
                @include('maintenance.requests.partials._operation_log')
            </div>

            <div class="border-t {{ $line }}"></div>

            @include('maintenance.requests.partials._sla_info')
        </div>

        {{-- Assign Modal --}}
        @can('assign', $req)
            <div id="assignModal"
                class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm p-3 sm:p-4">
                <div
                    class="relative z-[10000] w-full max-w-4xl overflow-hidden rounded-2xl border {{ $line }} bg-white ">

                    {{-- Modal Header --}}
                    <div class="flex items-center justify-between border-b {{ $line }} px-6 py-4">
                        <div class="flex items-start gap-3 min-w-0">
                            <span class="mt-0.5 inline-flex h-10 w-10 items-center justify-center text-indigo-700">
                                <img src="/icon/technical-support.webp" class="h-9 w-9 object-contain" alt="Icon">
                            </span>
                            <div class="min-w-0">
                                <div class="text-[16px] font-semibold text-slate-900 leading-tight">มอบหมายทีมเจ้าหน้าที่</div>
                                <p class="text-[13px] text-slate-500">ค้นหาและเลือกเจ้าหน้าที่ที่ต้องการ</p>
                            </div>
                        </div>
                        <x-ui.button id="closeAssignModalBtn" variant="ghost" size="icon" icon="close" aria-label="ปิด" />
                    </div>

                    <form method="POST" action="{{ $assignStoreUrl }}" data-dirty-check="true">
                        @csrf
                        <input type="hidden" id="assignSuggestRole" value="{{ $suggestRole }}">
                        <input type="hidden" name="update_team_flag" value="1">

                        <div class="grid grid-cols-1 lg:grid-cols-[380px,1fr] lg:h-[65vh]">

                            {{-- Left Sidebar --}}
                            <div
                                class="border-b lg:border-b-0 lg:border-r {{ $line }} bg-slate-50 flex flex-col min-h-0">

                                {{-- Controls --}}
                                <div class="p-5 space-y-4 flex-none">
                                    <div>
                                        <label class="block text-[13px] font-semibold text-slate-700 mb-1.5">ค้นหาชื่อ</label>
                                        <div class="relative">
                                            <span
                                                class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none">
                                                    <circle cx="11" cy="11" r="7" stroke="currentColor"
                                                        stroke-width="2" />
                                                    <path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2"
                                                        stroke-linecap="round" />
                                                </svg>
                                            </span>
                                            <input id="assignSearch" type="text"
                                                class="w-full rounded-md border {{ $line }} bg-white pl-9 pr-3 py-2.5 text-[13px]
                                        focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400"
                                                placeholder="พิมพ์ชื่อเจ้าหน้าที่...">
                                        </div>
                                    </div>

                                    <div>
                                        <label
                                            class="block text-[13px] font-semibold text-slate-700 mb-1.5">กรองตามตำแหน่ง</label>
                                        <select id="assignRoleFilter"
                                            class="w-full rounded-md border {{ $line }} bg-white px-3 py-2.5 text-[13px]
                                    focus:outline-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400">
                                            <option value="">— ทั้งหมด —</option>
                                            @foreach ($roleGroupsSorted as $roleCode => $users)
                                                <option value="{{ strtolower((string) $roleCode) }}">
                                                    {{ $roleLabels[$roleCode] ?? ($fallbackRoleLabels[$roleCode] ?? ucfirst((string) $roleCode)) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div id="assignSuggestHint" class="mt-1.5 text-[12px] text-indigo-600 hidden">
                                            ตัวกรองถูกตั้งค่าตามประเภทงานโดยอัตโนมัติ
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2">
                                        <x-ui.button id="assignSelectAllBtn" size="sm">เลือกทั้งหมด</x-ui.button>
                                        <x-ui.button id="assignClearAllBtn" size="sm">ล้างการเลือก</x-ui.button>
                                    </div>
                                </div>

                                {{-- Selected List — เติบโตเต็ม sidebar ที่เหลือ --}}
                                <div class="px-5 pb-4 border-t {{ $line }} pt-4 flex flex-col flex-1 min-h-0">
                                    <div class="flex items-center justify-between flex-none mb-2">
                                        <div class="text-[13px] font-semibold text-slate-700">เลือกแล้ว</div>
                                        <div class="text-[13px] text-slate-500" id="assignSelectedMeta">0 คน</div>
                                    </div>
                                    <div id="assignSelectedEmpty" class="text-[13px] text-slate-400 flex-none">
                                        ยังไม่ได้เลือกเจ้าหน้าที่
                                    </div>
                                    {{-- แต่ละ item แสดงชื่อเต็ม ไม่ตัด --}}
                                    <div id="assignSelectedList"
                                        class="space-y-1.5 overflow-y-auto pr-1 hidden flex-1 min-h-0"></div>
                                </div>

                                {{-- Footer ปุ่มย้ายมาอยู่ใต้ sidebar --}}
                                <div class="border-t {{ $line }} bg-slate-50 px-5 py-3 flex justify-end gap-2">
                                    <x-ui.button id="cancelAssignModalBtn">ยกเลิก</x-ui.button>
                                    <x-ui.button type="submit" variant="primary">บันทึก</x-ui.button>
                                </div>

                            </div>

                            {{-- Right List --}}
                            <div class="flex flex-col min-h-0">

                                {{-- Right Header --}}
                                <div
                                    class="px-5 py-3 border-b {{ $line }} bg-white flex items-center justify-between">
                                    <div class="text-[14px] font-semibold text-slate-800">
                                        รายชื่อเจ้าหน้าที่
                                        <span id="assignVisibleCount" class="text-slate-500 font-normal">(0)</span>
                                    </div>
                                </div>

                                {{-- Hint --}}
                                <div class="px-5 py-2 border-b {{ $line }} bg-slate-50">
                                    <p class="text-[12px] text-slate-400">
                                        ใช้ตัวกรองด้านซ้ายเพื่อค้นหาและเลือกเจ้าหน้าที่ได้รวดเร็ว
                                    </p>
                                </div>

                                {{-- List --}}
                                <div class="flex-1 min-h-0 overflow-y-auto" id="assignListScroll">
                                    @if ($roleGroupsSorted->isEmpty())
                                        <div class="px-5 py-10 text-center text-[14px] text-slate-500">
                                            ไม่พบข้อมูลเจ้าหน้าที่ในระบบ
                                        </div>
                                    @else
                                        @foreach ($roleGroupsSorted as $roleCode => $users)
                                            @php
                                                $roleTitle =
                                                    $roleLabels[$roleCode] ??
                                                    ($fallbackRoleLabels[$roleCode] ?? ucfirst((string) $roleCode));
                                                $roleCount = $users->count();
                                                $roleKey = strtolower(trim((string) $roleCode));
                                            @endphp
                                            <section class="border-b {{ $line }}" data-role-group="1"
                                                data-role-group-code="{{ $roleKey }}">

                                                {{-- Section Header --}}
                                                <div
                                                    class="sticky top-0 z-10 px-5 py-2 bg-slate-100 border-b {{ $line }}">
                                                    <div
                                                        class="text-[12px] font-bold text-slate-600 uppercase tracking-widest">
                                                        {{ $roleTitle }} ({{ $roleCount }})
                                                    </div>
                                                </div>

                                                <div class="divide-y divide-slate-100">
                                                    @foreach ($users as $worker)
                                                        @php
                                                            $roleLabelRow =
                                                                $worker->role_label ??
                                                                ($fallbackRoleLabels[$worker->role ?? 'unknown'] ??
                                                                    ($worker->role ?? 'unknown'));
                                                            $avatar = $worker->avatar_thumb_url ?? null;
                                                        @endphp
                                                        <label
                                                            class="assign-user-row flex items-center gap-3 px-5 py-2.5 hover:bg-indigo-50/30 cursor-pointer transition-colors"
                                                            data-role="{{ $roleKey }}"
                                                            data-name="{{ strtolower((string) $worker->name) }}"
                                                            data-display-name="{{ $worker->name }}"
                                                            data-role-label="{{ $roleLabelRow }}">

                                                            <input type="checkbox"
                                                                class="assign-user-checkbox h-4 w-4 flex-shrink-0 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                                                data-role="{{ (string) $roleCode }}" name="user_ids[]"
                                                                value="{{ $worker->id }}" @checked($workers->contains('id', $worker->id))>

                                                            <div
                                                                class="h-8 w-8 flex-shrink-0 overflow-hidden rounded-full border border-slate-200 bg-slate-100">
                                                                @if ($avatar)
                                                                    <img src="{{ $avatar }}" alt="{{ $worker->name }}"
                                                                        class="h-full w-full object-cover">
                                                                @else
                                                                    <div
                                                                        class="grid h-full w-full place-items-center text-[12px] font-semibold text-slate-500 bg-slate-100 uppercase">
                                                                        {{ mb_substr($worker->name, 0, 1) }}
                                                                    </div>
                                                                @endif
                                                            </div>

                                                            <div class="flex-1 min-w-0 truncate">
                                                                <span
                                                                    class="text-[14px] font-semibold text-slate-900">{{ $worker->name }}</span>
                                                            </div>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </section>
                                        @endforeach
                                    @endif
                                </div>

                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endcan

        {{-- Reject Modal --}}
        @if ($canReject)
            <div id="rejectModal"
                class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
                <div class="relative z-[10000] w-full max-w-xl rounded-2xl border {{ $line }} bg-white ">
                    <div class="flex items-center justify-between border-b {{ $line }} px-4 py-3">
                        <div class="text-sm font-semibold text-rose-600">ไม่รับเรื่อง</div>
                        <x-ui.button id="closeRejectModalBtn" variant="ghost" size="icon" icon="close" aria-label="ปิด" />
                    </div>
                    <form method="POST" action="{{ route('maintenance.requests.reject', $req->id) }}"
                        class="px-4 py-4 space-y-4" data-dirty-check="true">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-slate-700">ระบุเหตุผลที่ไม่รับเรื่อง <span
                                    class="text-rose-600">*</span></label>
                            <textarea name="reject_reason" rows="3" required style="min-height:unset;height:auto;"
                                class="mt-2 w-full rounded-md border {{ $line }} bg-white px-3 py-2 text-sm resize-none overflow-hidden
                               focus:border-rose-500 focus:ring-2 focus:ring-rose-100"
                                placeholder="เช่น ข้อมูลไม่ครบถ้วน, แจ้งซ้ำ, หรือไม่ใช่หน้าที่ของทีมเจ้าหน้าที่..."></textarea>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <x-ui.button id="cancelRejectModalBtn">ยกเลิก</x-ui.button>
                            <x-ui.button type="submit" variant="danger">ยืนยันการไม่รับเรื่อง</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Cancel Modal --}}
        @if ($canCancel)
            <div id="cancelModal"
                class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
                <div class="relative z-[10000] w-full max-w-xl rounded-2xl border {{ $line }} bg-white ">
                    <div class="flex items-center justify-between border-b {{ $line }} px-4 py-3">
                        <div class="text-sm font-semibold text-slate-600">ยกเลิกการซ่อมบำรุง</div>
                        <x-ui.button id="closeCancelModalBtn" variant="ghost" size="icon" icon="close" aria-label="ปิด" />
                    </div>
                    <form method="POST" action="{{ route('maintenance.requests.cancel', $req->id) }}"
                        class="px-4 py-4 space-y-4" data-dirty-check="true">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-slate-700">ระบุเหตุผลการยกเลิก <span
                                    class="text-rose-600">*</span></label>
                            <textarea name="cancel_reason" rows="3" required style="min-height:unset;height:auto;"
                                class="mt-2 w-full rounded-md border {{ $line }} bg-white px-3 py-2 text-sm resize-none overflow-hidden
                               focus:border-slate-500 focus:ring-2 focus:ring-slate-100"
                                placeholder="เช่น แจ้งผิดหน่วยงาน, ซ่อมเองได้แล้ว, หรือขอยกเลิกรายการนี้..."></textarea>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <x-ui.button id="cancelCancelModalBtn">ปิด</x-ui.button>
                            <x-ui.button type="submit" variant="neutral">ยืนยันการยกเลิกการซ่อมบำรุง</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Hold Modal --}}
        @if ($canHold)
            <div id="holdModal"
                class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
                <div class="relative z-[10000] w-full max-w-xl rounded-2xl border {{ $line }} bg-white ">
                    <div class="flex items-center justify-between border-b {{ $line }} px-4 py-3">
                        <div class="text-sm font-semibold text-slate-900">พักชั่วคราว</div>
                        <x-ui.button id="closeHoldModalBtn" variant="ghost" size="icon" icon="close" aria-label="ปิด" />
                    </div>
                    <form method="POST" action="{{ route('maintenance.requests.hold', $req->id) }}"
                        class="px-4 py-4 space-y-4" data-dirty-check="true">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-slate-700">ระบุเหตุผลในการพักชั่วคราว <span
                                    class="text-amber-600">*</span></label>
                            <textarea name="note" rows="3" required style="min-height:unset;height:auto;"
                                class="mt-2 w-full rounded-md border {{ $line }} bg-white px-3 py-2 text-sm resize-none overflow-hidden
                           focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
                                placeholder="เช่น รออะไหล่, รอเบิกเครื่องมือ, หรือเหตุผลอื่น ๆ..."></textarea>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <x-ui.button id="cancelHoldModalBtn">ยกเลิก</x-ui.button>
                            <x-ui.button type="submit" variant="warning">ยืนยันการพักชั่วคราว</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Resolve Modal --}}
        @if ($canResolve)
            <div id="resolveModal"
                class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
                <div class="relative z-[10000] w-full max-w-xl rounded-2xl border {{ $line }} bg-white ">
                    <div class="flex items-center justify-between border-b {{ $line }} px-4 py-3">
                        <div class="text-sm font-semibold text-slate-900">ซ่อมบำรุงเสร็จสิ้น</div>
                        <x-ui.button id="closeResolveModalBtn" variant="ghost" size="icon" icon="close" aria-label="ปิด" />
                    </div>
                    <form method="POST" action="{{ route('maintenance.requests.resolve', $req->id) }}"
                        class="px-4 py-4 space-y-4" data-dirty-check="true">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-slate-700">ระบุรายละเอียดการแก้ปัญหา <span
                                    class="text-emerald-600">*</span></label>
                            <textarea name="resolution_note" rows="3" required style="min-height:unset;height:auto;"
                                class="mt-2 w-full rounded-md border {{ $line }} bg-white px-3 py-2 text-sm resize-none overflow-hidden
                               focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                placeholder="เช่น เปลี่ยนอะไหล่, ซ่อมแผงวงจรสำเร็จ, ผ่านการสอบเทียบแล้ว..."></textarea>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <x-ui.button id="cancelResolveModalBtn">ยกเลิก</x-ui.button>
                            <x-ui.button type="submit" variant="primary">ยืนยันซ่อมบำรุงเสร็จสิ้น</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- History Modal --}}
        <div id="historyModal"
            class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
            <div
                class="relative z-[10000] w-full max-w-2xl rounded-2xl border {{ $line }} bg-white overflow-hidden animate-in fade-in zoom-in duration-200">
                {{-- Header: same icon / text sizing as the assign-team dialog --}}
                <div class="flex items-center justify-between border-b {{ $line }} px-6 py-4">
                    <div class="flex items-start gap-3 min-w-0">
                        <span class="mt-0.5 inline-flex h-10 w-10 items-center justify-center text-slate-800">
                            <span class="material-symbols-outlined text-[36px]">history</span>
                        </span>
                        <div class="min-w-0">
                            <div class="text-[16px] font-semibold text-slate-900 leading-tight">ประวัติการดำเนินงาน</div>
                            <p class="text-[13px] text-slate-500">Operation & Status History Log</p>
                        </div>
                    </div>
                    <x-ui.button id="closeHistoryModalBtn" variant="ghost" size="icon" icon="close" aria-label="ปิด" />
                </div>

                <div class="px-6 py-6 max-h-[60vh] overflow-y-auto bg-white custom-scrollbar-indigo">
                    @include('maintenance.requests.partials._timeline')
                </div>

                <div class="flex justify-end border-t {{ $line }} px-6 py-4 bg-slate-50/50">
                    <x-ui.button id="cancelHistoryModalBtn">ปิดหน้าต่าง</x-ui.button>
                </div>
            </div>
        </div>

        {{-- Post-Close Action Modal --}}
        @if (session('show_post_close_modal'))
            <div id="postCloseModal"
                class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/60 backdrop-blur-md p-4">
                <div
                    class="relative z-[10000] w-full max-w-md transform transition-all animate-in fade-in zoom-in duration-300">
                    <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white ">
                        {{-- Icon Header --}}
                        <div class="bg-slate-50 px-6 py-8 text-center border-b border-slate-100">
                            <div
                                class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 mb-4 ">
                                <span class="material-symbols-outlined text-[48px]">check_circle</span>
                            </div>
                            <h3 class="text-xl font-bold text-slate-900">อนุมัติผลการซ่อมบำรุงเรียบร้อยแล้ว!</h3>
                            <p class="mt-2 text-sm text-slate-500">ขอบคุณที่ใช้บริการครับ คุณต้องการดำเนินการอย่างไรต่อ?
                            </p>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="p-6 flex flex-wrap justify-center gap-3">
                            @can('rate', $req)
                                <x-ui.button variant="warning" icon="star" :href="route('maintenance.requests.rating.create', $req->id)">ประเมินความพึงพอใจ</x-ui.button>
                            @endcan

                            <x-ui.button icon="visibility"
                                onclick="document.getElementById('postCloseModal').remove(); document.body.style.overflow = '';">
                                ดูรายละเอียดใบงาน
                            </x-ui.button>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                document.body.style.overflow = 'hidden';
            </script>
        @endif

        {{-- Rating Popup Modal --}}
        <div x-show="ratingOpen" class="fixed inset-0 z-[9999] overflow-y-auto" style="display: none;"
            x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">

            <div class="flex items-center justify-center min-h-screen p-4">
                {{-- Backdrop --}}
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="ratingOpen = false"></div>

                {{-- Modal Content --}}
                <div class="relative bg-white rounded-sm max-w-lg w-full overflow-hidden transform transition-all"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100">

                    {{-- Header --}}
                    <div class="px-8 py-6 border-b border-slate-100 flex justify-between items-center bg-white">
                        <div class="flex items-center gap-4">
                            <div class="flex flex-col">
                                <h3 class="text-[18px] font-bold text-slate-900 tracking-tight">ประเมินความพึงพอใจ</h3>
                                <p class="text-[12px] text-slate-400 font-bold tracking-widest uppercase mt-0.5">
                                    เลขที่ใบงาน
                                    #{{ $req->request_no ?? $req->id }}</p>
                            </div>
                        </div>
                        <x-ui.button @click="ratingOpen = false" variant="ghost" size="icon" icon="close" aria-label="ปิด" />
                    </div>

                    {{-- Form --}}
                    <form action="{{ route('maintenance.requests.rating.store', $req) }}" method="POST" class="p-8"
                        data-dirty-check="true">
                        @csrf
                        <div class="mb-8">
                            <div class="text-[14px] font-bold text-slate-700 uppercase tracking-tight mb-2">
                                หัวข้อการแจ้งซ่อม</div>
                            <p class="text-[16px] font-bold text-slate-800 tracking-tight leading-relaxed">
                                {{ $req->title }}</p>
                        </div>

                        {{-- Star Rating --}}
                        <div class="mb-10 text-center">
                            <label
                                class="block text-[15px] font-bold text-slate-800 mb-6">คุณพึงพอใจกับงานซ่อมนี้แค่ไหน?</label>
                            <div class="flex flex-row-reverse justify-center gap-2">
                                @for ($i = 5; $i >= 1; $i--)
                                    <input type="radio" id="star{{ $i }}" name="score"
                                        value="{{ $i }}" class="hidden peer" required>
                                    <label for="star{{ $i }}"
                                        class="cursor-pointer text-slate-300 hover:text-amber-400 peer-hover:text-amber-400 peer-checked:text-amber-500 transition-all transform hover:scale-125">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" viewBox="0 0 20 20"
                                            fill="currentColor">
                                            <path
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                        </svg>
                                    </label>
                                @endfor
                            </div>
                            @error('score')
                                <p class="mt-2 text-rose-500 text-[12px] font-bold">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-8 p-1 bg-slate-50 rounded-sm border border-slate-100">
                            <textarea name="comment" rows="3" style="min-height:unset;height:auto;"
                                class="w-full bg-white rounded-sm border-none text-[14px] focus:ring-0 placeholder-slate-300 resize-none overflow-hidden p-4"
                                placeholder="แชร์ประสบการณ์ของคุณเพื่อการพัฒนาบริการ...">{{ old('comment') }}</textarea>
                            @error('comment')
                                <p class="mt-2 text-rose-500 text-[12px] font-bold px-4 pb-2">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex justify-end gap-3">
                            <x-ui.button @click="ratingOpen = false">ยกเลิก</x-ui.button>
                            <x-ui.button type="submit" variant="brand">บันทึกการประเมิน</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function() {
            'use strict';

            // --- 1. ประกาศตัวแปร (Selectors) ---
            const modal = document.getElementById('assignModal');
            const open = document.getElementById('openAssignModalBtn');
            const close = document.getElementById('closeAssignModalBtn');
            const cancel = document.getElementById('cancelAssignModalBtn');

            if (modal && open) {
                const searchInput = document.getElementById('assignSearch');
                const roleFilter = document.getElementById('assignRoleFilter');
                const suggestRole = (document.getElementById('assignSuggestRole')?.value || '').trim().toLowerCase();
                const visibleCountEl = document.getElementById('assignVisibleCount');
                const hintEl = document.getElementById('assignSuggestHint');
                const selectAllBtn = document.getElementById('assignSelectAllBtn');
                const clearAllBtn = document.getElementById('assignClearAllBtn');
                const selectedMetaEl = document.getElementById('assignSelectedMeta');
                const selectedEmptyEl = document.getElementById('assignSelectedEmpty');
                const selectedListEl = document.getElementById('assignSelectedList');
                const assignForm = modal.querySelector('form');

                // --- 2. ฟังก์ชันช่วยงาน (Helper Functions) ---

                const getAllRows = () => Array.from(modal.querySelectorAll('.assign-user-row'));
                const getAllCheckboxes = () => Array.from(modal.querySelectorAll('.assign-user-checkbox'));

                // ดึงเฉพาะ Checkbox ของแถวที่กำลังแสดงอยู่ (ไม่โดน Filter ซ่อน)
                const getVisibleCheckboxes = () => getAllRows()
                    .filter(row => row.style.display !== 'none')
                    .map(row => row.querySelector('.assign-user-checkbox'))
                    .filter(Boolean);

                // ป้องกัน XSS
                const escapeHtml = (str) => String(str || '')
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

                // ทำอักษรย่อ (เช่น "สมชาย ดีใจ" -> "ส ด")
                const initials = (name) => {
                    const s = (name || '').trim();
                    if (!s) return '—';
                    const parts = s.split(/\s+/).filter(Boolean);
                    const a = (parts[0] || '').charAt(0);
                    const b = parts.length > 1 ? (parts[parts.length - 1] || '').charAt(0) : '';
                    return (a + b).toUpperCase();
                };

                // --- 3. ฟังก์ชันหลักของระบบ (Core Logic) ---

                // อัปเดตรายชื่อคนที่ถูกเลือกใน Sidebar ด้านซ้าย
                function updateSelectedList() {
                    if (!selectedListEl || !selectedEmptyEl || !selectedMetaEl) return;

                    const checked = getAllCheckboxes().filter(cb => cb.checked);
                    selectedMetaEl.textContent = checked.length + ' คน';
                    selectedListEl.innerHTML = '';

                    if (checked.length === 0) {
                        selectedEmptyEl.classList.remove('hidden');
                        selectedListEl.classList.add('hidden');
                        return;
                    }

                    selectedEmptyEl.classList.add('hidden');
                    selectedListEl.classList.remove('hidden');

                    checked.forEach(cb => {
                        const row = cb.closest('.assign-user-row');
                        const displayName = row?.getAttribute('data-display-name') || '';
                        const roleLabel = row?.getAttribute('data-role-label') || '';
                        const ini = initials(displayName);
                        const userId = cb.value;

                        const item = document.createElement('div');
                        item.className =
                            'flex items-center gap-3 rounded-md border border-slate-200 bg-white px-3 py-2 animate-in fade-in slide-in-from-left-2 duration-200';
                        item.dataset.userId = userId;

                        item.innerHTML = `
                    <div class="h-8 w-8 rounded-full bg-slate-800 text-white grid place-items-center text-[11px] font-bold flex-shrink-0 ">
                        ${escapeHtml(ini)}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-[12px] font-bold text-slate-900 leading-tight" title="${escapeHtml(displayName)}">${escapeHtml(displayName)}</div>
                        <div class="truncate text-[10px] text-slate-500 uppercase tracking-tighter" title="${escapeHtml(roleLabel)}">${escapeHtml(roleLabel)}</div>
                    </div>
                    <button type="button" class="assign-chip-remove flex-shrink-0 inline-flex items-center justify-center h-6 w-6 rounded-full text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition-colors" data-user-id="${escapeHtml(userId)}" title="ลบออก">
                        <svg class="h-3.5 w-3.5 pointer-events-none" viewBox="0 0 24 24" fill="none">
                            <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                        </svg>
                    </button>
                `;
                        selectedListEl.appendChild(item);
                    });
                }

                function updateCounts() {
                    const visible = getAllRows().filter(r => r.style.display !== 'none').length;
                    if (visibleCountEl) visibleCountEl.textContent = `(${visible})`;
                    updateSelectedList();
                }

                // ระบบกรองชื่อและตำแหน่ง
                function applyFilter() {
                    const q = (searchInput?.value || '').trim().toLowerCase();
                    const r = (roleFilter?.value || '').trim().toLowerCase();

                    getAllRows().forEach(row => {
                        const name = (row.getAttribute('data-name') || '').toLowerCase().trim();
                        const role = (row.getAttribute('data-role') || '').toLowerCase().trim();
                        const okName = !q || name.includes(q);
                        const okRole = !r || role === r;
                        row.style.display = (okName && okRole) ? '' : 'none';
                    });

                    // อัปเดตการแสดงผล Hint
                    if (hintEl) {
                        if (r === suggestRole && r !== '') {
                            hintEl.classList.remove('hidden');
                        } else {
                            hintEl.classList.add('hidden');
                        }
                    }

                    modal.querySelectorAll('[data-role-group]').forEach(group => {
                        const inner = Array.from(group.querySelectorAll('.assign-user-row'));
                        group.style.display = inner.some(x => x.style.display !== 'none') ? '' : 'none';
                    });
                    updateCounts();
                }

                // --- 4. ควบคุมการแสดงผล Modal ---

                function showModal() {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    document.body.style.overflow = 'hidden';

                    // คืนค่า Checkbox ตามค่าเริ่มต้นใน HTML (ช่วยกรณีผู้ใช้กด "ล้าง" แต่ไม่ได้กดบันทึกแล้วปิดหน้าต่างไป)
                    getAllCheckboxes().forEach(cb => {
                        cb.checked = cb.defaultChecked;
                    });

                    // ล้างช่องค้นหา
                    if (searchInput) searchInput.value = '';

                    // รีเซ็ตตัวกรองตำแหน่งกลับไปที่ทีมที่แนะนำ (หรือ "ทั้งหมด" หากไม่มีคำแนะนำ)
                    if (roleFilter) {
                        if (suggestRole) {
                            const hasOption = Array.from(roleFilter.options)
                                .some(opt => (opt.value || '').toLowerCase().trim() === suggestRole);
                            roleFilter.value = hasOption ? suggestRole : '';
                        } else {
                            roleFilter.value = '';
                        }
                    }

                    applyFilter();
                }

                function hideModal() {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    document.body.style.overflow = '';
                }

                // --- 5. Event Listeners ---

                open.addEventListener('click', showModal);
                close?.addEventListener('click', hideModal);
                cancel?.addEventListener('click', hideModal);

                // คลิกพื้นหลังเทาๆ ให้ปิด Modal
                modal.addEventListener('click', e => {
                    if (e.target === modal) hideModal();
                });

                // ปุ่มเลือกทั้งหมด (เฉพาะที่เห็นอยู่)
                selectAllBtn?.addEventListener('click', () => {
                    getVisibleCheckboxes().forEach(cb => cb.checked = true);
                    updateCounts();
                });

                // ล้างที่เลือกไว้ทั้งหมด
                clearAllBtn?.addEventListener('click', () => {
                    getAllCheckboxes().forEach(cb => cb.checked = false);
                    updateCounts();
                });

                searchInput?.addEventListener('input', applyFilter);
                searchInput?.addEventListener('keyup', applyFilter);
                roleFilter?.addEventListener('change', applyFilter);

                // เมื่อมีการติ๊ก Checkbox ในลิสต์
                modal.addEventListener('change', e => {
                    if (e.target?.classList?.contains('assign-user-checkbox')) updateCounts();
                });

                // ลบชิปจาก Sidebar
                selectedListEl?.addEventListener('click', e => {
                    const btn = e.target.closest('.assign-chip-remove');
                    if (!btn) return;
                    const userId = btn.dataset.userId;
                    const cb = modal.querySelector(`.assign-user-checkbox[value="${CSS.escape(userId)}"]`);
                    if (cb) cb.checked = false;
                    updateCounts();
                });

                // จัดการตอนกด Submit
                assignForm?.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML =
                            '<span class="animate-spin mr-2 text-[14px]">◌</span> กำลังบันทึก...';
                    }
                });

                // รันครั้งแรกเพื่อตั้งค่าเริ่มต้น
                updateCounts();
            }

            // --- 6. Reject Modal Logic (หน้าต่างปฏิเสธงาน) ---
            const rejectModal = document.getElementById('rejectModal');
            const openRejectBtn = document.getElementById('openRejectModalBtn');
            const closeRejectBtn = document.getElementById('closeRejectModalBtn');
            const cancelRejectBtn = document.getElementById('cancelRejectModalBtn');

            if (rejectModal && openRejectBtn) {
                const showReject = () => {
                    rejectModal.classList.remove('hidden');
                    rejectModal.classList.add('flex');
                    document.body.style.overflow = 'hidden';
                };
                const hideReject = () => {
                    rejectModal.classList.add('hidden');
                    rejectModal.classList.remove('flex');
                    document.body.style.overflow = '';
                };
                openRejectBtn.addEventListener('click', showReject);
                closeRejectBtn?.addEventListener('click', hideReject);
                cancelRejectBtn?.addEventListener('click', hideReject);
                rejectModal.addEventListener('click', e => {
                    if (e.target === rejectModal) hideReject();
                });
            }

            // --- 6.1 Cancel Modal Logic (หน้าต่างยกเลิกงาน) ---
            const cancelModal = document.getElementById('cancelModal');
            const openCancelBtn = document.getElementById('openCancelModalBtn');
            const closeCancelBtn = document.getElementById('closeCancelModalBtn');
            const cancelCancelBtn = document.getElementById('cancelCancelModalBtn');

            if (cancelModal && openCancelBtn) {
                const showCancel = () => {
                    cancelModal.classList.remove('hidden');
                    cancelModal.classList.add('flex');
                    document.body.style.overflow = 'hidden';
                };
                const hideCancel = () => {
                    cancelModal.classList.add('hidden');
                    cancelModal.classList.remove('flex');
                    document.body.style.overflow = '';
                };
                openCancelBtn.addEventListener('click', showCancel);
                closeCancelBtn?.addEventListener('click', hideCancel);
                cancelCancelBtn?.addEventListener('click', hideCancel);
                cancelModal.addEventListener('click', e => {
                    if (e.target === cancelModal) hideCancel();
                });
            }

            // --- 7. Hold Modal Logic ---
            const holdModal = document.getElementById('holdModal');
            const openHoldBtn = document.getElementById('openHoldModalBtn');
            const closeHoldBtn = document.getElementById('closeHoldModalBtn');
            const cancelHoldBtn = document.getElementById('cancelHoldModalBtn');

            if (holdModal && openHoldBtn) {
                const showHold = () => {
                    holdModal.classList.remove('hidden');
                    holdModal.classList.add('flex');
                    document.body.style.overflow = 'hidden';
                };
                const hideHold = () => {
                    holdModal.classList.add('hidden');
                    holdModal.classList.remove('flex');
                    document.body.style.overflow = '';
                };
                openHoldBtn.addEventListener('click', showHold);
                closeHoldBtn?.addEventListener('click', hideHold);
                cancelHoldBtn?.addEventListener('click', hideHold);
                holdModal.addEventListener('click', e => {
                    if (e.target === holdModal) hideHold();
                });
            }

            // --- 8. Resolve Modal Logic ---
            const resolveModal = document.getElementById('resolveModal');
            const openResolveBtn = document.getElementById('openResolveModalBtn');
            const closeResolveBtn = document.getElementById('closeResolveModalBtn');
            const cancelResolveBtn = document.getElementById('cancelResolveModalBtn');

            if (resolveModal && openResolveBtn) {
                const showResolve = () => {
                    resolveModal.classList.remove('hidden');
                    resolveModal.classList.add('flex');
                    document.body.style.overflow = 'hidden';
                };
                const hideResolve = () => {
                    resolveModal.classList.add('hidden');
                    resolveModal.classList.remove('flex');
                    document.body.style.overflow = '';
                };
                openResolveBtn.addEventListener('click', showResolve);
                closeResolveBtn?.addEventListener('click', hideResolve);
                cancelResolveBtn?.addEventListener('click', hideResolve);
                resolveModal.addEventListener('click', e => {
                    if (e.target === resolveModal) hideResolve();
                });
            }

            // --- 9. History Modal Logic ---
            const historyModal = document.getElementById('historyModal');
            const openHistoryBtn = document.getElementById('openHistoryModalBtn');
            const closeHistoryBtn = document.getElementById('closeHistoryModalBtn');
            const cancelHistoryBtn = document.getElementById('cancelHistoryModalBtn');

            if (historyModal && openHistoryBtn) {
                const showHistory = () => {
                    historyModal.classList.remove('hidden');
                    historyModal.classList.add('flex');
                    document.body.style.overflow = 'hidden';
                };
                const hideHistory = () => {
                    historyModal.classList.add('hidden');
                    historyModal.classList.remove('flex');
                    document.body.style.overflow = '';
                };
                openHistoryBtn.addEventListener('click', showHistory);
                closeHistoryBtn?.addEventListener('click', hideHistory);
                cancelHistoryBtn?.addEventListener('click', hideHistory);
                historyModal.addEventListener('click', e => {
                    if (e.target === historyModal) hideHistory();
                });
            }
        })();
    </script>
@endpush

@extends('layouts.app')
@section('title', 'งานของฉัน')

@section('content')
    @php
        use Illuminate\Support\Str;

        $q = $q ?? request('q');
        $status = $status ?? request('status');
        $filter = $filter ?? (request('filter') ?: 'all');
        $tech = $tech ?? request('tech');
        $resp = $resp ?? request('resp');

        $myJobsRoute = 'repairs.my_jobs';

        $filterLabels = [
            'my' => 'งานของฉัน',
            'available' => 'งานว่าง',
            'all' => 'ทั้งหมด',
        ];

        $statusLabel = fn(?string $s) => [
            'pending' => 'รอดำเนินการ',
            'acknowledged' => 'รับทราบแล้ว',
            'accepted' => 'รับเรื่องแล้ว',
            'in_progress' => 'กำลังดำเนินการ',
            'on_hold' => 'พักไว้ชั่วคราว',
            'resolved' => 'ซ่อมบำรุงเสร็จสิ้น',
            'closed' => 'อนุมัติผลการซ่อมบำรุง',
            'cancelled' => 'ยกเลิกการซ่อมบำรุง',
            'rejected' => 'ไม่รับเรื่อง',
        ][strtolower((string) $s)] ?? 'ไม่ระบุ';

        $respLabel = fn(?string $s) => [
            'pending' => 'ยังไม่ตอบรับ',
            'accepted' => 'รับเรื่อง',
            'rejected' => 'ไม่รับเรื่อง',
            'acknowledged' => 'รับทราบแล้ว',
        ][strtolower((string) $s)] ?? 'ไม่ระบุ';

        $statusDot = fn(?string $s) => match (strtolower((string) $s)) {
            'pending' => 'bg-amber-500',
            'acknowledged' => 'bg-sky-500',
            'accepted' => 'bg-emerald-500',
            'in_progress' => 'bg-blue-500',
            'on_hold' => 'bg-slate-400',
            'resolved' => 'bg-emerald-600',
            'closed' => 'bg-emerald-700',
            'cancelled' => 'bg-rose-500',
            'rejected' => 'bg-rose-600',
            default => 'bg-slate-400',
        };

        $statusAccentColor = fn(?string $s) => match (strtolower((string) $s)) {
            'pending' => '#f59e0b',
            'acknowledged' => '#38bdf8',
            'accepted' => '#10b981',
            'in_progress' => '#3b82f6',
            'on_hold' => '#94a3b8',
            'resolved' => '#059669',
            'closed' => '#065f46',
            'cancelled' => '#ef4444',
            'rejected' => '#e11d48',
            default => '#cbd5e1',
        };

        $hasActiveFilter =
            ($q ?? '') !== '' ||
            ($status ?? '') !== '' ||
            ($tech ?? '') !== '' ||
            ($filter ?? 'all') !== 'all' ||
            ($resp ?? '') !== '';

        $activeTech = isset($tech) && isset($team) ? $team->firstWhere('id', (int) $tech) : null;

        $statusIcon = fn(?string $s) => match (strtolower((string) $s)) {
            'pending' => 'hourglass_empty',
            'acknowledged' => 'visibility',
            'accepted' => 'thumb_up',
            'in_progress' => 'autorenew',
            'on_hold' => 'pause_circle',
            'resolved' => 'task_alt',
            'closed' => 'verified',
            'cancelled' => 'cancel',
            'rejected' => 'error',
            default => 'info',
        };

        $statusBadge = fn(?string $s) => match (strtolower((string) $s)) {
            'pending' => 'text-amber-600',
            'acknowledged' => 'text-sky-600',
            'accepted' => 'text-emerald-600',
            'in_progress' => 'text-blue-600',
            'on_hold' => 'text-slate-500',
            'resolved' => 'text-emerald-700',
            'closed' => 'text-emerald-800',
            'cancelled' => 'text-rose-600',
            'rejected' => 'text-rose-700',
            default => 'text-slate-500',
        };

        $statPending = (int) ($stats['pending'] ?? 0);
        $statAcknowledged = (int) ($stats['acknowledged'] ?? 0);
        $statAccepted = (int) ($stats['accepted'] ?? 0);
        $statInProgress = (int) ($stats['in_progress'] ?? 0);
        $statOnHold = (int) ($stats['on_hold'] ?? 0);
        $statResolved = (int) ($stats['resolved'] ?? 0);
        $statClosed = (int) ($stats['closed'] ?? 0);

        // Active work for donut (Accepted + Progress + Resolved + Closed)
        $totalCompleted = $statResolved + $statClosed;
        $totalWorkload =
            $statPending + $statAcknowledged + $statAccepted + $statInProgress + $statOnHold + $totalCompleted;
        $pct = $totalWorkload > 0 ? round(($totalCompleted / $totalWorkload) * 100) : 0;

        $total = $totalWorkload;
        $p1 = $total > 0 ? ($statPending / $total) * 360 : 0;
        $p2 = $p1 + ($total > 0 ? ($statAcknowledged / $total) * 360 : 0);
        $p3 = $p2 + ($total > 0 ? ($statAccepted / $total) * 360 : 0);
        $p4 = $p3 + ($total > 0 ? ($statInProgress / $total) * 360 : 0);
        $p5 = $p4 + ($total > 0 ? ($statOnHold / $total) * 360 : 0);
        $p6 = $p5 + ($total > 0 ? ($statResolved / $total) * 360 : 0);
        $p7 = $p6 + ($total > 0 ? ($statClosed / $total) * 360 : 0);

        $getIp = fn($r) => $r->client_ip ?? ($r->ip_address ?? ($r->ip ?? null));
        $getDept = fn($r) => $r->department->name ?? ($r->department->name_th ?? ($r->department->title ?? null));

        $getWorkOrderNo = function ($r) {
            return $r->work_order_no ?? ($r->workOrder->order_no ?? (null ?? ($r->job_no ?? null)));
        };

        $avatarColors = [
            'bg-indigo-500',
            'bg-emerald-500',
            'bg-amber-500',
            'bg-rose-500',
            'bg-sky-500',
            'bg-violet-500',
            'bg-teal-500',
        ];

        $u = auth()->user();
        $uRole = strtolower((string) ($u?->role ?? ''));
        $isAdmin =
            (bool) ($u?->is_admin ?? false) ||
            in_array($uRole, ['admin', 'developer'], true) ||
            (method_exists($u, 'hasRole') && ($u->hasRole('admin') || $u->hasRole('developer')));
    @endphp

    <style>
        /* ป้องกัน Dropdown ในบัตรงานโดนกล่องอื่นทับ */
        .job-card { position: relative !important; transition: z-index 0.1s step-end; }
        .job-card:focus-within, .job-card:hover { z-index: 50 !important; }
        .ts-dropdown { z-index: 1000 !important; }
    </style>
    
    <div class="w-full flex flex-col pb-6 md:pb-8 font-sans text-slate-900" id="myJobsRealtime">

        {{-- Sticky Header --}}
        <div class="sticky top-16 z-20 bg-white border-b border-slate-200" x-data="{ showStats: window.innerWidth >= 1024, showFilters: window.innerWidth >= 1024 }">
            <div class="px-4 md:px-6 lg:px-8 py-4">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                        <div class="flex flex-col gap-3">
                            <div class="flex items-start gap-3">
                                <span class="material-symbols-outlined text-[32px] text-[#0F2D5C] mt-0.5"
                                    aria-hidden="true">engineering</span>
                                <div>
                                    <h1 class="text-[17px] font-semibold text-slate-900">
                                        @if ($activeTech && $activeTech->id !== auth()->id())
                                            งานของ {{ $activeTech->name }}
                                        @else
                                            งานของฉัน
                                        @endif
                                    </h1>
                                    <p class="text-[13px] text-slate-600">
                                        รายการงานซ่อมบำรุงที่ต้องจัดการ
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Mobile Toggle Buttons --}}
                        <div class="flex lg:hidden gap-2" x-cloak>
                            <button type="button" @click="showStats = !showStats"
                                class="flex-1 inline-flex justify-center items-center gap-1.5 h-9 rounded-md border text-[13px] font-medium transition-colors"
                                :class="showStats ? 'bg-slate-100 border-slate-300 text-slate-800' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'">
                                <span class="material-symbols-outlined text-[16px]">pie_chart</span>
                                <span x-text="showStats ? 'ซ่อนสถิติ' : 'ดูสถิติ'"></span>
                            </button>
                            <button type="button" @click="showFilters = !showFilters"
                                class="flex-1 inline-flex justify-center items-center gap-1.5 h-9 rounded-md border text-[13px] font-medium transition-colors"
                                :class="showFilters ? 'bg-slate-100 border-slate-300 text-slate-800' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'">
                                <span class="material-symbols-outlined text-[16px]">filter_list</span>
                                <span x-text="showFilters ? 'ซ่อนตัวกรอง' : 'ตัวกรอง'"></span>
                            </button>
                        </div>
                    </div>

                    <div x-show="showStats" x-transition.opacity.duration.200ms class="flex flex-wrap items-center gap-x-5 gap-y-3 text-[13px] mt-1 lg:mt-0 pb-1 border-b lg:border-none border-slate-100" style="display: none;">
                        <div class="flex items-center gap-1.5">
                            <span class="h-2 w-2 rounded-full bg-amber-500 ring-2 ring-amber-100"></span>
                            <span class="text-slate-600">รอดำเนินการ:</span>
                            <span id="stat-pending" class="font-bold text-slate-900">{{ $statPending }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="h-2 w-2 rounded-full bg-blue-500 ring-2 ring-blue-100"></span>
                            <span class="text-slate-600">รับทราบแล้ว:</span>
                            <span id="stat-acknowledged" class="font-bold text-slate-900">{{ $statAcknowledged }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-indigo-500"></span>
                            <span class="text-slate-600">รับเรื่องแล้ว:</span>
                            <span id="stat-accepted" class="font-bold text-slate-900">{{ $statAccepted }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-sky-500"></span>
                            <span class="text-slate-600">กำลังดำเนินการ:</span>
                            <span id="stat-in-progress" class="font-bold text-slate-900">{{ $statInProgress }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-slate-400"></span>
                            <span class="text-slate-600">พักไว้ชั่วคราว:</span>
                            <span id="stat-on-hold" class="font-bold text-rose-500">{{ $statOnHold }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                            <span class="text-slate-600">ซ่อมบำรุงเสร็จสิ้น:</span>
                            <span id="stat-resolved" class="font-bold text-slate-900">{{ $statResolved }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 rounded-full bg-emerald-600"></span>
                            <span class="text-slate-600">อนุมัติผลการซ่อมบำรุง:</span>
                            <span id="stat-closed" class="font-bold text-emerald-600">{{ $statClosed }}</span>
                        </div>
                        <div class="flex items-center gap-2 pl-3 border-l border-slate-200 ml-1">
                            <div class="relative w-8 h-8">
                                <div id="donut" class="w-full h-full rounded-full"
                                    style="background: conic-gradient(
                                        #f59e0b 0deg {{ $p1 }}deg,
                                        #38bdf8 {{ $p1 }}deg {{ $p2 }}deg,
                                        #6366f1 {{ $p2 }}deg {{ $p3 }}deg,
                                        #3b82f6 {{ $p3 }}deg {{ $p4 }}deg,
                                        #94a3b8 {{ $p4 }}deg {{ $p5 }}deg,
                                        #10b981 {{ $p5 }}deg {{ $p6 }}deg,
                                        #065f46 {{ $p6 }}deg {{ $p7 }}deg,
                                        #e2e8f0 {{ $p7 }}deg 360deg
                                    );">
                                </div>
                                <div class="absolute inset-0 m-auto w-5 h-5 rounded-full bg-white"></div>
                            </div>
                            <span id="donutPct" class="font-bold text-[11px] text-slate-700">{{ $pct }}%</span>
                        </div>
                    </div>
                </div>

                <div x-show="showFilters" x-transition.opacity.duration.200ms style="display: none;">
                    <form method="GET" action="{{ route($myJobsRoute) }}"
                        class="mt-4 grid grid-cols-1 md:grid-cols-12 gap-3 items-end" onsubmit="showLoader()">

                        <div class="md:col-span-12 lg:col-span-3 min-w-0">
                            <label for="q" class="mb-1 block text-[12px] text-slate-600">คำค้นหา</label>
                            <div class="relative">
                                <input id="q" type="text" name="q" value="{{ $q }}"
                                    placeholder="ค้นหาเลขใบงาน, เรื่อง, หรือสถานที่..."
                                    class="w-full rounded-md border border-slate-200 bg-white pl-10 pr-3 py-2 text-[13px] placeholder:text-slate-400
                                           focus:outline-none focus:ring-2 focus:ring-[#0F2D5C]/35 focus:border-[#0F2D5C]/35">
                                <span
                                    class="pointer-events-none absolute inset-y-0 left-0 flex w-9 items-center justify-center text-slate-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                                            d="M21 21l-4.3-4.3M17 10a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </span>
                            </div>
                        </div>

                        <div class="md:col-span-3 lg:col-span-2">
                            <label for="filter" class="mb-1 block text-[12px] text-slate-600">ช่วงงาน</label>
                            <select id="filter" name="filter"
                                class="w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-[13px] text-slate-800
                                       focus:outline-none focus:ring-2 focus:ring-[#0F2D5C]/35 focus:border-[#0F2D5C]/35 cursor-pointer"
                                onchange="this.form.submit()">
                                @foreach ($filterLabels as $key => $label)
                                    <option value="{{ $key }}" @selected($filter === $key)>{{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="md:col-span-3 lg:col-span-2">
                            <label for="status" class="mb-1 block text-[12px] text-slate-600">สถานะใบงาน</label>
                            <select id="status" name="status"
                                class="w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-[13px] text-slate-800
                                       focus:outline-none focus:ring-2 focus:ring-[#0F2D5C]/35 focus:border-[#0F2D5C]/35 cursor-pointer"
                                onchange="this.form.submit()">
                                <option value="">ทุกสถานะ</option>
                                @foreach (['pending', 'acknowledged', 'accepted', 'in_progress', 'on_hold', 'resolved', 'closed', 'cancelled', 'rejected'] as $s)
                                    <option value="{{ $s }}" @selected($status === $s)>{{ $statusLabel($s) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="md:col-span-3 lg:col-span-2">
                            <label for="type" class="mb-1 block text-[12px] text-slate-600">ประเภทงาน</label>
                            <select id="type" name="type"
                                class="w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-[13px] text-slate-800
                                       focus:outline-none focus:ring-2 focus:ring-[#0F2D5C]/35 focus:border-[#0F2D5C]/35 cursor-pointer"
                                onchange="this.form.submit()">
                                <option value="">ทุกประเภท</option>
                                @foreach ($types as $t)
                                    <option value="{{ $t->id }}" @selected((int) $typeId === (int) $t->id)>{{ $t->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="md:col-span-3 lg:col-span-2">
                            <label for="resp" class="mb-1 block text-[12px] text-slate-600">การตอบรับของฉัน</label>
                            <select id="resp" name="resp"
                                class="w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-[13px] text-slate-800
                                       focus:outline-none focus:ring-2 focus:ring-[#0F2D5C]/35 focus:border-[#0F2D5C]/35 cursor-pointer"
                                onchange="this.form.submit()">
                                <option value="">ทั้งหมด</option>
                                @foreach (['pending', 'accepted', 'acknowledged', 'rejected'] as $s)
                                    <option value="{{ $s }}" @selected(($resp ?? '') === $s)>{{ $respLabel($s) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="md:col-span-12 lg:col-span-1 flex items-end justify-end gap-2 mt-2 lg:mt-0">
                            @if ($hasActiveFilter)
                                <a href="{{ route($myJobsRoute) }}" onclick="showLoader()"
                                    class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600
                                           hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-[#0F2D5C]/30 transition-transform hover:scale-105"
                                    title="ล้างตัวกรอง">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </a>
                            @endif
                            <button type="submit"
                                class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#0F2D5C] text-white
                                       hover:bg-[#0F2D5C]/90 focus:outline-none focus:ring-2 focus:ring-[#0F2D5C]/45 transition-transform hover:scale-105 active:scale-95"
                                title="ค้นหา">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21 21l-4.3-4.3M17 10a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </button>
                        </div>

                        <input type="hidden" name="filter" value="{{ $filter }}">
                        @if ($tech)
                            <input type="hidden" name="tech" value="{{ $tech }}">
                        @endif
                    </form>
                </div>
            </div>
        </div>

        {{-- List Header --}}
        <div class="px-4 md:px-6 lg:px-8 py-2 border-b border-slate-200 bg-slate-50/30">
            <div class="flex items-center justify-between">
                <div class="text-[13px] font-semibold text-slate-800">รายการงาน</div>
                <div class="text-[12px] text-slate-500">ทั้งหมด {{ $list->total() }} รายการ</div>
            </div>
        </div>

        {{-- Job Cards --}}
        <div class="w-full px-4 md:px-6 lg:px-8 relative z-0 mt-4">
            <div class="mx-auto space-y-3 max-w-[1400px]">

                @forelse($list as $r)
                    @php
                        $ticketNo = $r->request_no ?? ($r->job_no ?? $r->id);
                        $workOrderNo = $getWorkOrderNo($r);
                        $assetName = $r->asset->name ?? null;
                        $assetCode = $r->asset->asset_code ?? null;
                        $deptName = $getDept($r);
                        $location = $r->location_text ?? null;
                        $reporterName = $r->reporter_name ?? ($r->reporter?->name ?? '-');
                        $reporterPhone = $r->reporter_phone ?? null;
                        $createdAtText = optional($r->created_at)->format('d/m/Y H:i');
                        $mrStatus = strtolower((string) ($r->status ?? ''));
                        $mrStatusText = $statusLabel($mrStatus);

                        // ดึงทีมเจ้าหน้าที่จาก assignments
                        $techWorkers = ($r->assignments ?? collect())
                            ->filter(fn($a) => strtolower((string) ($a->status ?? '')) !== 'cancelled')
                            ->map(fn($a) => $a->user)
                            ->filter()
                            ->unique('id')
                            ->values();
                    @endphp

                    <div
                        class="job-card bg-white border border-slate-200 hover: transition- rounded-md flex relative">
                        {{-- Status accent bar --}}
                        <div class="w-1.5 shrink-0 rounded-l-md" style="background-color: {{ $statusAccentColor($mrStatus) }}"></div>
                        <div class="flex-1 min-w-0">

                            {{-- Main Row --}}
                            <div
                                class="flex flex-col lg:grid lg:grid-cols-12 items-stretch divide-y lg:divide-y-0 lg:divide-x divide-slate-100">

                                {{-- Col 1: Job ID + Status --}}
                                <div class="col-span-1 lg:col-span-3 px-6 py-5">
                                    <div class="text-[14px] font-bold text-slate-500 mb-2">
                                        รหัสงาน
                                    </div>
                                    <div class="text-[20px] font-bold text-[#0F2D5C] font-mono">#{{ $ticketNo }}</div>
                                    @if ($workOrderNo)
                                        <div class="text-[13px] text-slate-400 font-mono mt-0.5">WO# {{ $workOrderNo }}
                                        </div>
                                    @endif

                                    <div class="mt-2.5">
                                        <div class="text-[13px] font-bold text-slate-500 mb-1.5">สถานะงาน</div>
                                        <div class="flex items-center gap-1.5 {{ $statusBadge($mrStatus) }}">
                                            <span
                                                class="material-symbols-outlined text-[18px] {{ $mrStatus === 'pending' ? 'animate-pulse' : '' }}">
                                                {{ $statusIcon($mrStatus) }}
                                            </span>
                                            <span class="text-[13px] font-bold">
                                                {{ $mrStatusText }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <div class="text-[13px] font-bold text-slate-500 mb-1.5">ประเภทงาน</div>
                                        @can('setType', $r)
                                            <select class="ts-basic job-type-select w-full"
                                                data-placeholder="— ประเภทงาน —"
                                                data-old-type-id="{{ $r->type_id }}"
                                                data-id="{{ $r->id }}">
                                                <option value="">- เลือกประเภทงาน -</option>
                                                @foreach ($types as $type)
                                                    <option value="{{ $type->id }}" @selected($r->type_id == $type->id)>
                                                        {{ $type->name }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <div
                                                class="inline-flex items-center px-2.5 py-1 text-[13px] font-bold border rounded-md {{ $r->type_id ? 'bg-sky-50 text-sky-700 border-sky-100' : 'bg-slate-50 text-slate-500 border-slate-100' }}">
                                                {{ $r->type?->name ?? 'ไม่ระบุประเภท' }}
                                            </div>
                                        @endcan
                                    </div>
                                </div>

                                {{-- Col 2: Problem Description --}}
                                <div class="col-span-1 lg:col-span-4 px-6 py-5">
                                    <div class="text-[13px] font-bold text-slate-500 uppercase tracking-widest mb-2">
                                        รายละเอียดปัญหา</div>
                                    <div class="text-[17px] font-semibold text-slate-800 leading-tight mb-2">
                                        {{ $r->title }}</div>
                                    @if ($r->description)
                                        <p class="text-[14px] text-slate-600 leading-relaxed">{{ $r->description }}</p>
                                    @else
                                        <p class="text-[14px] text-slate-400 italic">- ไม่มีรายละเอียด -</p>
                                    @endif
                                    <div class="mt-2 flex items-center gap-3 text-[14px] text-slate-500">
                                        <span class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[16px]">calendar_month</span>
                                            {{ $createdAtText }}
                                        </span>
                                        @if ($deptName)
                                            <span class="flex items-center gap-1">
                                                <span class="material-symbols-outlined text-[16px]">domain</span>
                                                {{ $deptName }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Col 3: Asset Details --}}
                                <div class="col-span-1 lg:col-span-3 px-6 py-5">
                                    <div class="text-[13px] font-bold text-slate-500 uppercase tracking-widest mb-2">
                                        รายละเอียดทรัพย์สิน</div>
                                    @if ($assetName || $assetCode)
                                        <div class="flex items-start gap-2">
                                            <span
                                                class="material-symbols-outlined text-[17px] text-[#0F2D5C] mt-0.5 shrink-0">inventory_2</span>
                                            <div>
                                                <div class="text-[16px] font-semibold text-slate-800">
                                                    {{ $assetName ?? '-' }}</div>
                                                @if ($assetCode)
                                                    <div class="text-[13px] font-mono text-[#0F2D5C]">{{ $assetCode }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <div class="text-[13px] text-slate-400 italic">- ไม่ระบุ -</div>
                                    @endif
                                    <div class="mt-3 text-[15px] text-slate-600">
                                        <span class="font-semibold text-slate-900">สถานที่:</span> {{ $location ?? '-' }}
                                    </div>
                                    <div class="mt-2 text-[15px] text-slate-600 space-y-1">
                                        <div><span class="font-semibold text-slate-900">ผู้แจ้ง:</span>
                                            {{ $reporterName }}</div>
                                        @if ($reporterPhone)
                                            <div><span class="font-semibold text-slate-900">โทร:</span>
                                                {{ $reporterPhone }}</div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Col 4: Technician + View Details --}}
                                <div
                                    class="col-span-1 lg:col-span-2 px-6 py-5 flex flex-col justify-between bg-slate-50/30 lg:bg-transparent">
                                    <div>
                                        <div class="text-[13px] font-bold text-slate-500 uppercase tracking-widest mb-3">
                                            เจ้าหน้าที่ผู้รับผิดชอบ</div>

                                        @if ($techWorkers->isEmpty())
                                            <div class="flex items-center gap-2">
                                                <div
                                                    class="w-8 h-8 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center shrink-0">
                                                    <span
                                                        class="material-symbols-outlined text-[15px] text-slate-400">person</span>
                                                </div>
                                                <span class="text-[11px] text-slate-400 italic">ยังไม่ได้มอบหมาย</span>
                                            </div>
                                        @else
                                            {{-- Avatar Stack --}}
                                            <div class="flex items-center">
                                                @foreach ($techWorkers->take(3) as $i => $w)
                                                    @php
                                                        $wAvatar = $w->avatar_thumb_url ?? null;
                                                        $ci = abs(crc32($w->name ?? '')) % count($avatarColors);
                                                    @endphp
                                                    <div class="w-8 h-8 rounded-full border-2 border-white shrink-0 overflow-hidden {{ $i > 0 ? '-ml-2' : '' }}"
                                                        title="{{ $w->name }}">
                                                        @if ($wAvatar)
                                                            <img src="{{ $wAvatar }}" alt="{{ $w->name }}"
                                                                class="w-full h-full object-cover">
                                                        @else
                                                            <div
                                                                class="w-full h-full {{ $avatarColors[$ci] }} flex items-center justify-center text-white text-[10px] font-bold">
                                                                {{ mb_strtoupper(mb_substr($w->name ?? '?', 0, 2)) }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach

                                                @if ($techWorkers->count() > 3)
                                                    <div
                                                        class="w-8 h-8 rounded-full border-2 border-white bg-slate-200 -ml-2 flex items-center justify-center shrink-0">
                                                        <span
                                                            class="text-[10px] font-bold text-slate-600">+{{ $techWorkers->count() - 3 }}</span>
                                                    </div>
                                                @endif
                                            </div>

                                            {{-- รายชื่อเจ้าหน้าที่ทั้งหมด --}}
                                            <div class="mt-1.5 space-y-0.5">
                                                @foreach ($techWorkers as $w)
                                                    <div class="text-[13px] font-semibold text-slate-700 leading-tight">
                                                        {{ $w->name }}
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    {{-- View Details --}}
                                    <div class="mt-4">
                                        <a href="{{ route('maintenance.requests.show', $r) }}" onclick="showLoader()"
                                            class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-[#0F2D5C] text-white text-[13px] font-semibold rounded-sm hover:bg-[#0a2045] transition-colors whitespace-nowrap">
                                            <span class="material-symbols-outlined text-[16px] shrink-0">visibility</span>
                                            <span>ดูรายละเอียด</span>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            {{-- Action Bar --}}
                            @php $hasActions = false; @endphp
                            @can('acknowledge', $r)
                                @if ($mrStatus === 'pending')
                                    @php $hasActions = true; @endphp
                                @endif
                            @endcan
                            @can('accept', $r)
                                @if ($mrStatus === 'acknowledged')
                                    @php $hasActions = true; @endphp
                                @endif
                            @endcan
                            @can('reject', $r)
                                @if ($mrStatus === 'acknowledged')
                                    @php $hasActions = true; @endphp
                                @endif
                            @endcan

                            @if ($hasActions)
                                <div class="px-6 py-3 bg-slate-50 border-t border-slate-100 flex items-center gap-2">
                                    @can('acknowledge', $r)
                                        @if ($mrStatus === 'pending')
                                            <button type="button"
                                                class="inline-flex items-center gap-2 px-4 py-2 bg-[#0F2D5C] text-white text-[13px] font-semibold rounded-sm hover:bg-[#0A2045] transition-colors"
                                                onclick="submitAcknowledge('{{ $r->id }}', '{{ $ticketNo }}')">
                                                <span class="material-symbols-outlined text-[16px]">approval_delegation</span>
                                                รับทราบ
                                            </button>
                                        @endif
                                    @endcan
                                    @can('accept', $r)
                                        @if ($mrStatus === 'acknowledged')
                                            <button type="button"
                                                class="inline-flex items-center gap-2 px-4 py-2 bg-[#0F2D5C] text-white text-[13px] font-semibold rounded-sm hover:bg-[#0A2045] transition-colors"
                                                onclick="submitAccept('{{ $r->id }}', '{{ $ticketNo }}')">
                                                <span class="material-symbols-outlined text-[16px]">check</span>
                                                รับเรื่อง
                                            </button>
                                        @endif
                                    @endcan
                                    @can('reject', $r)
                                        @if (in_array($mrStatus, ['pending', 'acknowledged']))
                                            <button type="button"
                                                class="inline-flex items-center gap-2 px-4 py-2 bg-rose-600 text-white text-[13px] font-semibold rounded-sm hover:bg-rose-700 transition-colors"
                                                onclick="submitReject('{{ $r->id }}', '{{ $ticketNo }}')">
                                                <span class="material-symbols-outlined text-[16px]">block</span>
                                                ไม่รับเรื่อง
                                            </button>
                                        @endif
                                    @endcan
                                </div>
                            @endif

                            {{-- Hidden Forms --}}
                            <form id="ackForm-{{ $r->id }}" method="POST"
                                action="{{ route('maintenance.requests.acknowledge', $r) }}" class="hidden">@csrf</form>
                            <form id="acceptForm-{{ $r->id }}" method="POST"
                                action="{{ route('maintenance.requests.accept', $r) }}" class="hidden">@csrf</form>
                            <form id="rejectForm-{{ $r->id }}" method="POST"
                                action="{{ route('maintenance.requests.reject', $r) }}" class="hidden">
                                @csrf
                                <input type="hidden" name="remark" id="rejectRemark-{{ $r->id }}"
                                    value="">
                            </form>

                        </div> {{-- end flex-1 --}}
                    </div> {{-- end card --}}

                @empty
                    <div class="bg-white border border-slate-200 rounded-md p-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        <p class="mt-4 text-sm text-slate-500">ไม่พบรายการงานตามเงื่อนไขที่เลือก</p>
                    </div>
                @endforelse

            </div>

            @if ($list->hasPages())
                <div class="mt-6 mb-12">
                    {{ $list->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>


@endsection

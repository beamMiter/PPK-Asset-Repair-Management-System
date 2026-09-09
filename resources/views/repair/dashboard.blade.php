{{-- resources/views/dashboard.blade.php --}}
@extends('layouts.app')

@section('title', 'Repair Dashboard')

@section('content')
    @php
        // ... (existing php blocks)
        $toast = session('toast');
        if ($toast) {
            session()->forget('toast');
        }

        $type = $toast['type'] ?? null;
        $message = $toast['message'] ?? null;
        $timeout = (int) ($toast['timeout'] ?? 3200);

        $firstError = isset($errors) ? $errors->first() : null;
        if (!$message && $firstError) {
            $message = $firstError;
            $type = $type ?: 'error';
        }
        if (!$message && session('error')) {
            $message = session('error');
            $type = $type ?: 'error';
        }
        if (!$message && session('status')) {
            $message = session('status');
            $type = $type ?: 'success';
        }

        $monthlyTrend = collect($monthlyTrend ?? []);
        $byAssetType = collect($byAssetType ?? []);
        $byDept = collect($byDept ?? []);
        $recent = collect($recent ?? []);

        $kpi = array_merge(
            [
                'lastYear' => 0,
                'thisYear' => 0,
                'thisYearCompleted' => 0,
                'avgResolveHours' => null,
            ],
            $kpi ?? [],
        );

        $stats = $stats ?? [];

        $intVal = fn($v) => is_numeric($v) ? (int) $v : 0;
        $strVal = fn($v, $def = '') => is_string($v) && $v !== '' ? $v : $def;

        $trendLabels = $monthlyTrend->map(fn($i) => $strVal($i['ym'] ?? ($i->ym ?? '')))->values();
        $trendCounts = $monthlyTrend->map(fn($i) => $intVal($i['cnt'] ?? ($i->cnt ?? 0)))->values();

        $typeLabels = $byAssetType
            ->map(fn($i) => $strVal($i['type'] ?? ($i->type ?? 'Unspecified'), 'Unspecified'))
            ->values();
        $typeCounts = $byAssetType->map(fn($i) => $intVal($i['cnt'] ?? ($i->cnt ?? 0)))->values();

        $deptLabels = $byDept
            ->map(fn($i) => $strVal($i['dept'] ?? ($i->dept ?? 'Unspecified'), 'Unspecified'))
            ->values();
        $deptCounts = $byDept->map(fn($i) => $intVal($i['cnt'] ?? ($i->cnt ?? 0)))->values();

        $totalType = $typeCounts->sum();
        $topTypeLabel = $typeLabels[0] ?? '-';
        $topTypePct = $totalType > 0 ? round((($typeCounts[0] ?? 0) / $totalType) * 100, 1) : 0;

        $filtersActive = request()->hasAny(['status', 'from', 'to']);

        $statusTH = [
            'pending' => 'รอดำเนินการ',
            'acknowledged' => 'รับทราบแล้ว',
            'accepted' => 'รับเรื่องแล้ว',
            'in_progress' => 'กำลังดำเนินการ',
            'on_hold' => 'พักไว้ชั่วคราว',
            'resolved' => 'ซ่อมบำรุงเสร็จสิ้น',
            'closed' => 'อนุมัติผลการซ่อมบำรุง',
        ];

        $statusPill = fn($s) => match ($s) {
            'pending' => 'bg-[#FFF9E6] text-[#D97706]',
            'acknowledged' => 'bg-[#EAF2FF] text-[#2B6CEB]',
            'accepted' => 'bg-[#EEF2FF] text-[#4F46E5]',
            'in_progress' => 'bg-[#DDE8FF] text-[#2B6CEB]',
            'on_hold' => 'bg-[#F1F5F9] text-[#475569]',
            'resolved' => 'bg-[#ECFDF5] text-[#059669]',
            'closed' => 'bg-emerald-100 text-emerald-800',
            default => 'bg-slate-100 text-slate-500',
        };

        $statusStyle = fn($s) => match ($s) {
            'pending' => [
                'icon' => 'schedule',
                'color' => 'text-amber-600',
                'bg' => 'bg-amber-50',
                'border' => 'border-amber-200',
            ],
            'acknowledged' => [
                'icon' => 'mark_email_read',
                'color' => 'text-sky-500',
                'bg' => 'bg-sky-50',
                'border' => 'border-sky-200',
            ],
            'accepted' => [
                'icon' => 'thumb_up',
                'color' => 'text-indigo-500',
                'bg' => 'bg-indigo-50',
                'border' => 'border-indigo-200',
            ],
            'in_progress' => [
                'icon' => 'engineering',
                'color' => 'text-blue-500',
                'bg' => 'bg-blue-50',
                'border' => 'border-blue-200',
            ],
            'on_hold' => [
                'icon' => 'pause_circle',
                'color' => 'text-slate-500',
                'bg' => 'bg-slate-50',
                'border' => 'border-slate-200',
            ],
            'resolved' => [
                'icon' => 'check_circle',
                'color' => 'text-emerald-500',
                'bg' => 'bg-emerald-50',
                'border' => 'border-emerald-200',
            ],
            'closed' => [
                'icon' => 'fact_check',
                'color' => 'text-emerald-800',
                'bg' => 'bg-emerald-50',
                'border' => 'border-emerald-300',
            ],
            default => [
                'icon' => 'info',
                'color' => 'text-slate-500',
                'bg' => 'bg-slate-50',
                'border' => 'border-slate-200',
            ],
        };
    @endphp

    <div class="min-h-screen bg-white pb-[42px] antialiased overflow-x-hidden -mt-[1rem]">

        {{-- HERO --}}
        <header
            class="relative overflow-hidden bg-[#0B1F3B] min-h-[320px] pb-[46px] -mx-[clamp(16px,2vw,28px)] rounded-none">
            <img class="absolute top-[-70px] left-[60%] -translate-x-1/2 w-[980px] max-w-none h-auto opacity-[0.16] pointer-events-none select-none z-0"
                src="{{ asset('images/dashboard/world-map.svg') }}" alt="" aria-hidden="true" />

            <div class="absolute inset-0 pointer-events-none z-10"
                style="background: radial-gradient(900px 380px at 18% 30%, rgba(255, 255, 255, .10), transparent 58%), radial-gradient(1000px 420px at 85% 10%, rgba(0, 0, 0, .26), transparent 58%), linear-gradient(135deg, rgba(0, 0, 0, .20), rgba(0, 0, 0, .08));"
                aria-hidden="true"></div>

            <div class="relative z-20 px-[clamp(20px,3vw,44px)] pt-[44px]">
                <div class="flex flex-col md:flex-row md:items-start justify-between gap-6">
                    <div class="hero-left">
                        <h1
                            class="mt-[10px] text-white text-[32px] md:text-[38px] font-semibold leading-tight tracking-tight">
                            Main Dashboard</h1>
                        <p class="mt-[12px] text-white/90 text-sm font-medium leading-[1.55]">
                            สรุปภาพรวมและสถิติการแจ้งซ่อมทั้งหมด<br>
                            อัปเดตล่าสุด: {{ now()->format('d F Y') }}
                        </p>
                    </div>

                    <div class="hero-right flex items-center gap-3 md:mt-[18px]">
                        <a href="{{ route('maintenance.requests.create') }}"
                            class="inline-flex items-center gap-2 rounded-md bg-white px-4 py-2 text-[13px] font-medium text-[#0B1F3B] hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-white/40 transition-all active:scale-95"
                            onclick="showLoader()">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                            </svg>
                            สร้างทะเบียนแจ้งซ่อม
                        </a>
                    </div>
                </div>
            </div>
        </header>

        {{-- CONTENT --}}
        <main class="relative z-30 px-[clamp(16px,2vw,28px)] mt-[-20px] sm:mt-[-40px] md:mt-[-84px] lg:mt-[-98px]">
            {{-- FILTERS --}}

            {{-- OVERVIEW (Charts) --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-[14px] mb-6">
                {{-- LEFT: DONUT GROUP --}}
                <section class="bg-white p-6 rounded-xl border border-[#D6E4FF] relative overflow-hidden flex flex-col">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-[1.15rem] font-semibold text-[#0F2D5C] tracking-tight leading-tight">Asset
                                Distribution</h3>
                            <p class="text-[11px] font-medium text-slate-400 mt-0.5">สัดส่วนงานแยกตามประเภทครุภัณฑ์และสถานะ
                            </p>
                        </div>
                        <div
                            class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-300 border border-slate-100">
                            <span class="material-symbols-outlined text-xl">pie_chart</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 px-4 pb-4">
                        {{-- Asset Type donut --}}
                        <div class="relative">
                            <div class="pl-2.5 mb-1.5 text-[13px] font-semibold text-[#0B1F3B] tracking-tight">
                                ประเภทครุภัณฑ์</div>
                            <div class="relative h-[240px] p-2 bg-white">
                                <canvas id="typeDonut" data-labels='@json($typeLabels)'
                                    data-values='@json($typeCounts)'></canvas>
                            </div>
                            <div
                                class="absolute inset-0 flex flex-col justify-center items-center pointer-events-none text-center">
                                <div class="text-[22px] font-bold text-[#0B1F3B] tracking-tight leading-none"
                                    data-countup="{{ $topTypePct }}" data-suffix="%">
                                    {{ $topTypePct }}%</div>
                                <div class="text-[10px] font-semibold text-[#4A6FA5] uppercase tracking-wide mt-0.5">
                                    {{ $topTypeLabel }}</div>
                            </div>
                        </div>

                        {{-- Status donut --}}
                        <div class="relative">
                            <div class="pl-2.5 mb-1.5 text-[13px] font-semibold text-[#0B1F3B] tracking-tight">
                                สถานะการทำงาน</div>
                            <div class="relative h-[240px] p-2 bg-white">
                                <canvas id="statusDonut" data-pending="{{ (int) ($stats['pending'] ?? 0) }}"
                                    data-progress="{{ (int) ($stats['inProgress'] ?? 0) }}"
                                    data-completed="{{ (int) ($stats['completed'] ?? 0) }}" data-cancelled="0"></canvas>
                            </div>
                            <div class="flex gap-[22px] items-baseline px-2.5 pt-2.5">
                                <div class="flex flex-col gap-0.5">
                                    <div class="text-[10px] font-semibold text-[#4A6FA5] uppercase tracking-wide">
                                        เวลาเฉลี่ยปิดงาน</div>
                                    <div class="text-xl font-bold text-[#0B1F3B] tracking-tight"
                                        @if ($kpi['avgResolveHours']) data-countup="{{ $kpi['avgResolveHours'] }}" data-suffix=" h" @endif>
                                        {{ $kpi['avgResolveHours'] ? $kpi['avgResolveHours'] . ' h' : '-' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- RIGHT: MONTHLY TREND (Chart.js) --}}
                <section class="bg-white p-6 rounded-xl border border-[#D6E4FF] flex flex-col relative overflow-hidden">
                    <div class="flex justify-between items-center mb-4 z-10">
                        <div>
                            <h3 class="text-[1.15rem] font-semibold text-[#0F2D5C] tracking-tight leading-tight">Repair
                                Volume Trends</h3>
                            <p class="text-[11px] font-medium text-slate-400 mt-0.5">ภาพรวมปริมาณงานแจ้งซ่อมตามช่วงเวลา</p>
                        </div>
                        <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-widest">Monthly</span>
                    </div>
                    @php
                        $trendLabels = $monthlyTrend
                            ->map(
                                fn($r) => \Carbon\Carbon::createFromFormat('Y-m', $r['ym'])
                                    ->locale('th')
                                    ->isoFormat('MMM YY'),
                            )
                            ->values()
                            ->toArray();
                        $trendValues = $monthlyTrend->pluck('cnt')->values()->toArray();
                    @endphp
                    <div class="relative flex-1 min-h-[180px] p-3.5 bg-white">
                        <canvas id="trendBar" data-labels='@json($trendLabels)'
                            data-values='@json($trendValues)'></canvas>
                    </div>
                </section>
            </div>

            {{-- QUICK ANALYTICS --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <!-- Total Requests -->
                <div
                    class="bg-white p-6 rounded-lg border border-[#D6E4FF] relative overflow-hidden group hover:bg-slate-50 transition-all flex flex-col items-center justify-center text-center font-inter">
                    <span class="text-[11px] font-semibold text-slate-900 uppercase tracking-widest mb-3">
                        Cumulative Data
                    </span>
                    <h3 class="text-[#4A6FA5] text-sm md:text-base font-semibold uppercase tracking-wider mb-2">
                        ใบแจ้งซ่อมสะสมทั้งหมด</h3>
                    <p class="text-4xl sm:text-5xl font-bold text-[#0B1F3B] leading-none"
                        data-countup="{{ (int) ($stats['total'] ?? 0) }}">
                        {{ number_format((int) ($stats['total'] ?? 0)) }}
                    </p>
                    <p class="mt-2 text-[11px] font-medium text-slate-400">จำนวนรายการแจ้งซ่อมทั้งหมดในระบบ</p>
                </div>

                <!-- This Month -->
                <div
                    class="bg-white p-6 rounded-lg border border-[#D6E4FF] relative overflow-hidden group hover:bg-slate-50 transition-all flex flex-col items-center justify-center text-center font-inter">
                    <span class="text-[11px] font-semibold text-slate-900 uppercase tracking-widest mb-3">
                        Yearly Traffic
                    </span>
                    <h3 class="text-[#4A6FA5] text-sm md:text-base font-semibold uppercase tracking-wider mb-2">
                        สถิติการแจ้งซ่อมปีนี้</h3>
                    <div class="flex items-center gap-3">
                        <p class="text-4xl sm:text-5xl font-bold text-[#0B1F3B] leading-none"
                            data-countup="{{ (int) $kpi['thisYear'] }}">
                            {{ number_format((int) $kpi['thisYear']) }}</p>
                        @php
                            $trend = $kpi['totalTrend'] ?? 0;
                            $trendDisplay = abs($trend) >= 999 ? '>999' : abs($trend);
                        @endphp
                        <div class="flex flex-col items-start pt-1">
                            <span
                                class="flex items-center text-sm sm:text-base font-bold {{ $trend >= 0 ? 'text-emerald-500' : 'text-rose-500' }}">
                                <span
                                    class="material-symbols-outlined text-lg sm:text-xl">{{ $trend >= 0 ? 'trending_up' : 'trending_down' }}</span>
                                {{ $trendDisplay }}%
                            </span>
                        </div>
                    </div>
                    <p class="mt-2 text-[11px] font-medium text-slate-400">ปริมาณงานใหม่ที่เกิดขึ้นในปีปัจจุบัน</p>
                </div>

                <!-- Pending -->
                <div
                    class="bg-white p-6 rounded-lg border border-[#D6E4FF] relative overflow-hidden group hover:bg-slate-50 transition-all flex flex-col items-center justify-center text-center font-inter">
                    <span class="text-[11px] font-semibold text-slate-900 uppercase tracking-widest mb-3">
                        Active Queue
                    </span>
                    <h3 class="text-[#4A6FA5] text-sm md:text-base font-semibold uppercase tracking-wider mb-2">
                        งานคงค้างรอดำเนินการ</h3>
                    <p class="text-4xl sm:text-5xl font-bold text-amber-600 leading-none"
                        data-countup="{{ (int) ($stats['pending'] ?? 0) }}">
                        {{ number_format((int) ($stats['pending'] ?? 0)) }}
                    </p>
                    <p class="mt-2 text-[11px] font-medium text-slate-400">จำนวนงานที่ยังไม่ได้เริ่มดำเนินการ</p>
                </div>

                <!-- Completed This Month -->
                <div
                    class="bg-white p-6 rounded-lg border border-[#D6E4FF] relative overflow-hidden group hover:bg-slate-50 transition-all flex flex-col items-center justify-center text-center font-inter">
                    <span class="text-[11px] font-semibold text-slate-900 uppercase tracking-widest mb-3">
                        Yearly Resolution
                    </span>
                    <h3 class="text-[#4A6FA5] text-sm md:text-base font-semibold uppercase tracking-wider mb-2">
                        งานที่ซ่อมเสร็จสิ้นปีนี้</h3>
                    <div class="flex items-center gap-3">
                        <p class="text-4xl sm:text-5xl font-bold text-[#006c46] leading-none"
                            data-countup="{{ (int) $kpi['thisYearCompleted'] }}">
                            {{ number_format((int) $kpi['thisYearCompleted']) }}</p>
                        @php
                            $trend = $kpi['completedTrend'] ?? 0;
                            $trendDisplay = abs($trend) >= 999 ? '>999' : abs($trend);
                        @endphp
                        <div class="flex flex-col items-start pt-1">
                            <span
                                class="flex items-center text-sm sm:text-base font-bold {{ $trend >= 0 ? 'text-emerald-500' : 'text-rose-500' }}">
                                <span
                                    class="material-symbols-outlined text-lg sm:text-xl">{{ $trend >= 0 ? 'trending_up' : 'trending_down' }}</span>
                                {{ $trendDisplay }}%
                            </span>
                        </div>
                    </div>
                    <p class="mt-2 text-[11px] font-medium text-slate-400">ประสิทธิภาพการปิดงานในปีปัจจุบัน</p>
                </div>
            </div>


            {{-- TECHNICIAN WORKLOAD --}}
            @if ($techWorkload->isNotEmpty())
                <section class="bg-white rounded-xl border border-[#D6E4FF] overflow-hidden mb-6">
                    <div class="flex justify-between items-center px-6 py-4 border-b border-[#D6E4FF]">
                        <div>
                            <h3 class="text-[1.15rem] font-black text-[#0F2D5C] tracking-tight leading-tight">Technician
                                Workload</h3>
                            <p class="text-[11px] font-bold text-slate-400 mt-0.5">ภาระงานเจ้าหน้าที่แต่ละคน</p>
                        </div>
                        <span class="text-[12px] font-medium text-slate-500">ทั้งหมด {{ $techWorkload->count() }}
                            คน</span>
                    </div>

                    {{-- Horizontal scroll row --}}
                    <div class="flex gap-x-8 px-6 py-8 overflow-x-auto scrollbar-thin" style="scrollbar-width: thin;">
                        @foreach ($techWorkload as $tech)
                            <a href="{{ route('repairs.my_jobs', ['tech' => $tech['id'], 'filter' => 'all']) }}"
                                class="flex flex-col items-center gap-2.5 flex-shrink-0 group transition-all duration-300 hover:-translate-y-1"
                                title="ดูแผงงานของ {{ $tech['name'] }}">
                                {{-- Avatar --}}
                                <div class="relative">
                                    <div
                                        class="w-14 h-14 rounded-full p-0.5 bg-gradient-to-tr from-[#0F2D5C]/20 to-[#0F2D5C]/5 group-hover:from-[#0F2D5C] group-hover:to-blue-500 transition-all duration-300">
                                        <img src="{{ $tech['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($tech['name']) . '&background=00275f&color=fff' }}"
                                            alt="{{ $tech['name'] }}"
                                            class="w-full h-full rounded-full object-cover border-2 border-white " />
                                    </div>
                                    <span
                                        class="absolute -top-1 -right-1 min-w-[22px] h-[22px] px-1 rounded-full flex items-center justify-center text-[10px] font-black text-white bg-[#0F2D5C] border-2 border-white z-10 transition-transform group-hover:scale-110">
                                        {{ $tech['total'] }}
                                    </span>
                                </div>
                                {{-- Full Name & Stats --}}
                                <div class="text-center space-y-0.5">
                                    <span
                                        class="block text-[11px] font-black text-[#0F2D5C] leading-tight whitespace-nowrap transition-colors">
                                        {{ $tech['name'] }}
                                    </span>
                                    <span class="block text-[9px] font-bold text-slate-400 uppercase tracking-tight">
                                        {{ $tech['role_label'] }}
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1 text-[10px] font-black text-[#4A6FA5] group-hover:text-[#0F2D5C]">
                                        <span
                                            class="w-1.5 h-1.5 rounded-full bg-blue-500 group-hover:animate-pulse"></span>
                                        {{ $tech['total'] }} งาน
                                    </span>
                                </div>
                            </a>
                        @endforeach
                        {{-- Spacer to ensure right padding when scrolled to the end --}}
                        <div class="flex-shrink-0 w-4 h-1"></div>
                    </div>
                </section>
            @endif

            {{-- OVERVIEW --}}
            <section class="mt-[10px]">





                {{-- Department --}}
                <div class="mt-6">
                    <section
                        class="bg-white p-6 rounded-xl border border-[#D6E4FF] relative overflow-hidden flex flex-col">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h3 class="text-[1.15rem] font-semibold text-[#0F2D5C] tracking-tight leading-tight">
                                    Workload by Dept</h3>
                                <p class="text-[11px] font-medium text-slate-400 mt-0.5">ปริมาณงานแยกตามแผนกที่รับผิดชอบ
                                </p>
                            </div>
                            <div
                                class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-300 border border-slate-100">
                                <span class="material-symbols-outlined text-xl">account_tree</span>
                            </div>
                        </div>

                        <div class="relative h-[240px] p-2 bg-white">
                            <canvas id="deptBar" data-labels='@json($deptLabels)'
                                data-values='@json($deptCounts)'></canvas>
                        </div>
                    </section>
                </div>
            </section>




    </div>

    <div class="ui-toast-overlay" aria-live="polite" aria-atomic="true"></div>

    <script>
        window.__DASH__ = {
            message: @json($message),
            type: @json($type),
            timeout: @json($timeout),
        };
    </script>

    @vite(['resources/js/repair/dashboard.js'])
@endsection

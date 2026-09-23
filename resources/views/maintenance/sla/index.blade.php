@extends('layouts.app')

@section('title', 'SLA Dashboard')

@section('page-header')
    @php
        // The three quick-range links below (and the "แสดงข้อมูล:" summary further down) both need to know which one, if
        // any, is currently applied — computed once, from the exact same `from` each link itself sends, so the two
        // things showing it never fall out of step with each other (they used to: the summary compared against
        // subMonths(5)->startOfMonth() / subMonths(11)->startOfMonth(), which never once matched what these links
        // actually send — subMonths(6)->addDay() / subYear()->addDay() — so "แสดงข้อมูล:" always read "ช่วงวันที่", never
        // "6 เดือน" or "12 เดือน", even right after clicking one).
        $slaShortcuts = [
            'six' => ['label' => '6 เดือน', 'from' => now()->subMonths(6)->addDay()->format('Y-m-d')],
            'twelve' => ['label' => '12 เดือน', 'from' => now()->subYear()->addDay()->format('Y-m-d')],
            'year' => ['label' => 'ปีนี้', 'from' => null],
        ];
        $activeShortcut = null;
        if (!request('from') && !request('to')) {
            $activeShortcut = 'year';
        } elseif (!request('to')) {
            $activeShortcut = collect($slaShortcuts)->search(fn ($s, $key) => $key !== 'year' && $s['from'] === request('from')) ?: null;
        }
    @endphp
    @php
        // every late job (not just the 20 the panel shows): the print dialog lets the user choose among all of them
        $printRows = collect($breachedTickets)->map(fn ($t) => [
            'id' => $t->id,
            'no' => (string) $t->request_no,
            'title' => (string) $t->title,
            'dept' => (string) ($t->department?->name_th ?? ($t->department?->name_en ?? '-')),
            'late' => $t->overdueLabel(now()),
        ])->values();
    @endphp
    <div class="sticky top-16 z-20 bg-white/90 backdrop-blur border-b border-slate-200" x-data="{ showFilters: window.innerWidth >= 768 }">
        <div class="px-4 md:px-6 lg:px-8 py-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <img src="/icon/slapaper.webp" class="h-8 w-8 object-contain" alt="SLA Icon">
                        <div>
                            <h1 class="text-[17px] font-semibold text-slate-900 leading-tight">SLA Performance Dashboard</h1>
                            <p class="text-[13px] text-slate-500 font-medium">
                                สรุปประสิทธิภาพ@if (request('from') || request('to'))
                                    ช่วง {{ request('from') ?? '...' }} ถึง {{ request('to') ?? 'วันนี้' }} @elseในรอบปี
                                    {{ now()->year }}
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 mt-2 md:mt-0" x-data="{ showSignModal: false }">
                    {{-- Filter Toggle (Mobile Only) --}}
                    <button type="button" @click="showFilters = !showFilters"
                        class="md:hidden inline-flex items-center gap-1.5 h-11 px-4 rounded-md border text-[13px] font-medium transition-colors"
                        :class="showFilters ? 'bg-slate-100 border-slate-300 text-slate-800' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'">
                        <span class="material-symbols-outlined text-[16px]">filter_list</span>
                        <span x-text="showFilters ? 'ซ่อนตัวกรอง' : 'ตัวกรอง'"></span>
                    </button>
                    {{-- Bare icons, not a bordered/filled button of any size — same `icon-lg` (40px, a 24px glyph) as the
                         live chat page's own refresh icon (chat/index.blade.php, #btnHeaderRefresh) and the paperclip/
                         camera pair and job-page assign-team icon elsewhere. `ghost-brand`, not plain `ghost`: this whole
                         page is navy (the submit button, the active shortcut pill, every focus ring), and plain ghost's
                         neutral grey read as too faint to notice against it. --}}
                    <x-ui.button type="button" @click="showSignModal = true" variant="ghost-brand" size="icon-lg"
                        icon="print" aria-label="รายงานสรุป" title="รายงานสรุป" />
                    <x-ui.button type="button" onclick="window.location.reload()" variant="ghost-brand" size="icon-lg"
                        icon="refresh" aria-label="รีเฟรชข้อมูลล่าสุด" title="รีเฟรชข้อมูลล่าสุด" />

                    {{-- Print dialog: which late jobs go in the report, a note, and the preparer's signature. Teleported to the body. --}}
                    <template x-teleport="body">
                        <div x-show="showSignModal"
                            class="fixed inset-0 z-[3000] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
                            x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
                            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                            style="display: none;">

                            <div class="bg-white rounded-xl w-full max-w-2xl max-h-[92vh] flex flex-col overflow-hidden border border-slate-200"
                                @click.away="showSignModal = false" x-data="slaPrintDialog(@js($printRows))">

                                <div
                                    class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50 shrink-0">
                                    <h3 class="text-lg font-semibold text-slate-900">พิมพ์รายงานสรุป SLA</h3>
                                    <button @click="showSignModal = false"
                                        class="text-slate-400 hover:text-slate-600 transition-colors">
                                        <span class="material-symbols-outlined">close</span>
                                    </button>
                                </div>

                                <div class="p-6 overflow-y-auto space-y-6">
                                    {{-- 1. which late jobs the report lists --}}
                                    <section>
                                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                            <label class="block text-[13px] font-medium text-slate-700">งานที่เกินเวลาที่จะแสดงในรายงาน</label>
                                            <span class="text-[12px] text-slate-500" x-show="rows.length > 0">
                                                เลือก <b class="text-slate-800" x-text="selected.length"></b> จาก <span x-text="rows.length"></span> รายการ
                                            </span>
                                        </div>

                                        <template x-if="rows.length === 0">
                                            <p class="rounded-md border border-dashed border-slate-200 bg-slate-50 px-3 py-4 text-center text-[13px] text-slate-500">
                                                ไม่มีงานที่เกินเวลาในขณะนี้ รายงานจะไม่มีตารางรายการ
                                            </p>
                                        </template>

                                        <template x-if="rows.length > 0">
                                            <div>
                                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                                    <input type="text" x-model="q" placeholder="ค้นหาเลขที่ ชื่อปัญหา หรือแผนก..."
                                                        class="min-w-0 flex-1 rounded-md border border-slate-200 px-3 py-1.5 text-[13px] placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#0F2D5C]/30">
                                                    <button type="button" @click="selectShown()"
                                                        class="text-[12px] font-semibold text-[#0F2D5C] hover:underline">เลือกทั้งหมด</button>
                                                    <button type="button" @click="clearShown()"
                                                        class="text-[12px] font-semibold text-slate-500 hover:text-rose-600 hover:underline">ล้างที่เลือก</button>
                                                </div>

                                                <div class="max-h-56 overflow-y-auto rounded-md border border-slate-200 divide-y divide-slate-100">
                                                    <template x-for="r in shown" :key="r.id">
                                                        <label class="flex cursor-pointer items-center gap-3 px-3 py-2 hover:bg-slate-50">
                                                            <input type="checkbox" :checked="isOn(r.id)" @change="toggle(r.id)"
                                                                class="h-4 w-4 shrink-0 rounded border-slate-300 text-[#0F2D5C] focus:ring-[#0F2D5C]/30">
                                                            <span class="w-[88px] shrink-0 text-[12px] font-semibold text-slate-700" x-text="'#' + r.no"></span>
                                                            <span class="min-w-0 flex-1 truncate text-[13px] text-slate-800" x-text="r.title"></span>
                                                            <span class="hidden w-32 shrink-0 truncate text-[12px] text-slate-500 sm:block" x-text="r.dept"></span>
                                                            <span class="shrink-0 text-[12px] font-bold text-rose-600" x-text="r.late"></span>
                                                        </label>
                                                    </template>
                                                    <p x-show="shown.length === 0" class="px-3 py-4 text-center text-[13px] text-slate-500">ไม่พบรายการที่ค้นหา</p>
                                                </div>
                                                <p class="mt-2 text-[11px] text-slate-500" x-show="selected.length < rows.length">
                                                    รายงานจะระบุว่าแสดงเฉพาะบางรายการ (เช่น "แสดง <span x-text="selected.length"></span> จากทั้งหมด <span x-text="rows.length"></span> รายการ")
                                                </p>
                                            </div>
                                        </template>
                                    </section>

                                    {{-- 2. a note that goes on the paper --}}
                                    <section>
                                        <label class="block text-[13px] font-medium text-slate-700 mb-2">ข้อสังเกต / ข้อเสนอแนะ <span class="font-normal text-slate-400">(ไม่บังคับ)</span></label>
                                        <textarea x-model="note" rows="3" maxlength="1000"
                                            placeholder="เช่น สาเหตุที่งานล่าช้า หรือแนวทางแก้ไข"
                                            class="w-full rounded-md border border-slate-200 px-3 py-2 text-[13px] placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#0F2D5C]/30" data-counter></textarea>
                                    </section>

                                    {{-- 3. the preparer signs --}}
                                    <section>
                                        <label class="block text-[13px] font-medium text-slate-700 mb-2">ลายเซ็นผู้จัดทำรายงาน</label>
                                        <div class="border-2 border-dashed border-slate-200 rounded-md bg-slate-50 overflow-hidden relative"
                                            style="height: 200px;">
                                            <canvas x-ref="canvas" class="w-full h-full cursor-crosshair"></canvas>
                                            <div class="absolute bottom-2 right-2 flex gap-2">
                                                <button @click="clearPad()"
                                                    class="px-2 py-1 bg-white border border-slate-200 rounded text-[11px] font-bold text-slate-600 hover:bg-slate-50">ล้างใหม่</button>
                                            </div>
                                        </div>
                                        <p class="mt-2 text-[11px] text-slate-500 italic text-center">*
                                            ลายเซ็นนี้จะปรากฏที่ช่อง "ผู้จัดทำรายงาน" ส่วนช่อง "ผู้อนุมัติ" เว้นไว้ให้เซ็นบนกระดาษ</p>
                                    </section>
                                </div>

                                <div class="px-6 py-4 bg-slate-50 flex justify-end gap-3 shrink-0">
                                    <button @click="showSignModal = false"
                                        class="px-4 py-2 text-[13px] font-bold text-slate-600 hover:text-slate-800">ยกเลิก</button>
                                    <button @click="submitReport()"
                                        class="px-6 py-2 bg-[#0F2D5C] text-white rounded-md text-[13px] font-bold hover:bg-[#1a3d7c] transition-all">พิมพ์รายงาน
                                        PDF</button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Filter Area --}}
        <div class="px-4 md:px-6 lg:px-8 py-3 border-t border-slate-100 bg-slate-50/30 md:!block" x-show="showFilters" x-collapse
            x-cloak>
            <form action="{{ route('maintenance.sla.index') }}" method="GET" class="flex flex-col gap-3">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2">
                            <label class="text-[12px] font-medium text-slate-500 whitespace-nowrap">ช่วงวันที่:</label>
                            <input type="date" name="from" value="{{ request('from') }}"
                                class="rounded-md border border-slate-200 px-2 py-1.5 text-[13px] focus:ring-2 focus:ring-[#0F2D5C]/20 outline-none">
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-[12px] font-medium text-slate-500 whitespace-nowrap">ถึง:</label>
                            <input type="date" name="to" value="{{ request('to') }}"
                                class="rounded-md border border-slate-200 px-2 py-1.5 text-[13px] focus:ring-2 focus:ring-[#0F2D5C]/20 outline-none">
                        </div>
                    </div>
                    {{-- The same round, filled, icon-only filter-submit button the request/asset/user lists use (a
                         44px navy or emerald circle with a magnifying glass, title "ค้นหา") — "แสดงผล" was the one
                         filter-apply button in the app that was a rectangle with a word on it instead. --}}
                    <button type="submit"
                        class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#0F2D5C] text-white hover:bg-[#0F2D5C]/90 focus:outline-none focus:ring-2 focus:ring-[#0F2D5C]/45 focus:ring-offset-1"
                        title="แสดงผล" aria-label="แสดงผล">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M21 21l-4.3-4.3M17 10a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </button>

                    <div class="h-4 w-[1px] bg-slate-200 mx-1 hidden md:block"></div>

                    {{-- Quick ranges, as a small set of pills rather than plain underlined links: which one (if any) is
                         applied right now is shown filled navy, not just implied by matching a date in the inputs above. --}}
                    <div class="flex items-center gap-2" role="group" aria-label="ช่วงเวลาด่วน">
                        <span class="text-[12px] font-medium text-slate-500">ทางลัด:</span>
                        @foreach ($slaShortcuts as $key => $shortcut)
                            <a href="{{ $shortcut['from'] ? url()->current() . '?from=' . $shortcut['from'] : url()->current() }}"
                                @if ($activeShortcut === $key) aria-current="true" @endif
                                class="rounded-md border px-2.5 py-1 text-[12px] font-semibold transition-colors {{ $activeShortcut === $key ? 'border-[#0F2D5C] bg-[#0F2D5C] text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                                {{ $shortcut['label'] }}
                            </a>
                        @endforeach
                    </div>

                    @if (request('from') || request('to'))
                        <a href="{{ route('maintenance.sla.index') }}"
                            class="ml-auto text-[12px] text-slate-500 hover:text-rose-600 underline">ล้างตัวกรอง</a>
                    @endif
                </div>

                {{-- Status Text below filter --}}
                <div class="text-[12px] text-slate-500 pt-1">
                    @php
                        $fromDate = request('from') ? \Carbon\Carbon::parse(request('from')) : null;
                        $toDate = request('to') ? \Carbon\Carbon::parse(request('to')) : now();

                        // คำนวณจำนวนวัน
                        $diffDays = 0;
                        if ($fromDate) {
                            $diffDays = (int) $fromDate->startOfDay()->diffInDays($toDate->copy()->startOfDay()) + 1;
                        } else {
                            $diffDays =
                                (int) now()
                                    ->startOfYear()
                                    ->diffInDays(now()->startOfDay()) + 1;
                        }

                        // ฟังก์ชันแปลงวันที่เป็นไทย (ย่อเดือน + พ.ศ.)
                        $thaiFormat = function ($date) {
                            if (!$date) {
                                return '...';
                            }
                            $months = [
                                '',
                                'ม.ค.',
                                'ก.พ.',
                                'มี.ค.',
                                'เม.ย.',
                                'พ.ค.',
                                'มิ.ย.',
                                'ก.ค.',
                                'ส.ค.',
                                'ก.ย.',
                                'ต.ค.',
                                'พ.ย.',
                                'ธ.ค.',
                            ];
                            return $date->day . ' ' . $months[$date->month] . ' ' . ($date->year + 543);
                        };

                        // ช่วงเวลาที่ใช้อยู่ตอนนี้ — มาจาก $activeShortcut ตัวเดียวกับที่ทำให้ปุ่มทางลัดด้านบนติดสถานะ
                        // "เลือกอยู่" ไม่ใช่คำนวณแยกอีกชุด (ของเดิมคำนวณแยก และคลาดกับ from ที่ลิงก์ทางลัดส่งจริง ทำให้
                        // บรรทัดนี้ไม่เคยขึ้น "6 เดือน" / "12 เดือน" แม้เพิ่งกดปุ่มนั้นมา)
                        $periodLabel = $activeShortcut ? $slaShortcuts[$activeShortcut]['label'] : 'ช่วงวันที่';
                        if ($activeShortcut === 'year') {
                            $fromDate = now()->startOfYear();
                        }
                    @endphp

                    <span class="font-semibold text-slate-700">แสดงข้อมูล:</span>
                    <span class="text-slate-600">
                        {{ $periodLabel }} — {{ $thaiFormat($fromDate) }} ถึง {{ $thaiFormat($toDate) }}
                        <span class="text-[#0F2D5C] font-semibold">({{ number_format($diffDays) }} วัน)</span>
                    </span>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('content')
    <style>
        .font-inter {
            font-family: 'Inter', sans-serif;
        }

        .ms-icon {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        .ms-icon-filled {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>

    <div class="w-full flex flex-col text-slate-900">
        <div class="w-full p-4 md:p-6 max-w-[1664px] mx-auto">

            {{-- what the print dialog submits: the range, the chosen jobs, a note and the signature --}}
            <form id="pdf-form" action="{{ route('maintenance.sla.report') }}" method="POST" class="hidden">
                @csrf
                <input type="hidden" name="from" value="{{ request('from') }}">
                <input type="hidden" name="to" value="{{ request('to') }}">
                <input type="hidden" name="ticket_filter" value="1">
                <input type="hidden" name="tickets" id="tickets-input">
                <input type="hidden" name="note" id="note-input">
                <input type="hidden" name="signature" id="sig-input">
            </form>

            <header class="mb-6">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Stat 1: Response Time -->
                    <div
                        class="bg-white p-6 rounded-lg border border-[#D6E4FF] relative overflow-hidden group hover:bg-slate-50 transition-all flex flex-col items-center justify-center text-center font-inter">
                        <span class="text-[11px] font-semibold text-slate-900 uppercase tracking-widest mb-3">
                            KPI Performance
                        </span>
                        <h3 class="text-sm md:text-base font-semibold uppercase tracking-wider mb-2">
                            เวลาตอบรับ</h3>
                        <div class="flex items-baseline justify-center gap-1">
                            <p class="text-4xl font-bold text-[#0B1F3B] leading-none"
                                data-countup="{{ number_format($dashboard['avg_response_hours'], 1, '.', '') }}">
                                0.0
                            </p>
                            <span class="text-[13px] font-semibold text-slate-400">HR</span>
                        </div>
                        <p class="mt-3 text-[11px] font-medium text-slate-400 leading-snug">
                            เวลาเฉลี่ยที่เจ้าหน้าที่<br>ใช้ในการตอบรับงาน
                        </p>
                    </div>

                    <!-- Stat 2: Acceptance Time -->
                    <div
                        class="bg-white p-6 rounded-lg border border-[#D6E4FF] relative overflow-hidden group hover:bg-slate-50 transition-all flex flex-col items-center justify-center text-center font-inter">
                        <span class="text-[11px] font-semibold text-slate-900 uppercase tracking-widest mb-3">
                            KPI Performance
                        </span>
                        <h3 class="text-sm md:text-base font-semibold uppercase tracking-wider mb-2">
                            เวลารับงาน</h3>
                        <div class="flex items-baseline justify-center gap-1">
                            <p class="text-4xl font-bold text-[#0B1F3B] leading-none"
                                data-countup="{{ number_format($dashboard['avg_acceptance_hours'], 1, '.', '') }}">
                                0.0
                            </p>
                            <span class="text-[13px] font-semibold text-slate-400">HR</span>
                        </div>
                        <p class="mt-3 text-[11px] font-medium text-slate-400 leading-snug">
                            เวลาเฉลี่ยจนถึง<br>เจ้าหน้าที่รับเรื่องซ่อม
                        </p>
                    </div>

                    <!-- Stat 3: Resolution Time -->
                    <div
                        class="bg-white p-6 rounded-lg border border-[#D6E4FF] relative overflow-hidden group hover:bg-slate-50 transition-all flex flex-col items-center justify-center text-center font-inter">
                        <span class="text-[11px] font-semibold text-slate-900 uppercase tracking-widest mb-3">
                            KPI Performance
                        </span>
                        <h3 class="text-sm md:text-base font-semibold uppercase tracking-wider mb-2">
                            เวลาแก้ไขงาน</h3>
                        <div class="flex items-baseline justify-center gap-1">
                            <p class="text-4xl font-bold text-[#0B1F3B] leading-none"
                                data-countup="{{ number_format($dashboard['avg_resolution_hours'], 1, '.', '') }}">
                                0.0
                            </p>
                            <span class="text-[13px] font-semibold text-slate-400">HR</span>
                        </div>
                        <p class="mt-3 text-[11px] font-medium text-slate-400 leading-snug">
                            เวลาเฉลี่ยทั้งหมด<br>ที่ใช้ในการแก้ไขปัญหา
                        </p>
                    </div>

                    <!-- Stat 4: Compliance Rate -->
                    <div
                        class="bg-[#f0f9f1] p-6 rounded-lg border-2 border-[#006c46]/20 relative overflow-hidden group hover:bg-[#e4f2e6] transition-all flex flex-col items-center justify-center text-center font-inter">
                        <span class="text-[11px] font-semibold text-[#006c46]/60 uppercase tracking-widest mb-3">
                            Compliance Data
                        </span>
                        <h3 class="text-[#006c46] text-sm md:text-base font-semibold uppercase tracking-wider mb-2">
                            บรรลุเป้าหมาย SLA</h3>
                        <div class="flex items-center justify-center gap-2">
                            <p class="text-4xl font-bold text-[#006c46] leading-none"
                                data-countup="{{ number_format($dashboard['compliance_rate'], 1, '.', '') }}"
                                data-suffix="%">
                                0%
                            </p>
                            <span
                                class="material-symbols-outlined text-[#006c46] ms-icon-filled text-[24px]">verified</span>
                        </div>
                        <p class="mt-3 text-[11px] font-medium text-[#006c46]/70 leading-snug">
                            อัตราการทำงานสำเร็จ<br>ภายในเวลาที่กำหนด
                        </p>
                    </div>
                </div>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">

                {{-- Main Trend Chart --}}
                <div class="col-span-1 md:col-span-12 lg:col-span-8 flex flex-col py-2">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-[1.15rem] font-semibold text-[#0F2D5C] tracking-tight leading-tight">
                                Performance
                                Trends</h2>
                            <p class="text-[11px] font-medium text-slate-400 mt-0.5">แนวโน้มประสิทธิภาพการให้บริการ</p>
                        </div>
                        <div class="flex gap-4">
                            <span
                                class="flex items-center gap-2 text-[10px] font-semibold text-slate-400 uppercase tracking-widest">
                                <span class="w-2.5 h-2.5 rounded-full bg-[#0F2D5C]"></span> Resolution (H)
                            </span>
                            <span
                                class="flex items-center gap-2 text-[10px] font-semibold text-slate-400 uppercase tracking-widest">
                                <span class="w-2.5 h-2.5 rounded-full bg-[#006c46]"></span> Compliance (%)
                            </span>
                        </div>
                    </div>
                    <div class="flex-1 w-full min-h-[340px]">
                        <canvas id="slaTrendChart" data-labels='@json($chartData['trend']['labels'] ?? [])'
                            data-resolution='@json($chartData['trend']['resolution'] ?? [])'
                            data-compliance='@json($chartData['trend']['compliance'] ?? [])'></canvas>
                    </div>
                </div>

                {{-- Right Sidebar Charts --}}
                <div class="col-span-1 md:col-span-12 lg:col-span-4 flex flex-col gap-8 py-2">
                    <div class="flex-1 flex flex-col">
                        <h2 class="text-[1.15rem] font-semibold text-[#0F2D5C] tracking-tight leading-tight">
                            @if (request('from') || request('to'))
                                Distribution
                            @else
                                Yearly Distribution
                            @endif
                        </h2>
                        <p class="text-[11px] font-medium text-slate-400 mt-0.5 mb-6">สัดส่วนงานแยกตามสถานะ</p>
                        <div class="w-full h-[200px] flex items-center justify-center">
                            <canvas id="slaDistChart" data-labels='@json($chartData['distribution']['labels'] ?? [])'
                                data-values='@json($chartData['distribution']['data'] ?? [])'></canvas>
                        </div>
                    </div>
                    <div class="flex-1 flex flex-col pt-4 border-t border-slate-100">
                        <h3 class="text-[1.15rem] font-semibold text-[#0F2D5C] tracking-tight leading-tight">Breach by
                            Asset Type</h3>
                        <p class="text-[11px] font-medium text-slate-400 mt-0.5">สัดส่วนการผิดนัดสัญญาแยกตามประเภท</p>
                        <div class="flex-1 w-full min-h-[180px]">
                            <canvas id="slaDeptChart" data-labels='@json($chartData['department']['labels'] ?? [])'
                                data-values='@json($chartData['department']['data'] ?? [])'></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="h-[1px] bg-slate-200 w-full my-8"></div>

            {{-- Critical Monitoring & SLA Settings --}}
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-12 mt-4">

                {{-- Left Panel: Critical Priority Monitoring --}}
                <section class="flex flex-col h-[600px] bg-transparent" x-data="{ searchGlobal: '', activeTab: '{{ count($breachedTickets) > 0 ? 'breached' : (count($atRiskTickets) > 0 ? 'atRisk' : 'breached') }}', sortAsc: true }">
                    <div
                        class="pb-4 border-b border-slate-200 flex flex-wrap gap-4 items-center justify-between shrink-0 relative">
                        <div class="flex items-center gap-6">
                            {{-- Tab: Breached --}}
                            <button type="button" @click="activeTab = 'breached'"
                                :class="activeTab === 'breached' ? 'border-rose-600 text-rose-600' :
                                    'border-transparent text-slate-400 hover:text-slate-600 hover:border-slate-200'"
                                class="pb-3 text-[15px] font-bold border-b-[3px] transition-all flex items-center gap-2 -mb-[1px] uppercase tracking-wide">
                                <span>เกินเวลา</span>
                                @if (count($breachedTickets) > 0)
                                    <span
                                        class="px-1.5 py-0.5 rounded bg-rose-600 text-white text-[9px] font-bold">{{ count($breachedTickets) }}</span>
                                @endif
                            </button>
                            {{-- Tab: At Risk --}}
                            <button type="button" @click="activeTab = 'atRisk'"
                                :class="activeTab === 'atRisk' ? 'border-amber-500 text-amber-600' :
                                    'border-transparent text-slate-400 hover:text-slate-600 hover:border-slate-200'"
                                class="pb-3 text-[15px] font-bold border-b-[3px] transition-all flex items-center gap-2 -mb-[1px] uppercase tracking-wide">
                                <span>ใกล้ครบกำหนด</span>
                                @if (count($atRiskTickets) > 0)
                                    <span
                                        class="px-1.5 py-0.5 rounded bg-amber-500 text-white text-[9px] font-bold">{{ count($atRiskTickets) }}</span>
                                @endif
                            </button>
                        </div>

                        {{-- Search Input & Sort Toggle --}}
                        <div class="flex flex-wrap items-center gap-2 pb-3"
                            x-show="{{ count($breachedTickets) + count($atRiskTickets) > 0 ? 'true' : 'false' }}">
                            <div class="relative w-full sm:w-64">
                                <input type="text" x-model="searchGlobal"
                                    placeholder="กรอกเลขที่หรือชื่อปัญหาเพื่อค้นหา..."
                                    class="w-full rounded-md border border-slate-200 bg-white pl-10 pr-3 py-2 text-[13px] placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#0F2D5C]/35 focus:border-[#0F2D5C]/35 transition-all">
                                <span
                                    class="absolute inset-y-0 left-0 flex w-10 items-center justify-center text-slate-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M21 21l-4.3-4.3M17 10a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-y-auto custom-scrollbar flex-1 p-0 flex flex-col">
                        {{-- Breached Tickets Tab Content --}}
                        <template x-if="activeTab === 'breached'">
                            <div class="flex flex-col gap-3 pt-4">
                                @if (count($breachedTickets) > $ticketLimit)
                                    <p class="text-[12px] text-slate-500" style="order: -1">
                                        แสดง {{ $ticketLimit }} รายการที่เกินเวลานานที่สุด จากทั้งหมด
                                        {{ count($breachedTickets) }} รายการ (ค้นหาได้เฉพาะรายการที่แสดง)
                                    </p>
                                @endif
                                @forelse (collect($breachedTickets)->take($ticketLimit) as $t)
                                    @php $timeStr = $t->overdueLabel(); @endphp
                                    <a href="{{ route('maintenance.requests.show', $t->id) }}"
                                        class="p-3 border border-red-100 rounded-lg bg-red-50/30 flex items-start flex-wrap gap-3 hover:bg-red-50/60 transition-all group"
                                        x-bind:style="'order: ' + (sortAsc ? {{ $loop->index }} :
                                            {{ count($breachedTickets) - $loop->index }})"
                                        x-show="searchGlobal === '' || 
                                                '{{ strtolower($t->request_no) }}'.includes(searchGlobal.toLowerCase()) || 
                                                '{{ strtolower($t->title) }}'.includes(searchGlobal.toLowerCase()) || 
                                                '{{ strtolower($t->technician?->name ?? '') }}'.includes(searchGlobal.toLowerCase())">
                                        <div
                                            class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center shrink-0 border border-red-200 text-red-600 mt-0.5 group-hover:scale-110 transition-transform">
                                            <span class="material-symbols-outlined text-[18px]">warning</span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div
                                                class="font-bold text-[13px] text-[#0F2D5C] group-hover:text-rose-700 transition-colors truncate block">
                                                {{ $t->title }}
                                            </div>
                                            <div class="text-[11px] text-[#444650] mt-1 flex flex-wrap gap-x-2 gap-y-1">
                                                <span class="font-semibold text-slate-700">#{{ $t->request_no }}</span>
                                                <span class="text-slate-300">|</span>
                                                <span>{{ $t->technician?->name ?? 'ยังไม่ระบุเจ้าหน้าที่' }}</span>
                                                <span class="text-slate-300">|</span>
                                                <span class="truncate">{{ $t->type?->name ?? 'ทั่วไป' }}</span>
                                            </div>
                                        </div>
                                        <div
                                            class="shrink-0 text-right ms-auto sm:ms-0 mt-2 sm:mt-0 bg-white px-2 py-1 rounded border border-red-100">
                                            <div class="text-[9px] font-bold text-red-600 uppercase">เกินกำหนดล่าช้า
                                            </div>
                                            <div class="text-[13px] font-black text-red-600">{{ $timeStr }}</div>
                                        </div>
                                    </a>
                                @empty
                                    <div
                                        class="p-8 text-center flex flex-col items-center justify-center gap-2 opacity-60 m-auto">
                                        <span
                                            class="material-symbols-outlined text-4xl text-[#006c46] ms-icon">verified_user</span>
                                        <p class="text-[#444650] font-medium text-[13px]">ไม่มีงานที่เกินเวลาในขณะนี้
                                        </p>
                                    </div>
                                @endforelse
                            </div>
                        </template>

                        {{-- At Risk Tickets Tab Content --}}
                        <template x-if="activeTab === 'atRisk'">
                            <div class="flex flex-col gap-3 pt-4">
                                @if (count($atRiskTickets) > $ticketLimit)
                                    <p class="text-[12px] text-slate-500" style="order: -1">
                                        แสดง {{ $ticketLimit }} รายการที่ใกล้ครบกำหนดที่สุด จากทั้งหมด
                                        {{ count($atRiskTickets) }} รายการ (ค้นหาได้เฉพาะรายการที่แสดง)
                                    </p>
                                @endif
                                @forelse (collect($atRiskTickets)->take($ticketLimit) as $t)
                                    @php
                                        $now = \Carbon\Carbon::now();
                                        $diffInMins = (int) $now->diffInMinutes($t->slaDeadline($now));
                                        // 4 hours warning baseline (total = 240 mins)
                                        $pct = min(100, max(0, 100 - ($diffInMins / (4 * 60)) * 100));
                                        $hrs = floor($diffInMins / 60);
                                        $mins = $diffInMins % 60;
                                    @endphp
                                    <a href="{{ route('maintenance.requests.show', $t->id) }}"
                                        class="p-3 border border-amber-100 rounded-lg bg-orange-50/20 flex flex-wrap items-start gap-3 hover:bg-orange-50/50 transition-all relative overflow-hidden group"
                                        x-bind:style="'order: ' + (sortAsc ? {{ $loop->index }} :
                                            {{ count($atRiskTickets) - $loop->index }})"
                                        x-show="searchGlobal === '' || 
                                                '{{ strtolower($t->request_no) }}'.includes(searchGlobal.toLowerCase()) || 
                                                '{{ strtolower($t->title) }}'.includes(searchGlobal.toLowerCase()) || 
                                                '{{ strtolower($t->technician?->name ?? '') }}'.includes(searchGlobal.toLowerCase())">

                                        {{-- 2px Thin Progress Bar at bottom of card --}}
                                        <div class="absolute bottom-0 left-0 h-[2px] bg-slate-100 w-full">
                                            <div class="h-full bg-[#f59e0b]" style="width: {{ $pct }}%">
                                            </div>
                                        </div>

                                        <div
                                            class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center shrink-0 border border-amber-200 text-[#d97706] mt-0.5 group-hover:scale-110 transition-transform">
                                            <span class="material-symbols-outlined text-[18px]">schedule</span>
                                        </div>
                                        <div class="flex-1 min-w-0 pb-1">
                                            <div
                                                class="font-bold text-[13px] text-[#0F2D5C] group-hover:text-amber-700 transition-colors truncate block">
                                                {{ $t->title }}
                                            </div>
                                            <div class="text-[11px] text-[#444650] mt-1 flex flex-wrap gap-x-2 gap-y-1">
                                                <span class="font-semibold text-slate-700">#{{ $t->request_no }}</span>
                                                <span class="text-slate-300">|</span>
                                                <span>{{ $t->technician?->name ?? 'ยังไม่ระบุเจ้าหน้าที่' }}</span>
                                                <span class="text-slate-300">|</span>
                                                <span class="truncate">{{ $t->type?->name ?? 'ทั่วไป' }}</span>
                                            </div>
                                        </div>
                                        <div
                                            class="shrink-0 text-right ms-auto sm:ms-0 mt-2 sm:mt-0 bg-white px-2 py-1 rounded border border-amber-100 z-10 relative">
                                            <div class="text-[9px] font-bold text-[#d97706] uppercase">เหลือเวลาอีก
                                            </div>
                                            <div class="text-[13px] font-black text-[#d97706]">{{ $hrs }} ชม.
                                                {{ $mins }} น.</div>
                                        </div>
                                    </a>
                                @empty
                                    <div
                                        class="p-8 text-center flex flex-col items-center justify-center gap-2 opacity-60 m-auto">
                                        <span
                                            class="material-symbols-outlined text-4xl text-[#006c46] ms-icon">check_circle</span>
                                        <p class="text-[#444650] font-medium text-[13px]">
                                            ไม่มีงานที่ใกล้ครบกำหนดในขณะนี้</p>
                                    </div>
                                @endforelse
                            </div>
                        </template>
                    </div>
                </section>

                {{-- Right Panel: SLA Type Targets --}}
                <section class="flex flex-col h-[600px] bg-transparent" x-data="{ isEdited: false }">
                    <form action="{{ route('maintenance.sla.bulk-update-type-default') }}" method="POST"
                        class="flex flex-col h-full"
                        @input="isEdited = true; $el.querySelector('button[type=submit]').classList.add('animate-pulse')">
                        @csrf @method('PATCH')
                        <div
                            class="pb-4 border-b border-slate-200 flex flex-wrap gap-3 items-center justify-between shrink-0">
                            <div>
                                <h2 class="text-[1.15rem] font-semibold text-[#0F2D5C] tracking-tight leading-tight">
                                    SLA Configuration</h2>
                                <p class="text-[11px] font-medium text-slate-400 mt-0.5">กำหนดเป้าหมายเวลาพื้นฐานรายประเภท
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                @can('manage-system')
                                <a href="{{ route('settings.maintenance-types.index') }}"
                                    class="inline-flex items-center overflow-hidden rounded border border-slate-200 bg-white text-[13px] font-bold text-slate-700 hover:bg-slate-50 transition-all group active:scale-95">
                                    <span
                                        class="px-2.5 py-2 bg-slate-50 flex items-center justify-center text-slate-500 group-hover:text-slate-700 border-r border-slate-100">
                                        <span class="material-symbols-outlined text-[17px]">settings</span>
                                    </span>
                                    <span class="px-3 py-2 leading-none">จัดการประเภทงาน</span>
                                </a>
                                @endcan
                                <button type="submit"
                                    :class="isEdited ? 'bg-amber-500 hover:bg-amber-600' : 'bg-[#0F2D5C] hover:bg-[#0F2D5C]/90'"
                                    class="inline-flex items-center overflow-hidden rounded text-[13px] font-bold text-white transition-all group active:scale-95">
                                    <span
                                        class="px-2.5 py-2 bg-black/10 flex items-center justify-center text-white/90 group-hover:text-white border-r border-white/10">
                                        <span class="material-symbols-outlined text-[17px]"
                                            x-text="isEdited ? 'report_problem' : 'save'"></span>
                                    </span>
                                    <span class="px-3 py-2 leading-none"
                                        x-text="isEdited ? 'บันทึกการเปลี่ยนแปลง' : 'บันทึกข้อมูล'"></span>
                                </button>
                            </div>
                        </div>

                        <div class="overflow-y-auto max-h-full custom-scrollbar flex-1 relative">
                            <table class="w-full text-left border-collapse">
                                <thead
                                    class="bg-slate-50 text-[11px] font-black uppercase text-slate-500 sticky top-0 z-10 ">
                                    <tr>
                                        <th class="px-4 py-2 border-b border-slate-200">ประเภทงาน</th>
                                        <th class="px-2 py-2 border-b border-slate-200 text-center w-28">ตอบกลับ (นาที)
                                        </th>
                                        <th class="px-4 py-2 border-b border-slate-200 text-center w-28">ซ่อมเสร็จ
                                            (นาที)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#eceef0]">
                                    @foreach ($jobTypes->take(20) as $jt)
                                        <tr class="hover:bg-slate-50/50 transition-colors">
                                            <td class="px-4 py-2 max-w-[200px]">
                                                <div class="text-[13px] font-bold text-slate-800 truncate"
                                                    title="{{ $jt->name }}">{{ $jt->name }}</div>
                                                <div class="text-[10px] text-slate-500 truncate mt-0.5"
                                                    title="{{ $jt->description }}">{{ $jt->description ?: '-' }}
                                                </div>
                                            </td>
                                            <td class="px-2 py-2 align-middle">
                                                <input type="number" min="0" step="1"
                                                    name="types[{{ $jt->id }}][default_response_minutes]"
                                                    value="{{ $jt->default_response_minutes }}"
                                                    class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-center text-[12px] font-bold text-[#0F2D5C] focus:outline-none focus:border-[#0F2D5C] focus:ring-1 focus:ring-[#0F2D5C]">
                                            </td>
                                            <td class="px-4 py-2 align-middle">
                                                <input type="number" min="0" step="1"
                                                    name="types[{{ $jt->id }}][default_resolution_minutes]"
                                                    value="{{ $jt->default_resolution_minutes }}"
                                                    class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-center text-[12px] font-bold text-[#0F2D5C] focus:outline-none focus:border-[#0F2D5C] focus:ring-1 focus:ring-[#0F2D5C]">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div
                            class="p-3 border-t border-slate-200 shrink-0 text-center text-[11px] text-slate-500 flex items-center justify-center gap-1.5 font-medium">
                            <span class="material-symbols-outlined text-[14px]">info</span>
                            การแก้ไขมีผลกับงานใหม่เท่านั้น ไม่ย้อนหลัง
                        </div>
                    </form>
                </section>
            </div>

        </div>
    </div>
    </div>
@endsection

@section('scripts')
    @vite(['resources/js/maintenance/sla/dashboard.js'])
@endsection

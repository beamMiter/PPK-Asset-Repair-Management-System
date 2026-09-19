@extends('layouts.app')

@php
    $line = 'border-slate-200';
    $assetKey = $asset->getKey();
    $assetCode = $asset->primary_code ?? '#' . $asset->id;
    $createMrUrl = route('maintenance.requests.create', ['asset_id' => $assetKey]);
    $mrListRoute = route('maintenance.requests.index', ['asset_id' => $assetKey]);

    // Policy check (will implement AssetPolicy shortly)
    $canUpdate = Gate::allows('update', $asset);
@endphp

@push('styles')
    <style>
        .ms {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
@endpush

@section('header-wrap-class', 'no-gap')

@section('title', 'Asset ' . ($asset->asset_code ?: '#' . $asset->id))

@section('page-header')
    <div class="w-full bg-slate-50 border-b {{ $line }}">
        <div class="mx-auto max-w-screen-2xl px-4 sm:px-6 lg:px-8 py-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                {{-- LEFT --}}
                <div class="min-w-0">
                    <div class="flex items-start gap-2.5">
                        <span class="mt-1 text-emerald-600">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="3" width="18" height="18" rx="3" />
                                <path d="M7 8h10M7 12h7M7 16h5" />
                            </svg>
                        </span>
                        <div class="min-w-0">
                            <h1 class="text-[20px] sm:text-[22px] font-semibold text-slate-900 leading-tight">
                                ทะเบียนครุภัณฑ์
                                <span
                                    class="ml-2 text-slate-500 text-[13px] sm:text-[14px] font-semibold">#{{ $asset->id }}</span>
                            </h1>
                            <div class="mt-1 text-xs sm:text-[13px] text-slate-600 flex flex-wrap gap-x-4 gap-y-1">
                                <span>ดูรายละเอียดและประวัติครุภัณฑ์</span>
                                @if ($asset->updated_at)
                                    <span>อัปเดต: <span
                                            class="font-medium text-slate-900">{{ $asset->updated_at->format('Y-m-d H:i') }}</span></span>
                                @endif
                                <span>รหัส: <span class="font-semibold text-slate-900">{{ $assetCode }}</span></span>
                                <span class="truncate">ชื่อ: <span
                                        class="font-semibold text-slate-900">{{ $asset->name ?? '—' }}</span></span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- RIGHT --}}
                <div class="flex flex-wrap items-center justify-start sm:justify-end gap-2">
                    <x-ui.button variant="primary" :href="$createMrUrl" icon="add">สร้างคำขอซ่อมใหม่</x-ui.button>
                    @if ($canUpdate)
                        <x-ui.button :href="route('assets.edit', $asset)" icon="edit">แก้ไข</x-ui.button>
                    @endif
                    <x-ui.back-button :fallback="route('assets.index')" />
                </div>
            </div>
            </form>
        </div>
    @endsection

    @section('content')
        <form class="space-y-8 pb-8" onsubmit="return false;">
            <div class="mx-auto max-w-screen-2xl px-3 sm:px-6 lg:px-8 pt-6 mt-8">
                <div class="space-y-12">
                    <div class="relative grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-x-12 gap-y-12">

                        {{-- Vertical Dividers --}}
                        <div class="hidden lg:block xl:hidden absolute inset-y-0 left-1/2 w-px bg-slate-200"></div>
                        <div class="hidden xl:block absolute inset-y-0 left-1/3 w-px bg-slate-200"></div>
                        <div class="hidden xl:block absolute inset-y-0 left-2/3 w-px bg-slate-200"></div>

                        {{-- STEP 1: ข้อมูลหลัก --}}
                        <section>
                            <x-ui.section-head no="1" title="ข้อมูลหลัก" subtitle="ชื่อ รหัส และการเชื่อมต่อ HIS" />

                            <div class="space-y-5 pt-1">
                                <div>
                                    <label class="ui-label">ชื่อครุภัณฑ์ <span
                                            class="text-rose-600 font-bold">*</span></label>
                                    <input type="text" class="ui-input !bg-white"
                                        value="{{ $asset->name ?? '' }}" readonly>
                                </div>
                                <div>
                                    <label class="ui-label">รหัสครุภัณฑ์ <span
                                            class="text-rose-600 font-bold">*</span></label>
                                    <input type="text" class="ui-input !bg-white"
                                        value="{{ $asset->asset_code ?? '' }}" readonly>
                                </div>
                                <div>
                                    <label class="ui-label">ประเภท <span class="ui-hint">(Medical /
                                            IT / Office)</span></label>
                                    <input type="text" class="ui-input !bg-white"
                                        value="{{ $asset->type ?? '' }}" readonly>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between">
                                        <label class="ui-label">เลข รพจ <span class="ui-hint">(HIS
                                                ID)</span></label>
                                        @if ($asset->his_asset_id)
                                            <span
                                                class="mb-1 inline-flex items-center gap-1.5 text-[11px] font-bold text-sky-600">
                                                <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2.5">
                                                    <path
                                                        d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5-5 5 5M12 5v12" />
                                                </svg>
                                                ข้อมูลจากระบบ HIS
                                            </span>
                                        @endif
                                    </div>
                                    <input type="text" class="ui-input !mt-0 !bg-white"
                                        value="{{ $asset->his_asset_id ?? '—' }}" readonly>
                                </div>
                            </div>
                        </section>

                        {{-- STEP 2: รายละเอียดทางเทคนิค --}}
                        <section>
                            <x-ui.section-head no="2" title="รายละเอียดทางเทคนิค" subtitle="ยี่ห้อ รุ่น Serial และที่ตั้ง" />

                            <div class="space-y-5 pt-1">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="ui-label">ยี่ห้อ</label>
                                        <input type="text" class="ui-input !bg-white"
                                            value="{{ $asset->brand ?? '' }}" readonly>
                                    </div>
                                    <div>
                                        <label class="ui-label">รุ่น</label>
                                        <input type="text" class="ui-input !bg-white"
                                            value="{{ $asset->model ?? '' }}" readonly>
                                    </div>
                                </div>
                                <div>
                                    <label class="ui-label">Serial Number</label>
                                    <input type="text" class="ui-input !bg-white"
                                        value="{{ $asset->serial_number ?? '' }}" readonly>
                                </div>
                                <div>
                                    <label class="ui-label">เบอร์ติดต่อภายใน <span
                                            class="ui-hint">(ป้ายเหลือง)</span></label>
                                    <input type="text" class="ui-input !bg-white"
                                        value="{{ $asset->internal_phone ?? '' }}" readonly>
                                </div>
                                <div>
                                    <label class="ui-label">ที่ตั้ง / ห้อง / สถานที่ใช้งาน</label>
                                    <input type="text" class="ui-input !bg-white"
                                        value="{{ $asset->location ?? '' }}" readonly>
                                </div>
                            </div>
                        </section>

                        {{-- STEP 3: ข้อมูลการจัดซื้อ --}}
                        <section>
                            <x-ui.section-head no="3" title="ข้อมูลการจัดซื้อ" subtitle="ผู้ขาย ราคา และวันจัดซื้อ" />

                            <div class="space-y-5 pt-1">
                                <div>
                                    <label class="ui-label">ชื่อผู้ขาย / ตัวแทนจำหน่าย</label>
                                    <input type="text" class="ui-input !bg-white"
                                        value="{{ $asset->vendor_name ?? '' }}" readonly>
                                </div>
                                <div>
                                    <label class="ui-label">เบอร์ติดต่อผู้ขาย</label>
                                    <input type="text" class="ui-input !bg-white"
                                        value="{{ $asset->vendor_phone ?? '' }}" readonly>
                                </div>
                                <div>
                                    <label class="ui-label">ราคาจัดซื้อ <span
                                            class="ui-hint">(บาท)</span></label>
                                    <input type="text" class="ui-input !bg-white"
                                        value="{{ $asset->formatted_price ?? '—' }}" readonly>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="ui-label">วันที่จัดซื้อ</label>
                                        <input type="text" class="ui-input !bg-white"
                                            value="{{ optional($asset->purchase_date)->format('d/m/Y') ?? '—' }}"
                                            readonly>
                                    </div>
                                    <div>
                                        <label class="ui-label">ประกันสิ้นสุด</label>
                                        <input type="text" class="ui-input !bg-white"
                                            value="{{ optional($asset->warranty_expire)->format('d/m/Y') ?? '—' }}"
                                            readonly>
                                    </div>
                                </div>
                            </div>
                        </section>

                        {{-- Horizontal Divider --}}
                        <div class="col-span-full border-t border-slate-200"></div>

                        {{-- STEP 4: การจัดกลุ่ม & สถานะ --}}
                        <section>
                            <x-ui.section-head no="4" title="หมวดหมู่ และสถานะ" subtitle="จัดกลุ่ม / ระบุเจ้าของ / สถานะ" />

                            <div class="space-y-5 pt-1">
                                <div>
                                    <label class="ui-label">หมวดหมู่ครุภัณฑ์</label>
                                    <input type="text" class="ui-input !bg-white"
                                        value="{{ optional($asset->categoryRef)->name ?? '—' }}" readonly>
                                </div>
                                <div>
                                    <label class="ui-label">หน่วยงานเจ้าของ</label>
                                    <input type="text" class="ui-input !bg-white"
                                        value="{{ optional($asset->department)->name_th ?? (optional($asset->department)->name_en ?? '—') }}"
                                        readonly>
                                </div>
                                <div>
                                    <label class="ui-label">สถานะในระบบ</label>
                                    <input type="text" class="ui-input !bg-white font-bold"
                                        value="{{ $asset->status_label ?? '—' }}" readonly>
                                </div>
                                <div>
                                    <label class="ui-label">หมายเหตุเพิ่มเติม</label>
                                    <textarea rows="5" class="ui-textarea !bg-white" readonly>{{ $asset->note ?? '' }}</textarea>
                                </div>
                            </div>
                        </section>

                        {{-- STEP 5: รูปครุภัณฑ์ --}}
                        <section>
                            <x-ui.section-head no="5" title="ภาพถ่ายครุภัณฑ์" subtitle="รูปภาพถ่ายของครุภัณฑ์" />

                            <div class="space-y-4 pt-1">
                                @php
                                    $heroUrl = $asset->hero_image_url;
                                    $fallbackUrl = asset('images/equipment/default.svg');
                                    $heroFinal = $heroUrl ?: $fallbackUrl;
                                @endphp
                                <div
                                    class="mt-2 relative aspect-video w-full overflow-hidden rounded-lg border border-slate-200 bg-slate-50 ">
                                    <img src="{{ $heroFinal }}" class="h-full w-full object-contain p-4"
                                        alt="Asset Image">
                                    @if (!$heroUrl)
                                        <div
                                            class="absolute inset-x-0 bottom-0 py-2 bg-slate-900/5 backdrop-blur-[1px] text-center">
                                            <span class="text-[10px] font-semibold text-slate-400">ภาพจำลองระบบ</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </section>

                        {{-- STEP 6: ไฟล์แนบ --}}
                        <section>
                            <x-ui.section-head no="6" title="ไฟล์แนบ" subtitle="เอกสาร คู่มือ หรืออื่นๆ" />

                            <div class="space-y-4 pt-1">
                                @php $attached = $asset->attachments ?? collect(); @endphp
                                @if ($attached->count())
                                    <div class="mt-4">
                                        <p class="text-[11px] text-slate-500 mb-2">ไฟล์ที่มีอยู่แล้ว
                                            ({{ $attached->count() }}):</p>
                                        <div class="grid grid-cols-2 gap-2">
                                            @foreach ($attached as $att)
                                                <div
                                                    class="flex items-center justify-between gap-2 p-2 rounded-md border border-slate-100 bg-slate-50/50 text-[11px]">
                                                    <span class="truncate">{{ $att->original_name }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <div
                                        class="mt-2 min-h-[140px] rounded-md border border-dashed border-slate-300 bg-slate-50/50 flex flex-col items-center justify-center p-6 text-center">
                                        <span class="text-[13px] text-slate-400 font-medium italic">ยังไม่มีไฟล์แนบ</span>
                                    </div>
                                @endif
                            </div>
                        </section>

                    </div>

                    {{-- STEP 7: ประวัติการแจ้งซ่อมล่าสุด --}}
                    <section class="mt-12 pt-12 border-t {{ $line }}">
                        <x-ui.section-head no="7" title="ประวัติการแจ้งซ่อมล่าสุด" subtitle="รายการซ่อมล่าสุดของครุภัณฑ์ชิ้นนี้" />

                        <div class="mt-6">
                            @if ($asset->maintenanceRequests && $asset->maintenanceRequests->count() > 0)
                                <div class="space-y-3">
                                    @php
                                        $statusDot = fn(?string $s) => match (strtolower((string) $s)) {
                                            'pending' => 'bg-amber-500',
                                            'acknowledged' => 'bg-sky-500',
                                            'accepted' => 'bg-indigo-500',
                                            'in_progress' => 'bg-sky-500',
                                            'on_hold' => 'bg-slate-400',
                                            'resolved' => 'bg-emerald-500',
                                            'closed' => 'bg-emerald-700',
                                            'cancelled' => 'bg-rose-500',
                                            'rejected' => 'bg-rose-600',
                                            default => 'bg-slate-400',
                                        };
                                        $statusAccentColor = fn(?string $s) => match (strtolower((string) $s)) {
                                            'pending' => '#f59e0b',
                                            'acknowledged' => '#38bdf8',
                                            'accepted' => '#6366f1',
                                            'in_progress' => '#3b82f6',
                                            'on_hold' => '#94a3b8',
                                            'resolved' => '#10b981',
                                            'closed' => '#065f46',
                                            'cancelled' => '#ef4444',
                                            'rejected' => '#e11d48',
                                            default => '#cbd5e1',
                                        };
                                        $statusBadge = fn(?string $s) => match (strtolower((string) $s)) {
                                            'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
                                            'acknowledged' => 'bg-sky-50 text-sky-700 border-sky-200',
                                            'accepted' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                            'in_progress' => 'bg-sky-50 text-sky-700 border-sky-200',
                                            'on_hold' => 'bg-slate-100 text-slate-600 border-slate-200',
                                            'resolved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            'closed' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                            'cancelled' => 'bg-rose-50 text-rose-700 border-rose-200',
                                            'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
                                            default => 'bg-slate-100 text-slate-500 border-slate-200',
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
                                    @endphp

                                    @foreach ($asset->maintenanceRequests->sortByDesc('created_at')->take(1) as $mr)
                                        @php
                                            $mrStatus = strtolower((string) ($mr->status ?? ''));
                                            $mrStatusText = $mr->statusLabel();
                                            $ticketNo = $mr->ticket_no ?? $mr->id;
                                            $createdAtText = $mr->created_at->format('d/m/Y H:i');
                                            $reporterName = $mr->reporter->name ?? 'ระบบ';

                                            // Get assigned technicians
                                            $techWorkers = ($mr->assignments ?? collect())
                                                ->filter(
                                                    fn($a) => strtolower((string) ($a->status ?? '')) !== 'cancelled',
                                                )
                                                ->map(fn($a) => $a->user)
                                                ->filter()
                                                ->unique('id')
                                                ->values();
                                        @endphp

                                        <div
                                            class="bg-white border border-slate-200 rounded-md overflow-hidden flex group">
                                            {{-- Status accent bar --}}
                                            <div class="w-1.5 shrink-0"
                                                style="background-color: {{ $statusAccentColor($mrStatus) }}"></div>

                                            <div class="flex-1 min-w-0">
                                                <div
                                                    class="grid grid-cols-1 md:grid-cols-12 items-center divide-x divide-slate-100">

                                                    {{-- Col 1: Job ID + Status --}}
                                                    <div class="md:col-span-3 px-5 py-4">
                                                        <div
                                                            class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">
                                                            Job ID</div>
                                                        <div class="text-[15px] font-bold text-[#0F2D5C] font-mono">
                                                            #{{ $ticketNo }}</div>

                                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                                            <span
                                                                class="inline-flex items-center px-2 py-0.5 text-[10px] font-bold border rounded {{ $statusBadge($mrStatus) }}">
                                                                <span
                                                                    class="w-1.5 h-1.5 rounded-full mr-1.5 {{ $statusDot($mrStatus) }}"></span>
                                                                {{ $mrStatusText }}
                                                            </span>

                                                        </div>
                                                    </div>

                                                    {{-- Col 2: Problem Description --}}
                                                    <div class="md:col-span-6 px-5 py-4">
                                                        <div
                                                            class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">
                                                            Problem Description</div>
                                                        <h4
                                                            class="text-[14px] font-bold text-slate-900 leading-tight mb-1 truncate">
                                                            <a href="{{ route('maintenance.requests.show', $mr) }}"
                                                                class="hover:text-emerald-700 transition-colors">
                                                                {{ $mr->title }}
                                                            </a>
                                                        </h4>
                                                        <div class="flex items-center gap-3 text-[11px] text-slate-500">
                                                            <span class="flex items-center gap-1">
                                                                <svg class="w-3.5 h-3.5" fill="none"
                                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2"
                                                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                                </svg>
                                                                {{ $createdAtText }}
                                                            </span>
                                                            <span class="flex items-center gap-1">
                                                                <svg class="w-3.5 h-3.5" fill="none"
                                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2"
                                                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                                </svg>
                                                                โดย {{ $reporterName }}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    {{-- Col 3: Technician + Action --}}
                                                    <div
                                                        class="md:col-span-3 px-5 py-4 flex flex-col justify-between h-full bg-slate-50/30">
                                                        <div class="mb-3">
                                                            <div
                                                                class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">
                                                                Technician</div>
                                                            @if ($techWorkers->isEmpty())
                                                                <div
                                                                    class="flex items-center gap-1.5 italic text-slate-400 text-[11px]">
                                                                    <svg class="w-3.5 h-3.5" fill="none"
                                                                        viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round"
                                                                            stroke-linejoin="round" stroke-width="2"
                                                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                                    </svg>
                                                                    ยังไม่มอบหมาย
                                                                </div>
                                                            @else
                                                                <div class="flex items-center -space-x-2">
                                                                    @foreach ($techWorkers->take(3) as $i => $w)
                                                                        @php
                                                                            $ci =
                                                                                abs(crc32($w->name ?? '')) %
                                                                                count($avatarColors);
                                                                            $wAvatar = $w->avatar_thumb_url ?? null;
                                                                        @endphp
                                                                        <div class="w-7 h-7 rounded-full border-2 border-white overflow-hidden "
                                                                            title="{{ $w->name }}">
                                                                            @if ($wAvatar)
                                                                                <img src="{{ $wAvatar }}"
                                                                                    class="w-full h-full object-cover">
                                                                            @else
                                                                                <div
                                                                                    class="w-full h-full {{ $avatarColors[$ci] }} flex items-center justify-center text-white text-[9px] font-bold">
                                                                                    {{ mb_strtoupper(mb_substr($w->name ?? '?', 0, 2)) }}
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    @endforeach
                                                                    @if ($techWorkers->count() > 3)
                                                                        <div
                                                                            class="w-7 h-7 rounded-full border-2 border-white bg-slate-200 flex items-center justify-center text-[9px] font-bold text-slate-600 ">
                                                                            +{{ $techWorkers->count() - 3 }}
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            @endif
                                                        </div>

                                                        <a href="{{ route('maintenance.requests.show', $mr) }}"
                                                            class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-1.5 bg-emerald-600 text-white text-[11px] font-bold rounded hover:bg-emerald-700 transition-colors">
                                                            <span>View Details</span>
                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                                                                stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M9 5l7 7-7 7" />
                                                            </svg>
                                                        </a>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                @php
                                    $isInRepair = $asset->status === \App\Models\Asset::STATUS_IN_REPAIR;
                                @endphp
                                <div
                                    class="text-sm text-slate-500 italic p-8 rounded-md bg-slate-50 border border-dashed border-slate-300 flex flex-col items-center justify-center text-center">
                                    @if ($isInRepair)
                                        <div
                                            class="w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center mb-3">
                                            <svg class="h-6 w-6 text-amber-500" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                        </div>
                                        <p class="font-bold text-amber-700 text-base">กำลังซ่อม (แต่ไม่พบใบแจ้งซ่อม)</p>
                                        <p class="text-[13px] text-slate-500 mt-1 max-w-md">สถานะครุภัณฑ์ถูกตั้งเป็น
                                            "กำลังซ่อม" แต่ยังไม่ได้สร้างใบแจ้งซ่อมในระบบ</p>
                                        <a href="{{ $createMrUrl }}"
                                            class="mt-4 inline-flex items-center justify-center gap-2 px-4 py-2 bg-amber-600 text-white text-sm font-bold rounded-md hover:bg-amber-700 transition-all ">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                            </svg>
                                            สร้างใบแจ้งซ่อมทันที
                                        </a>
                                    @else
                                        <div
                                            class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center mb-3">
                                            <svg class="h-6 w-6 text-slate-300" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 01-2-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <p class="font-medium text-slate-600">ยังไม่มีประวัติการแจ้งซ่อม</p>
                                        <p class="text-[12px] text-slate-400 mt-1">
                                            ประวัติการซ่อมบำรุงทั้งหมดจะถูกรวบรวมไว้ที่นี่</p>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div class="mt-8 flex justify-start">
                            <a href="{{ $mrListRoute }}"
                                class="inline-flex items-center justify-center h-10 px-5 rounded-md bg-emerald-50 border border-emerald-100 text-sm font-medium text-emerald-700 hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-200 transition-colors ">
                                ดูประวัติการแจ้งซ่อมทั้งหมด
                                <svg class="h-4 w-4 ml-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </a>
                        </div>
                    </section>
                </div>
        </form>
    @endsection

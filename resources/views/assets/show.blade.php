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
                                        <x-ui.empty-state icon="attach_file">ยังไม่มีไฟล์แนบ</x-ui.empty-state>
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
                                @php
                                    // same mapping as the requests list (resources/views/maintenance/requests/index.blade.php):
                                    // a status is coloured text, not a boxed badge
                                    $statusTextClass = fn(?string $s) => match (strtolower((string) $s)) {
                                        'acknowledged' => 'text-blue-700',
                                        'pending' => 'text-amber-700',
                                        'accepted' => 'text-emerald-700',
                                        'in_progress' => 'text-sky-700',
                                        'on_hold' => 'text-slate-600',
                                        'resolved' => 'text-emerald-800',
                                        'closed' => 'text-emerald-950',
                                        'cancelled', 'rejected' => 'text-rose-700',
                                        default => 'text-slate-700',
                                    };
                                @endphp

                                {{-- The exact table the requests list (maintenance/requests/index.blade.php) uses: same columns, same
                                     classes, same "✅ Center" convention, so this reads as the same page's own list, not a design
                                     of its own. --}}
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-[13px]">
                                        <thead class="bg-white">
                                            <tr class="text-slate-600">
                                                <th class="p-3 text-center font-semibold w-[10%] whitespace-nowrap border-b border-slate-200">เลขใบงาน</th>
                                                <th class="p-3 text-center font-semibold w-[30%] border-b border-slate-200">เรื่อง/ปัญหา</th>
                                                <th class="p-3 text-center font-semibold w-[12%] border-b border-slate-200">ประเภทงาน</th>
                                                <th class="p-3 text-center font-semibold w-[18%] border-b border-slate-200">ผู้แจ้ง</th>
                                                <th class="p-3 text-center font-semibold w-[10%] whitespace-nowrap border-b border-slate-200">สถานะ</th>
                                                <th class="p-3 text-center font-semibold whitespace-nowrap min-w-[120px] border-b border-slate-200">การจัดการ</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white">
                                            @foreach ($asset->maintenanceRequests->sortByDesc('created_at')->take(1) as $mr)
                                                @php $mrStatus = strtolower((string) ($mr->status ?? '')); @endphp
                                                <tr class="align-top border-b border-slate-100 hover:bg-slate-50/60">
                                                    <td class="p-3 align-middle whitespace-nowrap text-center font-semibold text-slate-900">
                                                        {{ $mr->request_no ?: '#' . $mr->id }}
                                                    </td>
                                                    <td class="p-3 align-middle text-center">
                                                        <a href="{{ route('maintenance.requests.show', $mr) }}"
                                                            class="block max-w-full truncate font-semibold text-slate-900 hover:underline">
                                                            {{ Str::limit($mr->title, 90) }}
                                                        </a>
                                                        @if ($mr->description)
                                                            <p class="mt-1 text-[12px] leading-relaxed text-slate-600">{{ Str::limit($mr->description, 140) }}</p>
                                                        @endif
                                                    </td>
                                                    <td class="p-3 align-middle text-center">
                                                        @if ($mr->type)
                                                            <span class="text-[12px] font-semibold text-slate-700">{{ $mr->type->name }}</span>
                                                        @else
                                                            <span class="text-[12px] text-slate-400">ยังไม่ระบุ</span>
                                                        @endif
                                                    </td>
                                                    <td class="p-3 align-middle text-center">
                                                        <div class="text-[13px] font-semibold text-slate-900">{{ $mr->reporter->name ?? '—' }}</div>
                                                    </td>
                                                    <td class="p-3 align-middle whitespace-nowrap text-center">
                                                        <span class="text-[12px] font-semibold {{ $statusTextClass($mrStatus) }}">{{ $mr->statusLabel() }}</span>
                                                    </td>
                                                    <td class="p-3 text-center whitespace-nowrap align-middle">
                                                        <div class="h-full flex justify-center items-center gap-2">
                                                            <x-ui.button :href="route('maintenance.requests.show', $mr)" size="sm" icon="visibility">ดูรายละเอียด</x-ui.button>
                                                            @can('update', $mr)
                                                                <x-ui.button :href="route('maintenance.requests.edit', $mr)" size="sm" icon="edit">แก้ไข</x-ui.button>
                                                            @endcan
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                @php
                                    $isInRepair = $asset->status === \App\Models\Asset::STATUS_IN_REPAIR;
                                @endphp
                                <div
                                    class="text-sm text-slate-500 italic p-8 rounded-md bg-slate-50 border border-dashed border-slate-300 flex flex-col items-center justify-center text-center">
                                    @if ($isInRepair)
                                        <div class="w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center mb-3">
                                            <span class="material-symbols-outlined text-[28px] text-amber-500" aria-hidden="true">build</span>
                                        </div>
                                        <p class="font-bold text-amber-700 text-base">กำลังซ่อม (แต่ไม่พบใบแจ้งซ่อม)</p>
                                        <p class="text-[13px] text-slate-500 mt-1 max-w-md">สถานะครุภัณฑ์ถูกตั้งเป็น
                                            "กำลังซ่อม" แต่ยังไม่ได้สร้างใบแจ้งซ่อมในระบบ</p>
                                        <x-ui.button :href="$createMrUrl" variant="warning" icon="add" class="mt-4">สร้างใบแจ้งซ่อมทันที</x-ui.button>
                                    @else
                                        <x-ui.empty-state icon="history" hint="ประวัติการซ่อมบำรุงทั้งหมดจะถูกรวบรวมไว้ที่นี่">ยังไม่มีประวัติการแจ้งซ่อม</x-ui.empty-state>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div class="mt-6 flex justify-start">
                            <x-ui.button :href="$mrListRoute">ดูประวัติการแจ้งซ่อมทั้งหมด</x-ui.button>
                        </div>
                    </section>
                </div>
        </form>
    @endsection

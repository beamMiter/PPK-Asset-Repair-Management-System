@extends('layouts.app')

@section('title', 'Request Types')

@section('content')
    @php
        use Illuminate\Support\Facades\Route;

        $search = $search ?? request('search', '');
        $active = isset($active) ? $active : request('active', '');

        $types = $types ?? collect();

        $total = method_exists($types, 'total')
            ? $types->total()
            : (method_exists($types, 'count')
                ? $types->count()
                : 0);

        $primary = '#0F2D5C';

        $statusText = function (bool $isActive) {
            if ($isActive) {
                return '<span class="font-semibold text-emerald-700">ใช้งาน</span>';
            }
            return '<span class="font-semibold text-rose-700">ปิดใช้งาน</span>';
        };
    @endphp

    <div class="w-full flex flex-col">

        <div class="sticky top-16 z-20 bg-white/90 backdrop-blur border-b border-slate-200" x-data="{ showFilters: window.innerWidth >= 768 }">
            <div class="px-4 md:px-6 lg:px-8 py-4">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-start gap-3 min-w-0">
                        {{-- Same glyph as the sidebar's maintenance-types item --}}
                        <span class="material-symbols-outlined text-[32px] text-[#0F2D5C] mt-0.5"
                            aria-hidden="true">build_circle</span>
                        <div>
                            <h1 class="text-[17px] font-semibold text-slate-900">ตั้งค่า - ประเภทงานซ่อม</h1>
                            <p class="text-[13px] text-slate-600">จัดการประเภทงานซ่อม - เพิ่ม/แก้ไข/ปิดใช้งาน</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 w-full md:w-auto">
                        {{-- Filter Toggle (Mobile Only) --}}
                        <button type="button" @click="showFilters = !showFilters"
                            class="md:hidden inline-flex justify-center items-center gap-1.5 h-11 px-4 rounded-md border text-[13px] font-medium transition-colors"
                            :class="showFilters ? 'bg-slate-100 border-slate-300 text-slate-800' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'">
                            <span class="material-symbols-outlined text-[16px]">filter_list</span>
                            <span x-text="showFilters ? 'ซ่อนตัวกรอง' : 'ตัวกรอง'"></span>
                        </button>

                        <x-ui.button :href="route('settings.maintenance-types.create')" variant="primary" icon="add" onclick="showLoader()">เพิ่มประเภท</x-ui.button>
                    </div>
                </div>

                <div x-show="showFilters" x-collapse x-cloak>
                    <form method="GET" action="{{ route('settings.maintenance-types.index') }}"
                        class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end md:!grid" onsubmit="showLoader()">
 
                        <div class="md:col-span-4 lg:col-span-3 min-w-0">
                            <label class="mb-1 block text-[12px] text-slate-600">ค้นหา</label>
                            <div class="relative">
                                <input name="search" value="{{ $search }}" placeholder="ชื่อ / คำอธิบาย"
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
                            <label class="mb-1 block text-[12px] text-slate-600">สถานะ</label>
                            <select name="active"
                                class="w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-[13px] text-slate-800
                                 focus:outline-none focus:ring-2 focus:ring-[#0F2D5C]/35 focus:border-[#0F2D5C]/35">
                                <option value="" @selected($active === '' || $active === null)>ทั้งหมด</option>
                                <option value="1" @selected((string) $active === '1')>ใช้งาน</option>
                                <option value="0" @selected((string) $active === '0')>ปิดใช้งาน</option>
                            </select>
                        </div>
 
                        <div class="md:col-span-1 flex items-end justify-end gap-2">
                            <a href="{{ route('settings.maintenance-types.index') }}" onclick="showLoader()" class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900
                            focus:outline-none focus:ring-2 focus:ring-[#0F2D5C]/30 focus:ring-offset-1" title="รีเซ็ต"
                                aria-label="รีเซ็ต">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </a>
 
                            <button type="submit" class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-[#0F2D5C] text-white hover:bg-[#0F2D5C]/90
                                 focus:outline-none focus:ring-2 focus:ring-[#0F2D5C]/45 focus:ring-offset-1"
                                title="ค้นหา" aria-label="ค้นหา">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21 21l-4.3-4.3M17 10a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="px-4 md:px-6 lg:px-8 py-2 border-b border-slate-200">
            <div class="flex items-center justify-between">
                <div class="text-[13px] font-semibold text-slate-800">รายการประเภทงานซ่อม</div>
                <div class="text-[12px] text-slate-500">ทั้งหมด {{ $total }} รายการ</div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-[13px]">
                <thead class="bg-white">
                    <tr class="text-slate-600">
                        <th class="p-3 text-center font-semibold border-b border-slate-200 w-[60px]">#</th>
                        <th class="p-3 text-left font-semibold border-b border-slate-200">ชื่อประเภทงาน</th>
                        <th class="p-3 text-center font-semibold border-b border-slate-200 w-[110px]">ตอบสนอง</th>
                        <th class="p-3 text-center font-semibold border-b border-slate-200 w-[110px]">ซ่อมเสร็จ</th>
                        <th class="p-3 text-left font-semibold border-b border-slate-200">คำอธิบายรายละเอียด</th>
                        <th class="p-3 text-center font-semibold border-b border-slate-200 w-[110px]">สถานะ</th>
                        <th class="p-3 text-center font-semibold border-b border-slate-200 w-[190px]">จัดการ</th>
                    </tr>
                </thead>

                <tbody class="bg-white">
                    @forelse($types as $t)
                        @php
                            $isActive = (bool) ($t->is_active ?? false);
                        @endphp
                        <tr class="border-b border-slate-100 hover:bg-slate-50/60 transition-colors">
                            <td class="p-3 text-center">
                                <span class="font-bold text-slate-700">{{ (int) ($t->sort_order ?? 0) }}</span>
                            </td>
                            <td class="p-3 font-semibold text-slate-900 whitespace-nowrap">{{ $t->name }}</td>
                            <td class="p-3 text-center">
                                <span class="inline-flex items-center justify-center gap-1.5 text-[13px] font-bold text-slate-700">
                                    <span class="material-symbols-outlined text-[18px] text-[#00275f]">timer</span>
                                    <span>{{ $t->default_response_minutes ?? '-' }} <span class="text-[11px] font-normal text-slate-500">นาที</span></span>
                                </span>
                            </td>
                            <td class="p-3 text-center">
                                <span class="inline-flex items-center justify-center gap-1.5 text-[13px] font-bold text-slate-700">
                                    <span class="material-symbols-outlined text-[18px] text-[#006c46]">build</span>
                                    <span>{{ $t->default_resolution_minutes ?? '-' }} <span class="text-[11px] font-normal text-slate-500">นาที</span></span>
                                </span>
                            </td>
                            <td class="p-3 text-slate-700">{{ $t->description ?: '—' }}</td>
                            <td class="p-3 text-center">{!! $statusText($isActive) !!}</td>
                            <td class="p-3 text-center align-middle whitespace-nowrap">
                                {{-- Same row-action look as the assets / requests / users lists: outlined edit (emerald) + outlined destructive (rose), each with an icon --}}
                                <div class="inline-flex items-center justify-center gap-2">
                                    <a href="{{ route('settings.maintenance-types.edit', $t->id) }}"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-emerald-300 bg-white px-3 py-1.5 text-[12px] font-medium text-emerald-700 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-600"
                                        onclick="showLoader()">
                                        <span
                                            class="material-symbols-outlined ms text-[15px] leading-none text-emerald-600">edit</span>
                                        แก้ไข
                                    </a>

                                    <form method="POST" action="{{ route('settings.maintenance-types.destroy', $t->id) }}"
                                        class="inline" onsubmit="return confirm('ยืนยันปิดใช้งานประเภทนี้?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="inline-flex items-center gap-1.5 rounded-md border border-rose-300 bg-white px-3 py-1.5 text-[12px] font-medium text-rose-600 hover:bg-rose-50 focus:outline-none focus:ring-2 focus:ring-rose-500/30">
                                            <span
                                                class="material-symbols-outlined ms text-[15px] leading-none text-rose-500">block</span>
                                            ปิดใช้งาน
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-16 text-center text-slate-600">
                                <x-ui.empty-state>ไม่พบรายการประเภทงาน</x-ui.empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if (method_exists($types, 'links') && $types->hasPages())
            <div class="px-4 md:px-6 lg:px-8 mt-4 mb-6">
                {{ $types->withQueryString()->links() }}
            </div>
        @endif
    </div>
@endsection
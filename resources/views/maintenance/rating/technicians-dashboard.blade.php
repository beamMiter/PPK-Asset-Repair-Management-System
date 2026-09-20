@extends('layouts.app')

@section('title', 'Technician Ratings')

@php
    $avg = $globalAvg ?? round($technicians->avg('technician_ratings_avg_score'), 2);
    $sumReviews = $totalReviews ?? $technicians->sum('technician_ratings_count');
    $totalTech = $totalTech ?? $technicians->count();

    $chartLabels = $chartLabels ?? $technicians->pluck('name');
    $chartScores = ($chartAvg ?? $technicians->pluck('technician_ratings_avg_score'))->map(fn($v) => round($v, 2));

    $levelLabel = fn($score) => $score >= 4.5
        ? 'ดีมาก'
        : ($score >= 4.0
            ? 'ดี'
            : ($score >= 3.0
                ? 'ปานกลาง'
                : 'ควรปรับปรุง'));

    $levelTextClass = fn($score) => match (true) {
        $score >= 4.0 => 'text-emerald-600',
        $score >= 3.0 => 'text-amber-600',
        default => 'text-rose-600',
    };

    $getInitials = function ($name) {
        $name = trim((string) $name);
        $parts = preg_split('/\s+/u', $name) ?: [];
        $first = mb_substr($parts[0] ?? 'U', 0, 1);
        $second = mb_substr($parts[1] ?? '', 0, 1);
        return strtoupper($first . $second);
    };
@endphp

@section('content')
    <div class="w-full flex flex-col">

        {{-- Sticky Header --}}
        <div class="sticky top-[var(--topbar-h)] z-20 bg-white/95 backdrop-blur-md border-b border-slate-200"
            x-data="{ showFilters: window.innerWidth >= 768 }">
            <div class="px-4 md:px-6 lg:px-8 py-3.5">
                {{-- Stats Row - Top on Mobile --}}
                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-[12px] md:text-[13px] mb-4 md:hidden border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-slate-500 font-medium">รวมในทีม:</span>
                        <span class="font-semibold text-slate-900"
                            data-countup="{{ $totalTech }}">{{ $totalTech }}</span>
                    </div>
                    <div class="flex items-center gap-2 pl-3 border-l border-slate-200">
                        <span class="text-slate-500 font-medium">คะแนนเฉลี่ย:</span>
                        <span class="font-semibold text-emerald-700"
                            data-countup="{{ $avg }}">{{ number_format($avg, 2) }}</span>
                    </div>
                    <div class="flex items-center gap-2 pl-3 border-l border-slate-200">
                        <span class="text-slate-500 font-medium">ประเมินรวม:</span>
                        <span class="font-semibold text-indigo-700"
                            data-countup="{{ $sumReviews }}">{{ number_format($sumReviews) }}</span>
                    </div>
                </div>

                <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
                    <div class="flex items-start gap-3 flex-1 min-w-0">
                        <img src="{{ asset('icon/popularity.webp') }}" class="w-8 h-8 object-contain mt-0.5" alt="">
                        <div>
                            <h1 class="text-[17px] font-semibold text-slate-900 leading-tight">Technician Evaluation Summary
                            </h1>
                            <p class="text-[13px] text-slate-500 font-medium">
                                สรุปผลการประเมินสะสมทั้งหมด
                                <span class="text-slate-400 font-normal ml-1">แสดงหน้าละ {{ $technicians->perPage() }}
                                    ท่าน</span>
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 w-full md:w-auto justify-end md:justify-start">
                        {{-- Desktop Stats --}}
                        <div class="hidden md:flex items-center gap-x-5 text-[13px]">
                            <div class="flex items-center gap-2">
                                <span class="text-slate-500 font-medium">รวมในทีม:</span>
                                <span class="font-semibold text-slate-900"
                                    data-countup="{{ $totalTech }}">{{ $totalTech }}</span>
                            </div>
                            <div class="flex items-center gap-2 pl-3 border-l border-slate-200">
                                <span class="text-slate-500 font-medium">คะแนนเฉลี่ย:</span>
                                <span class="font-semibold text-emerald-700"
                                    data-countup="{{ $avg }}">{{ number_format($avg, 2) }}</span>
                            </div>
                            <div class="flex items-center gap-2 pl-3 border-l border-slate-200">
                                <span class="text-slate-500 font-medium">ประเมินรวม:</span>
                                <span class="font-semibold text-indigo-700"
                                    data-countup="{{ $sumReviews }}">{{ number_format($sumReviews) }}</span>
                            </div>
                        </div>

                        {{-- Filter Toggle (Mobile Only) --}}
                        <button type="button" @click="showFilters = !showFilters"
                            class="md:hidden flex-1 md:flex-none inline-flex justify-center items-center gap-1.5 h-11 px-4 rounded-md border text-[13px] font-medium transition-colors"
                            :class="showFilters ? 'bg-slate-100 border-slate-300 text-slate-800' :
                                'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'">
                            <span class="material-symbols-outlined text-[16px]">filter_list</span>
                            <span x-text="showFilters ? 'ซ่อนตัวกรอง' : 'ตัวกรอง'"></span>
                        </button>
                    </div>
                </div>


                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end md:!grid" x-show="showFilters" x-collapse
                    x-cloak>
                    <div class="md:col-span-4 lg:col-span-3 min-w-0">
                        <label for="techSearch" class="mb-1 block text-[12px] text-slate-600">ค้นหาพนักงาน</label>
                        <div class="relative">
                            <input id="techSearch" type="text" placeholder="กรอกชื่อเจ้าหน้าที่ที่ต้องการค้นหา..."
                                class="w-full rounded-md border border-slate-200 bg-white pl-10 pr-3 py-2 text-[13px] placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#0F2D5C]/35 focus:border-[#0F2D5C]/35">
                            <span class="absolute inset-y-0 left-0 flex w-9 items-center justify-center text-slate-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21 21l-4.3-4.3M17 10a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </span>
                        </div>
                    </div>
                    <div class="md:col-span-3 lg:col-span-2">
                        <label for="sortSelector" class="mb-1 block text-[12px] text-slate-600">เรียงลำดับข้อมูล</label>
                        <select id="sortSelector"
                            class="w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-[13px] text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#0F2D5C]/35 focus:border-[#0F2D5C]/35">
                            <option value="impact_desc"
                                {{ request('sort', 'impact_desc') == 'impact_desc' ? 'selected' : '' }}>ผลงานดีที่สุด
                                (Impact Score)</option>
                            <option value="score_desc" {{ request('sort') == 'score_desc' ? 'selected' : '' }}>
                                คะแนนเฉลี่ยสูงสุด</option>
                            <option value="count_desc" {{ request('sort') == 'count_desc' ? 'selected' : '' }}>
                                จำนวนการประเมินสูงสุด</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- Chart Section --}}
        @if ($technicians->count())
            <div class="px-4 md:px-6 lg:px-8 py-8 bg-slate-50/30 border-b border-slate-200">
                <div class="max-w-[1664px] mx-auto">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                        <div>
                            <h2 class="text-[1.15rem] font-semibold text-[#0F2D5C] tracking-tight leading-tight">Evaluation
                                Ranking</h2>
                            <p class="text-[11px] font-medium text-slate-400 mt-0.5">กราฟสรุปประสิทธิภาพสูงสุด 15 อันดับแรก
                            </p>
                        </div>
                        <div class="flex gap-4">
                            <span
                                class="flex items-center gap-2 text-[10px] font-semibold text-slate-400 uppercase tracking-widest">
                                <span class="w-2.5 h-2.5 rounded-full bg-[#0F2D5C]"></span> คะแนนเฉลี่ย (0-5)
                            </span>
                        </div>
                    </div>

                    <div class="w-full" style="height:320px">
                        <canvas id="techRatingChart" data-labels='@json($chartLabels)'
                            data-values='@json($chartScores)'></canvas>
                    </div>
                </div>
            </div>
        @endif

        {{-- Table header row --}}
        <div class="px-4 md:px-6 lg:px-8 py-2 border-b border-slate-200">
            <div class="max-w-[1664px] mx-auto flex items-center justify-between">
                <div class="text-[13px] font-semibold text-slate-800">
                    รายละเอียดคะแนนรายบุคคล
                </div>
                <div class="text-[12px] text-slate-500">
                    ทั้งหมด {{ $totalTech }} รายการ
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="max-w-[1664px] mx-auto w-full px-4 md:px-6 lg:px-8">
            <div class="overflow-x-auto">
                <table class="min-w-full text-[13px] border-collapse">
                    <thead class="bg-white">
                        <tr class="text-slate-600">
                            <th class="p-3 text-center font-semibold w-[60px] border-b border-slate-200 whitespace-nowrap">
                                รูปถ่าย</th>
                            <th class="p-3 text-center font-semibold w-[50px] border-b border-slate-200 whitespace-nowrap">
                                ลำดับ
                            </th>
                            <th class="p-3 text-left font-semibold border-b border-slate-200 whitespace-nowrap">
                                ชื่อ–สกุลพนักงาน
                            </th>
                            <th class="p-3 text-left font-semibold border-b border-slate-200 whitespace-nowrap">บทบาท</th>
                            <th class="p-3 text-center font-semibold w-[80px] border-b border-slate-200 whitespace-nowrap">
                                คะแนน
                            </th>
                            <th class="p-3 text-center font-semibold w-[100px] border-b border-slate-200 whitespace-nowrap">
                                ระดับ</th>
                            <th class="p-3 text-center font-semibold w-[100px] border-b border-slate-200 whitespace-nowrap">
                                ประเมินแล้ว</th>
                            <th class="p-3 text-center font-semibold w-[120px] border-b border-slate-200 whitespace-nowrap">
                                คุณภาพ</th>
                            <th
                                class="p-3 text-center font-semibold border-b border-slate-200 whitespace-nowrap min-w-[140px]">
                                การดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white text-slate-700">
                        @forelse($technicians as $i => $t)
                            @php
                                $avgScore = round($t->technician_ratings_avg_score, 2);
                                $roundStar = round($avgScore);
                                $avatarMain = data_get($t, 'avatar_url');
                                $avatarThumb = data_get($t, 'avatar_thumb_url');
                            @endphp
                            <tr class="align-top border-b border-slate-100 hover:bg-slate-50/60 transition-colors">
                                <td class="p-3 align-middle text-center">
                                    <div class="flex justify-center shrink-0">
                                        @if ($avatarThumb || $avatarMain)
                                            <img src="{{ $avatarThumb ?: $avatarMain }}" alt="{{ $t->name }}"
                                                class="h-9 w-9 rounded-full object-cover border border-slate-200 shrink-0">
                                        @else
                                            <div
                                                class="h-9 w-9 rounded-full bg-emerald-600 flex items-center justify-center border border-emerald-700 shrink-0">
                                                <span
                                                    class="text-white text-[13px] font-bold leading-none">{{ $getInitials($t->name) }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="p-3 align-middle text-center text-slate-500 font-medium whitespace-nowrap">
                                    {{ $i + 1 }}</td>
                                <td class="p-3 align-middle">
                                    <div class="flex flex-col">
                                        <span
                                            class="font-semibold text-slate-900 whitespace-nowrap">{{ $t->name }}</span>
                                    </div>
                                </td>
                                <td class="p-3 align-middle">
                                    <span
                                        class="text-[11px] text-slate-500 whitespace-nowrap font-medium tracking-wide uppercase">{{ $t->role_label }}</span>
                                </td>
                                <td class="p-3 align-middle text-center font-semibold text-slate-900">
                                    {{ number_format($avgScore, 2) }}
                                </td>
                                <td class="p-3 align-middle text-center">
                                    <span class="font-bold uppercase tracking-wide {{ $levelTextClass($avgScore) }}">
                                        {{ $levelLabel($avgScore) }}
                                    </span>
                                </td>
                                <td class="p-3 align-middle text-center text-slate-600 font-medium">
                                    {{ number_format($t->technician_ratings_count) }}
                                </td>
                                <td class="p-3 align-middle text-center">
                                    <div class="flex justify-center items-center gap-0.5">
                                        @for ($s = 1; $s <= 5; $s++)
                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                class="h-3 w-3 {{ $s <= $roundStar ? 'text-yellow-400' : 'text-slate-200' }}"
                                                viewBox="0 0 20 20" fill="currentColor">
                                                <path
                                                    d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                            </svg>
                                        @endfor
                                    </div>
                                </td>
                                <td class="p-3 align-middle text-center">
                                    <a href="{{ route('technicians.rating.summary', $t->id) }}"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-indigo-300 bg-white px-2.5 md:px-3 py-1.5 text-[12px] font-medium text-indigo-700 hover:bg-indigo-50 transition-colors whitespace-nowrap justify-center">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none"
                                            stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6zm10 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6z" />
                                        </svg>
                                        <span>ดูรายละเอียด</span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-16 text-center text-slate-500">
                                    ไม่พบข้อมูลคะแนนการประเมินในระบบ
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pagination Links --}}
        @if ($technicians->hasPages())
            <div class="max-w-[1664px] mx-auto w-full px-4 md:px-6 lg:px-8 mt-6 mb-12">
                {{ $technicians->links() }}
            </div>
        @endif
    </div>

@endsection


@section('scripts')
    @vite(['resources/js/maintenance/rating/technicians-dashboard.js'])
@endsection

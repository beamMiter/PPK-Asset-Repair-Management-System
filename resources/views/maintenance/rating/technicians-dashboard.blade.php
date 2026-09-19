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

    {{-- ─────────────────────────────────────────
     RATING DETAIL MODAL
────────────────────────────────────────── --}}
    <div id="ratingModal" role="dialog" aria-modal="true" aria-labelledby="ratingModalTitle"
        class="fixed inset-0 z-[9999] flex items-center justify-center hidden">

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/40 backdrop-blur-[2px]" onclick="closeRatingModal()"></div>

        {{-- Panel --}}
        <div class="relative bg-white w-full max-w-lg mx-4 rounded-md flex flex-col overflow-hidden animate-modal-in"
            style="max-height: 90vh;">

            {{-- ── Modal Header ── --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-white shrink-0">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-[#0F2D5C]" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span id="ratingModalTitle"
                        class="text-[14px] font-semibold text-slate-900">ผลการประเมินรายบุคคล</span>
                </div>
                <button onclick="closeRatingModal()"
                    class="flex items-center justify-center h-7 w-7 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- ── Modal Body ── --}}
            <div class="overflow-y-auto flex-1">

                {{-- Skeleton --}}
                <div id="ratingModalSkeleton" class="px-6 py-5 space-y-4 animate-pulse">
                    <div class="flex items-center gap-4">
                        <div class="h-12 w-12 rounded-full bg-slate-200 shrink-0"></div>
                        <div class="flex-1 space-y-2">
                            <div class="h-4 bg-slate-200 rounded w-2/5"></div>
                            <div class="h-3 bg-slate-200 rounded w-1/4"></div>
                        </div>
                    </div>
                    <div class="h-px bg-slate-100"></div>
                    <div class="h-20 bg-slate-100 rounded-md"></div>
                    <div class="space-y-2">
                        <div class="h-3 bg-slate-100 rounded w-1/4 mb-3"></div>
                        <div class="h-14 bg-slate-100 rounded-md"></div>
                        <div class="h-14 bg-slate-100 rounded-md"></div>
                        <div class="h-14 bg-slate-100 rounded-md"></div>
                    </div>
                </div>

                {{-- Content --}}
                <div id="ratingModalContent" class="hidden"></div>
            </div>

            {{-- ── Modal Footer ── --}}
            <div class="px-6 py-3 border-t border-slate-200 bg-slate-50 flex justify-end shrink-0">
                <button onclick="closeRatingModal()"
                    class="px-4 py-2 rounded-md border border-slate-300 bg-white text-[13px] font-medium text-slate-700 hover:bg-slate-50 transition-colors">
                    ปิด
                </button>
            </div>
        </div>
    </div>

@endsection


@section('scripts')
    @vite(['resources/js/maintenance/rating/technicians-dashboard.js'])

    <script>
        /* ─── Loader ─── */
        function showLoader() {
            document.getElementById('loaderOverlay')?.classList.add('show');
        }

        function hideLoader() {
            document.getElementById('loaderOverlay')?.classList.remove('show');
        }
        document.addEventListener('DOMContentLoaded', hideLoader);

        /* ─── Sort & Search ─── */
        document.addEventListener('DOMContentLoaded', function() {
            const sortSel = document.getElementById('sortSelector');
            if (sortSel) {
                sortSel.addEventListener('change', function() {
                    showLoader();
                    const url = new URL(window.location.href);
                    url.searchParams.set('sort', this.value);
                    // Clear page when sorting changes to show new top results
                    url.searchParams.delete('page');
                    window.location.href = url.toString();
                });
            }

            document.getElementById('techSearch')?.addEventListener('keyup', function() {
                const val = this.value.toUpperCase();
                const rows = document.querySelector('tbody').rows;
                for (let i = 0; i < rows.length; i++) {
                    if (rows[i].cells.length < 3) continue;
                    const name = rows[i].cells[2].textContent.toUpperCase();
                    rows[i].style.display = name.includes(val) ? '' : 'none';
                }
            });

            // Chart handled by Vite bundle (technicians-dashboard.js)
        });

        /* ─── Rating Modal ─── */
        function openRatingModal(userId, url) {
            const modal = document.getElementById('ratingModal');
            const skel = document.getElementById('ratingModalSkeleton');
            const content = document.getElementById('ratingModalContent');

            modal.classList.remove('hidden');
            skel.classList.remove('hidden');
            content.classList.add('hidden');
            content.innerHTML = '';

            fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(r => {
                    if (!r.ok) throw new Error('Network error');
                    return r.json();
                })
                .then(data => renderRatingModal(data))
                .catch(() => {
                    content.innerHTML = `
                    <div class="px-6 py-10 text-center">
                        <p class="text-[13px] text-rose-500">ไม่สามารถโหลดข้อมูลได้ กรุณาลองใหม่อีกครั้ง</p>
                    </div>`;
                    skel.classList.add('hidden');
                    content.classList.remove('hidden');
                });
        }

        function closeRatingModal() {
            document.getElementById('ratingModal').classList.add('hidden');
        }

        /* Close on ESC */
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeRatingModal();
        });

        function starSVG(filled, size = '3.5') {
            const color = filled ? '#FBBF24' : '#E2E8F0';
            return `<svg width="${size === '3.5' ? 14 : 16}" height="${size === '3.5' ? 14 : 16}" viewBox="0 0 20 20" fill="${color}" xmlns="http://www.w3.org/2000/svg">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
        </svg>`;
        }

        function renderRatingModal(d) {
            const skel = document.getElementById('ratingModalSkeleton');
            const content = document.getElementById('ratingModalContent');

            /* Level badge */
            const level = d.avg_score >= 4.5 ? 'ดีมาก' :
                d.avg_score >= 4.0 ? 'ดี' :
                d.avg_score >= 3.0 ? 'ปานกลาง' :
                'ควรปรับปรุง';
            const levelBg = d.avg_score >= 4.0 ? 'bg-emerald-50 text-emerald-700 border-emerald-200' :
                d.avg_score >= 3.0 ? 'bg-amber-50 text-amber-700 border-amber-200' :
                'bg-rose-50 text-rose-700 border-rose-200';

            /* Stars row */
            const starsLg = Array.from({
                length: 5
            }, (_, i) => starSVG(i < Math.round(d.avg_score), '4')).join('');

            /* Reviews */
            let reviewsHTML = '';
            if (d.reviews && d.reviews.length) {
                reviewsHTML = d.reviews.map(r => {
                    const starsSm = Array.from({
                        length: 5
                    }, (_, i) => starSVG(i < r.score, '3')).join('');
                    const comment = r.comment ?
                        `<p class="text-[12px] text-slate-600 mt-1.5 leading-relaxed">${r.comment}</p>` :
                        `<p class="text-[12px] text-slate-400 italic mt-1.5">ไม่มีความคิดเห็น</p>`;
                    return `
                <div class="py-3 border-b border-slate-100 last:border-0">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1">${starsSm}</div>
                        <span class="text-[11px] text-slate-400">${r.created_at}</span>
                    </div>
                    ${comment}
                    <p class="text-[11px] text-slate-400 mt-1.5">ประเมินโดย <span class="font-medium text-slate-500">${r.rater}</span></p>
                </div>`;
                }).join('');
            } else {
                reviewsHTML = `<p class="py-6 text-center text-[13px] text-slate-400">ยังไม่มีการประเมิน</p>`;
            }

            content.innerHTML = `
        <div class="px-6 py-5 space-y-5">

            {{-- ── ข้อมูลพนักงาน ── --}}
            <div class="flex items-center gap-4">
                <img src="${d.avatar_url}"
                     alt="${d.name}"
                     class="h-12 w-12 rounded-full object-cover border border-slate-200 shrink-0">
                <div>
                    <div class="font-semibold text-slate-900 text-[14px] leading-tight">${d.name}</div>
                    <div class="text-[12px] text-slate-500 mt-0.5">${d.role_label}</div>
                </div>
            </div>

            <div class="h-px bg-slate-100"></div>

            {{-- ── สรุปคะแนน ── --}}
            <div class="rounded-md border border-slate-200 bg-slate-50 px-5 py-4">
                <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-3">สรุปผลการประเมิน</div>
                <div class="flex items-center gap-6">
                    <div class="text-center">
                        <div class="text-3xl font-bold text-slate-900 leading-none">${d.avg_score.toFixed(2)}</div>
                        <div class="text-[10px] text-slate-400 mt-1">จาก 5.00</div>
                    </div>
                    <div class="flex-1 space-y-2.5">
                        <div class="flex items-center gap-1.5">
                            ${starsLg}
                        </div>
                        <div class="flex items-center gap-3 text-[12px]">
                            <div class="flex items-center gap-1.5 text-slate-500">
                                <svg class="h-3.5 w-3.5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                ประเมินแล้ว <span class="font-semibold text-slate-700">${d.total_count} ครั้ง</span>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded border text-[11px] font-medium ${levelBg}">${level}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── รายการความคิดเห็น ── --}}
            <div>
                <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-2">ความคิดเห็นล่าสุด</div>
                <div class="review-list custom-scrollbar max-h-56 overflow-y-auto -mx-1 px-1">
                    ${reviewsHTML}
                </div>
            </div>

        </div>`;

            skel.classList.add('hidden');
            content.classList.remove('hidden');
        }
    </script>
@endsection

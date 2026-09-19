@extends('layouts.app')

@section('title', 'สรุปผลการปฏิบัติงาน - ' . $tech->name)

@php
    $avgScore = round((float) $tech->technician_ratings_avg_score, 2);
    $totalReviews = (int) $tech->technician_ratings_count;
    $totalJobs = (int) $tech->technician_assignments_count;

    $levelLabel =
        $avgScore >= 4.5
            ? 'ยอดเยี่ยม'
            : ($avgScore >= 4.0
                ? 'ดีมาก'
                : ($avgScore >= 3.0
                    ? 'ดี'
                    : 'ควรปรับปรุง'));

    $insightText =
        $avgScore >= 4.5
            ? $tech->name . ' มีผลงานเกินมาตรฐานอย่างสม่ำเสมอ แนะนำสำหรับงานที่มีความสำคัญสูงและซับซ้อน'
            : ($avgScore >= 4.0
                ? $tech->name . ' มีผลงานดีเยี่ยม สามารถรับงานได้หลากหลายประเภท'
                : ($avgScore >= 3.0
                    ? $tech->name . ' มีผลงานอยู่ในเกณฑ์ดี ควรพัฒนาทักษะเพิ่มเติม'
                    : $tech->name . ' ควรได้รับการฝึกอบรมเพิ่มเติมเพื่อพัฒนาคุณภาพงาน'));

    $line = 'border-slate-200';

    $avatarColors = [
        'bg-emerald-600',
        'bg-indigo-600',
        'bg-rose-500',
        'bg-amber-500',
        'bg-sky-600',
        'bg-violet-600',
        'bg-teal-600',
    ];
@endphp

@section('page-header')
    <div class="w-full bg-white border-b {{ $line }}">
        <div class="mx-auto max-w-screen-xl px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4 min-w-0">
                    <div class="h-16 w-16 shrink-0 rounded-lg overflow-hidden border {{ $line }} bg-slate-100">
                        @if ($tech->avatar_thumb_url)
                            <img src="{{ $tech->avatar_thumb_url }}" alt="{{ $tech->name }}"
                                class="h-full w-full object-cover">
                        @else
                            <div
                                class="h-full w-full flex items-center justify-center bg-emerald-600 text-white text-2xl font-bold">
                                {{ mb_substr($tech->name, 0, 1) }}
                            </div>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-[22px] font-semibold text-slate-900 leading-tight">{{ $tech->name }}</h1>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-[13px] text-slate-500">
                            <span class="flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor"
                                    stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                {{ $tech->role_label }}
                            </span>
                            @if ($tech->department_name)
                                <span class="flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor"
                                        stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    {{ $tech->department_name }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <x-ui.back-button :fallback="route('maintenance.requests.rating.technicians')" />
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="mx-auto max-w-screen-xl px-4 sm:px-6 lg:px-8 pb-10">

        {{-- Row 1: Rating Distribution + Performance Insights --}}
        <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">

            {{-- Rating Distribution --}}
            <div class="bg-white rounded-lg border {{ $line }} p-6">
                <h3 class="text-[1.05rem] font-semibold text-[#0F2D5C] tracking-tight leading-tight mb-5">การกระจายคะแนน</h3>

                {{-- Big Score + Stars --}}
                <div class="flex items-end gap-3 mb-5">
                    <span class="text-5xl font-bold text-slate-900 leading-none">{{ number_format($avgScore, 2) }}</span>
                    <div class="pb-0.5">
                        <div class="flex items-center gap-0.5 mb-1">
                            @for ($i = 1; $i <= 5; $i++)
                                <svg class="w-4 h-4 {{ $i <= round($avgScore) ? 'text-yellow-400' : 'text-slate-200' }}"
                                    fill="currentColor" viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                            @endfor
                        </div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">คะแนนเฉลี่ย</span>
                    </div>
                </div>

                {{-- Bars --}}
                <div class="space-y-2.5">
                    @foreach ($distribution as $stars => $data)
                        <div class="flex items-center gap-3">
                            <span class="text-[12px] text-slate-500 w-12 shrink-0">{{ $stars }} ดาว</span>
                            <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $data['percent'] }}%">
                                </div>
                            </div>
                            <span class="text-[12px] text-slate-400 w-6 text-right shrink-0">{{ $data['count'] }}</span>
                        </div>
                    @endforeach
                </div>

                <p class="mt-5 text-[12px] text-slate-400">จำนวนการประเมินทั้งหมด: <span
                        class="font-semibold text-slate-600">{{ $totalReviews }}</span></p>
            </div>

            {{-- Performance Insights --}}
            <div class="lg:col-span-2 bg-white rounded-lg border {{ $line }} p-6">
                <h3 class="text-[1.05rem] font-semibold text-[#0F2D5C] tracking-tight leading-tight mb-5">ข้อมูลประสิทธิภาพการทำงาน</h3>

                {{-- 3 Metrics — no border box, just layout --}}
                <div class="grid grid-cols-3 gap-6 mb-6">
                    <div>
                        <div class="flex items-center gap-1.5 mb-2">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">งานที่เสร็จสิ้น</span>
                        </div>
                        <div class="text-4xl font-bold text-slate-900">{{ $totalJobs }}</div>
                    </div>

                    <div>
                        <div class="flex items-center gap-1.5 mb-2">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">ความพึงพอใจ</span>
                        </div>
                        <div class="text-4xl font-bold text-emerald-600">{{ number_format(($avgScore / 5) * 100, 1) }}%
                        </div>
                        <div class="text-[11px] text-slate-400 mt-1">คะแนน {{ number_format($avgScore, 1) }}/5 จากผู้ใช้บริการ
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center gap-1.5 mb-2">
                            <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">เป็นสมาชิกตั้งแต่</span>
                        </div>
                        <div class="text-4xl font-bold text-slate-900">{{ $tech->created_at->format('M y') }}</div>
                        <div class="text-[11px] text-slate-400 mt-1">{{ $levelLabel }}</div>
                    </div>
                </div>

                {{-- Insight box — left border accent style --}}
                <div class="flex items-start gap-3 p-4 bg-blue-50 rounded-md border-l-4 border-blue-500">
                    <svg class="w-4 h-4 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <div class="text-[13px] font-bold text-blue-900 mb-0.5">ประสิทธิภาพเชิงกลยุทธ์</div>
                        <div class="text-[12px] text-blue-700 leading-relaxed">{{ $insightText }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Row 2: Recent Work Orders --}}
        <div class="bg-white rounded-lg border {{ $line }} mb-6 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b {{ $line }}">
                <h3 class="text-[1.05rem] font-semibold text-[#0F2D5C] tracking-tight leading-tight">รายการงานล่าสุด
                </h3>
                <a href="#" class="text-[13px] font-semibold text-indigo-600 hover:text-indigo-800">ดูประวัติทั้งหมด</a>
            </div>
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-500 text-[10px] font-semibold uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3">เลขที่ใบแจ้งซ่อม</th>
                        <th class="px-6 py-3">ผู้แจ้ง</th>
                        <th class="px-6 py-3">วันที่เสร็จสิ้น</th>
                        <th class="px-6 py-3 text-right">การจัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y {{ $line }}">
                    @forelse($recentJobs as $job)
                        <tr class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-3.5 font-semibold text-indigo-600">#{{ $job->maintenance_request_id }}</td>
                            <td class="px-6 py-3.5 text-slate-700 text-[13px]">
                                {{ $job->maintenanceRequest?->reporter?->name ?? 'ไม่ระบุชื่อ' }}</td>
                            <td class="px-6 py-3.5 text-slate-500 text-[13px]">
                                {{ $job->created_at ? $job->created_at->format('d M Y') : '-' }}</td>
                            <td class="px-6 py-3.5 text-right">
                                <a href="{{ route('maintenance.requests.show', $job->maintenance_request_id) }}"
                                    class="text-[13px] font-bold text-indigo-600 hover:text-indigo-800">
                                    ดูรายละเอียด &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-slate-400 italic text-[13px]">
                                ไม่พบประวัติงานล่าสุด</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Row 3: Recent Feedback --}}
        <div class="mb-10">
            <h3 class="text-[1.05rem] font-semibold text-[#0F2D5C] tracking-tight leading-tight mb-4">ความคิดเห็นล่าสุด</h3>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @forelse($reviews as $review)
                    @php
                        $colorIndex = crc32(strtolower($review->rater?->name ?? 'u')) % count($avatarColors);
                        $avatarBg = $avatarColors[$colorIndex];
                    @endphp
                    <div class="bg-white rounded-lg border {{ $line }} p-5">
                        {{-- Header: avatar + name + role | stars --}}
                        <div class="flex items-start justify-between gap-3 mb-4">
                            <div class="flex items-center gap-3">
                                <div
                                    class="h-10 w-10 rounded-full {{ $avatarBg }} flex items-center justify-center font-bold text-white text-[13px] shrink-0">
                                    {{ mb_strtoupper(mb_substr($review->rater?->name ?? 'U', 0, 2)) }}
                                </div>
                                <div>
                                    <div class="font-semibold text-slate-900 text-[14px] leading-tight">
                                        {{ $review->rater?->name ?? 'ไม่ระบุชื่อ' }}</div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">
                                        {{ $review->rater?->role_label ?? 'User' }}</div>
                                </div>
                            </div>
                            {{-- Stars right of name area --}}
                            <div class="flex items-center gap-0.5 shrink-0 mt-0.5">
                                @for ($i = 1; $i <= 5; $i++)
                                    <svg class="w-4 h-4 {{ $i <= $review->score ? 'text-emerald-500' : 'text-slate-200' }}"
                                        fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                @endfor
                            </div>
                        </div>

                        {{-- Comment --}}
                        <p class="text-[13px] text-slate-600 leading-relaxed italic mb-4">
                            @if ($review->comment)
                                {{ $review->comment }}
                            @else
                                <span class="text-slate-400 not-italic">ไม่มีข้อความความคิดเห็น</span>
                            @endif
                        </p>

                        {{-- Footer: badge + date --}}
                        <div class="flex items-center justify-between">
                            @if ($review->score >= 4)
                                <span
                                    class="text-emerald-600 text-[11px] font-semibold uppercase tracking-wider">เสร็จสมบูรณ์</span>
                            @elseif($review->score <= 2)
                                <span
                                    class="text-rose-600 text-[11px] font-semibold uppercase tracking-wider">เร่งด่วน/วิกฤต</span>
                            @else
                                <span
                                    class="text-slate-500 text-[11px] font-semibold uppercase tracking-wider">ตรวจสอบแล้ว</span>
                            @endif
                            <span class="text-[11px] text-slate-400">{{ $review->created_at->format('d M Y') }}</span>
                        </div>
                    </div>
                @empty
                    <div
                        class="lg:col-span-2 py-16 text-center bg-slate-50 rounded-md border-2 border-dashed border-slate-200">
                        <svg class="mx-auto h-10 w-10 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                        </svg>
                        <p class="text-[13px] font-medium text-slate-500">ยังไม่มีรีวิว</p>
                    </div>
                @endforelse
            </div>
        </div>

    </div>
@endsection

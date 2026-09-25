@extends('layouts.app')

@section('title', 'Technician Rating: ' . $tech->name)

@php
    $avgScore = round((float) $tech->technician_ratings_avg_score, 2);
    $totalReviews = (int) $tech->technician_ratings_count;
    $totalJobs = (int) $tech->technician_assignments_count;

    // Too few ratings say little about a person: one 5-star review is not "consistently excellent".
    $thin = \App\Support\RatingLevel::isThin($totalReviews);

    // Of the finished jobs, how many were rated at all (a rating is a choice of the person who reported the job)
    $ratedShare = $totalJobs > 0 ? min(100, (int) round($totalReviews / $totalJobs * 100)) : null;
    $lowShare = $totalReviews > 0 ? (int) round($lowCount / $totalReviews * 100) : 0;

    $duration = function (int $minutes): string {
        if ($minutes < 60) {
            return $minutes . ' นาที';
        }
        if ($minutes < 1440) {
            return intdiv($minutes, 60) . ' ชม. ' . ($minutes % 60) . ' นาที';
        }

        return intdiv($minutes, 1440) . ' วัน ' . intdiv($minutes % 1440, 60) . ' ชม.';
    };

    $scoreTone = fn (int $score) => $score >= 4 ? 'text-emerald-700' : ($score === 3 ? 'text-amber-600' : 'text-rose-600');
    $barTone = fn (int $stars) => $stars >= 4 ? 'bg-emerald-500' : ($stars === 3 ? 'bg-amber-400' : 'bg-rose-400');

    $tile = 'rounded-md border border-slate-200 bg-white p-[16px]';
    $tileLabel = 'text-[11px] font-semibold uppercase tracking-wider text-slate-400';
    $card = 'rounded-md border border-slate-200 bg-white';
    $cardHead = 'flex items-center justify-between gap-3 border-b border-slate-100 px-[16px] py-[12px]';
    $cardTitle = 'text-[13px] font-semibold text-slate-800';
@endphp

@section('page-header')
    <div class="w-full bg-white border-b border-slate-200">
        <div class="px-4 md:px-6 lg:px-8 py-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="h-12 w-12 shrink-0 overflow-hidden rounded-full border border-slate-200 bg-slate-100">
                        <img src="{{ $tech->avatar_thumb_url }}" alt="{{ $tech->name }}" class="h-full w-full object-cover">
                    </div>
                    <div class="min-w-0">
                        <h1 class="text-[17px] font-semibold text-slate-900 leading-tight truncate">{{ $tech->name }}</h1>
                        <p class="text-[13px] text-slate-600">
                            สรุปผลการประเมินความพึงพอใจ · {{ $tech->role_label }}@if ($tech->department_name) · {{ $tech->department_name }}@endif
                        </p>
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
    <div class="w-full px-4 md:px-6 lg:px-8 py-6 flex flex-col gap-[20px]">

        @if ($thin)
            <div class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-[16px] py-[12px] text-[13px] text-amber-800">
                <span class="material-symbols-outlined text-[18px] mt-px" aria-hidden="true">info</span>
                <span>
                    @if ($totalReviews === 0)
                        ยังไม่มีการประเมินของเจ้าหน้าที่ท่านนี้ จึงยังไม่มีคะแนนให้แสดง
                    @else
                        มีการประเมินเพียง {{ $totalReviews }} ครั้ง (ควรมีอย่างน้อย {{ \App\Support\RatingLevel::ENOUGH_REVIEWS }} ครั้ง) —
                        คะแนนเฉลี่ยยังไม่ควรใช้ตัดสินผลงาน
                    @endif
                </span>
            </div>
        @endif

        {{-- ===== ตัวชี้วัดหลัก ===== --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-[12px]">
            <div class="{{ $tile }}">
                <div class="{{ $tileLabel }}">คะแนนเฉลี่ย</div>
                <div class="mt-2 flex items-end gap-[8px]">
                    <span class="text-[28px] leading-none font-semibold text-slate-900">{{ $totalReviews > 0 ? number_format($avgScore, 2) : '-' }}</span>
                    @if ($totalReviews > 0)
                        <x-rating.stars :score="$avgScore" class="pb-[2px]" />
                    @endif
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1">
                    <x-rating.level :average="$avgScore" :count="$totalReviews" pill />
                    @if ($totalReviews > 0)
                        <span class="text-[11px] text-slate-400">{{ number_format($avgScore / 5 * 100, 1) }}% ของคะแนนเต็ม</span>
                    @endif
                </div>
            </div>

            <div class="{{ $tile }}">
                <div class="{{ $tileLabel }}">จำนวนการประเมิน</div>
                <div class="mt-2 text-[28px] leading-none font-semibold text-slate-900">{{ number_format($totalReviews) }}</div>
                <div class="mt-2 text-[11px] text-slate-400">
                    @if ($ratedShare !== null)
                        ผู้แจ้งประเมิน {{ $ratedShare }}% ของงานที่เสร็จสิ้น
                    @else
                        ยังไม่มีงานที่เสร็จสิ้น
                    @endif
                </div>
            </div>

            <div class="{{ $tile }}">
                <div class="{{ $tileLabel }}">งานที่เสร็จสิ้น</div>
                <div class="mt-2 text-[28px] leading-none font-semibold text-slate-900">{{ number_format($totalJobs) }}</div>
                <div class="mt-2 text-[11px] text-slate-400">
                    @if ($repairTime)
                        เวลาซ่อมเฉลี่ย {{ $duration($repairTime['minutes']) }} (จาก {{ $repairTime['jobs'] }} งานล่าสุด)
                    @else
                        ยังไม่มีเวลาซ่อมให้คำนวณ
                    @endif
                </div>
            </div>

            <div class="{{ $tile }}">
                <div class="{{ $tileLabel }}">ได้ 1–2 ดาว</div>
                <div class="mt-2 text-[28px] leading-none font-semibold {{ $lowCount > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ number_format($lowCount) }}</div>
                <div class="mt-2 text-[11px] text-slate-400">
                    @if ($totalReviews > 0)
                        {{ $lowShare }}% ของการประเมินทั้งหมด
                    @else
                        -
                    @endif
                </div>
            </div>
        </div>

        {{-- ===== การกระจายคะแนน + แนวโน้ม ===== --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-[16px]">
            <section class="{{ $card }}">
                <div class="{{ $cardHead }}">
                    <h2 class="{{ $cardTitle }}">การกระจายคะแนน</h2>
                    <span class="text-[12px] text-slate-400">ทั้งหมด {{ number_format($totalReviews) }} ครั้ง</span>
                </div>
                <div class="space-y-[10px] p-[16px]">
                    @foreach ($distribution as $stars => $data)
                        <div class="flex items-center gap-3">
                            <span class="w-12 shrink-0 text-[12px] text-slate-500">{{ $stars }} ดาว</span>
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full {{ $barTone($stars) }}" style="width: {{ round($data['percent'], 1) }}%"></div>
                            </div>
                            <span class="w-8 shrink-0 text-right text-[12px] text-slate-500">{{ $data['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="{{ $card }} lg:col-span-2">
                <div class="{{ $cardHead }}">
                    <h2 class="{{ $cardTitle }}">แนวโน้ม 6 เดือนล่าสุด</h2>
                    <span class="text-[12px] text-slate-400">คะแนนเฉลี่ยรายเดือน (เต็ม 5)</span>
                </div>
                <div class="space-y-[10px] p-[16px]">
                    @foreach ($trend as $row)
                        <div class="flex items-center gap-3">
                            <span class="w-20 shrink-0 text-[12px] text-slate-500">{{ \App\Support\ThaiDate::monthYear($row['month']) }}</span>
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                @if ($row['avg'] !== null)
                                    <div class="h-full rounded-full {{ $barTone((int) round($row['avg'])) }}" style="width: {{ round($row['avg'] / 5 * 100, 1) }}%"></div>
                                @endif
                            </div>
                            @if ($row['avg'] !== null)
                                <span class="w-28 shrink-0 text-right text-[12px] text-slate-600">
                                    <span class="font-semibold">{{ number_format($row['avg'], 2) }}</span>
                                    <span class="text-slate-400">· {{ $row['count'] }} ครั้ง</span>
                                </span>
                            @else
                                <span class="w-28 shrink-0 text-right text-[12px] text-slate-300">ไม่มีการประเมิน</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- ===== รายการงานล่าสุด ===== --}}
        <section class="{{ $card }} overflow-hidden">
            <div class="{{ $cardHead }}">
                <h2 class="{{ $cardTitle }}">งานที่เสร็จสิ้นล่าสุด</h2>
                <span class="text-[12px] text-slate-400">5 รายการล่าสุด</span>
            </div>

            @if ($recentJobs->isEmpty())
                <div class="py-10 text-center text-[13px] text-slate-400">ยังไม่มีงานที่เสร็จสิ้น</div>
            @else
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full text-[13px]">
                        <thead class="bg-white">
                            <tr class="text-slate-600 border-b border-slate-200">
                                <th class="p-3 text-left font-semibold whitespace-nowrap">เลขที่ใบงาน</th>
                                <th class="p-3 text-left font-semibold">เรื่อง</th>
                                <th class="p-3 text-left font-semibold whitespace-nowrap">ผู้แจ้ง</th>
                                <th class="p-3 text-left font-semibold whitespace-nowrap">ปิดงานเมื่อ</th>
                                <th class="p-3 text-center font-semibold whitespace-nowrap">คะแนนที่ได้</th>
                                <th class="p-3 text-center font-semibold whitespace-nowrap">การดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recentJobs as $job)
                                @php
                                    $req = $job->maintenanceRequest;
                                    $closedOn = $req?->closed_at ?? $req?->resolved_at ?? $req?->completed_date;
                                    $jobScore = $jobScores[$job->maintenance_request_id] ?? null;
                                @endphp
                                <tr class="border-b border-slate-100 hover:bg-slate-50/60 transition-colors">
                                    <td class="p-3 align-middle whitespace-nowrap font-semibold text-[#0F2D5C]">#{{ $req?->request_no ?? $job->maintenance_request_id }}</td>
                                    <td class="p-3 align-middle min-w-[200px] text-slate-900"><span class="break-words line-clamp-2">{{ $req?->title ?? '-' }}</span></td>
                                    <td class="p-3 align-middle text-slate-700">{{ $req?->reporter?->name ?? 'ไม่ระบุชื่อ' }}</td>
                                    <td class="p-3 align-middle whitespace-nowrap text-slate-700">{{ $closedOn ? \App\Support\ThaiDate::short($closedOn) : '-' }}</td>
                                    <td class="p-3 align-middle text-center whitespace-nowrap">
                                        @if ($jobScore)
                                            <span class="inline-flex items-center gap-[6px]">
                                                <x-rating.stars :score="$jobScore" size="xs" />
                                                <span class="text-[12px] font-semibold {{ $scoreTone((int) $jobScore) }}">{{ number_format($jobScore, 1) }}</span>
                                            </span>
                                        @else
                                            <span class="text-[12px] text-slate-400">ยังไม่ได้ประเมิน</span>
                                        @endif
                                    </td>
                                    <td class="p-3 align-middle text-center">
                                        <x-ui.button :href="route('maintenance.requests.show', $job->maintenance_request_id)" size="sm"
                                            icon="visibility">ดูรายละเอียด</x-ui.button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="md:hidden grid gap-3 p-4">
                    @foreach ($recentJobs as $job)
                        @php
                            $req = $job->maintenanceRequest;
                            $closedOn = $req?->closed_at ?? $req?->resolved_at ?? $req?->completed_date;
                            $jobScore = $jobScores[$job->maintenance_request_id] ?? null;
                        @endphp
                        <div class="rounded-md border border-slate-200 bg-white p-4">
                            <div class="flex items-start justify-between gap-3 mb-2">
                                <span class="text-[13px] font-semibold text-[#0F2D5C]">#{{ $req?->request_no ?? $job->maintenance_request_id }}</span>
                                @if ($jobScore)
                                    <span class="shrink-0 inline-flex items-center gap-[6px]">
                                        <x-rating.stars :score="$jobScore" size="xs" />
                                        <span class="text-[12px] font-semibold {{ $scoreTone((int) $jobScore) }}">{{ number_format($jobScore, 1) }}</span>
                                    </span>
                                @else
                                    <span class="shrink-0 text-[12px] text-slate-400">ยังไม่ได้ประเมิน</span>
                                @endif
                            </div>
                            <div class="text-[14px] font-semibold text-slate-900 break-words line-clamp-2">{{ $req?->title ?? '-' }}</div>
                            <div class="mt-2 grid gap-1 text-[12px] text-slate-500">
                                <div>ผู้แจ้ง: <span class="font-medium text-slate-700">{{ $req?->reporter?->name ?? 'ไม่ระบุชื่อ' }}</span></div>
                                <div>ปิดงานเมื่อ: <span class="font-medium text-slate-700">{{ $closedOn ? \App\Support\ThaiDate::short($closedOn) : '-' }}</span></div>
                            </div>
                            <div class="mt-3 flex justify-end">
                                <x-ui.button :href="route('maintenance.requests.show', $job->maintenance_request_id)" size="sm"
                                    icon="visibility">ดูรายละเอียด</x-ui.button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ===== ความคิดเห็นล่าสุด ===== --}}
        <section>
            <div class="mb-[12px] flex items-center justify-between gap-3">
                <h2 class="{{ $cardTitle }}">ความคิดเห็นล่าสุด</h2>
                <span class="text-[12px] text-slate-400">เฉพาะการประเมินที่มีความคิดเห็น · ล่าสุด 6 รายการ</span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-[12px]">
                @forelse ($comments as $review)
                    @php $rater = $review->rater; @endphp
                    <div class="{{ $card }} p-[16px]">
                        <div class="mb-3 flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                {{-- the person's photo, or the initials avatar the whole system uses when there is none --}}
                                <div class="h-10 w-10 shrink-0 overflow-hidden rounded-full border border-slate-200 bg-slate-100">
                                    <img src="{{ $rater?->avatar_thumb_url ?? \App\Support\InitialsAvatar::url('User', 128) }}"
                                        alt="{{ $rater?->name }}" class="h-full w-full object-cover" loading="lazy">
                                </div>
                                <div class="min-w-0">
                                    <div class="truncate text-[14px] font-semibold leading-tight text-slate-900">{{ $rater?->name ?? 'ไม่ระบุชื่อ' }}</div>
                                    <div class="mt-0.5 text-[11px] text-slate-400">{{ $rater?->role_label ?? 'ผู้ใช้' }}</div>
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <x-rating.stars :score="$review->score" />
                                <div class="mt-0.5 text-[11px] font-semibold {{ $scoreTone((int) $review->score) }}">{{ \App\Support\RatingLevel::scoreLabel((int) $review->score) }}</div>
                            </div>
                        </div>

                        <p class="break-words text-[13px] italic leading-relaxed text-slate-600">“{{ $review->comment }}”</p>

                        <div class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-3 text-[11px] text-slate-400">
                            @if ($review->request)
                                <a href="{{ route('maintenance.requests.show', $review->request) }}"
                                    class="min-w-0 truncate font-medium text-[#0F2D5C] hover:underline">#{{ $review->request->request_no }} · {{ $review->request->title }}</a>
                            @else
                                <span></span>
                            @endif
                            <span class="shrink-0">{{ \App\Support\ThaiDate::short($review->created_at) }}</span>
                        </div>
                    </div>
                @empty
                    <div class="lg:col-span-2 rounded-md border border-dashed border-slate-200 bg-slate-50 py-12 text-center">
                        <span class="material-symbols-outlined text-slate-300 text-[40px]" aria-hidden="true">chat_bubble</span>
                        <p class="mt-1 text-[13px] font-medium text-slate-500">ยังไม่มีความคิดเห็น</p>
                    </div>
                @endforelse
            </div>
        </section>

    </div>
@endsection

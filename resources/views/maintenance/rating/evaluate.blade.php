@extends('layouts.app')

@section('title', 'Satisfaction Ratings')

@section('header-wrap-class', 'z-[30] bg-white')

@php
    // [label, value, colour] — shown in the header: a row above the title on a phone, at the right of it from md up
    $stats = [
        ['อัตราประเมิน', $submissionRate . '%', 'text-slate-900'],
        ['คะแนนเฉลี่ย', number_format($avgScore, 1), 'text-emerald-700'],
        ['รอประเมิน', number_format($pendingCount), 'text-amber-600'],
        ['ประเมินแล้ว', number_format($totalRatedCount), 'text-indigo-700'],
    ];

    // How much time a job has left, as a small label: green while there is plenty, amber inside a week, red inside three days
    $daysBadge = function (?int $days) use ($expiringDays) {
        if ($days === null) {
            return ['-', 'bg-slate-50 text-slate-500 ring-slate-200'];
        }

        $tone = $days <= 3
            ? 'bg-rose-50 text-rose-700 ring-rose-200'
            : ($days <= $expiringDays ? 'bg-amber-50 text-amber-700 ring-amber-200' : 'bg-emerald-50 text-emerald-700 ring-emerald-200');

        return [$days === 0 ? 'วันสุดท้าย' : "เหลือ {$days} วัน", $tone];
    };

    $scoreTone = fn (int $score) => $score >= 4 ? 'text-emerald-700' : ($score === 3 ? 'text-amber-600' : 'text-rose-600');
@endphp

@section('page-header')
    <div class="w-full bg-white border-b border-slate-200">
        <div class="px-4 md:px-6 lg:px-8 py-4">
            {{-- Stats, phone: above the title (the desktop copy below is hidden there) --}}
            <div class="md:hidden flex flex-wrap items-center gap-x-5 gap-y-2 text-[12px] mb-4 border-b border-slate-100 pb-3">
                @foreach ($stats as $i => [$label, $value, $tone])
                    <div class="flex items-center gap-2 {{ $i ? 'pl-3 border-l border-slate-200' : '' }}">
                        <span class="text-slate-500 font-medium">{{ $label }}:</span>
                        <span class="font-semibold {{ $tone }}">{{ $value }}</span>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-start gap-3">
                    {{-- Header glyph — same style as My Jobs / users / settings pages --}}
                    <span class="material-symbols-outlined text-[32px] text-[#0F2D5C] mt-0.5"
                        aria-hidden="true">rate_review</span>
                    <div>
                        <h1 class="text-[17px] font-semibold text-slate-900">ประเมินความพึงพอใจ</h1>
                        <p class="text-[13px] text-slate-600">
                            จัดการประเมินความพึงพอใจการให้บริการและตรวจสอบประวัติการให้คะแนน
                        </p>
                    </div>
                </div>

                <div class="hidden md:flex items-center gap-x-5 text-[13px]">
                    @foreach ($stats as $i => [$label, $value, $tone])
                        <div class="flex items-center gap-2 {{ $i ? 'pl-3 border-l border-slate-200' : '' }}">
                            <span class="text-slate-500 font-medium">{{ $label }}:</span>
                            <span class="font-semibold {{ $tone }}">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="w-full flex flex-col">

        {{-- ===== งานที่รอการให้คะแนน ===== --}}
        <section id="pending">
            <div class="px-4 md:px-6 lg:px-8 py-3 border-b border-slate-200 flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                <div class="flex items-center gap-2">
                    <h2 class="text-[13px] font-semibold text-slate-800">งานที่รอการให้คะแนน</h2>
                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-200">{{ number_format($pendingCount) }}</span>
                </div>
                <p class="text-[12px] text-slate-500">ประเมินได้ภายใน {{ $deadlineDays }} วันหลังปิดงาน · งานที่ใกล้หมดเวลาอยู่บนสุด</p>
            </div>

            @if ($expiringCount > 0)
                <div class="px-4 md:px-6 lg:px-8 py-2.5 bg-amber-50 border-b border-amber-100 flex items-center gap-2 text-[12px] text-amber-800">
                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">schedule</span>
                    <span>มี {{ number_format($expiringCount) }} งานที่เหลือเวลาประเมินไม่เกิน {{ $expiringDays }} วัน — งานที่เลยกำหนดจะไม่อยู่ในรายการนี้อีก</span>
                </div>
            @endif

            @if ($pendingRequests->isEmpty())
                <div class="flex flex-col items-center justify-center py-16 text-center">
                    <span class="material-symbols-outlined text-slate-300 text-[48px] mb-3" aria-hidden="true">task_alt</span>
                    <h3 class="text-[14px] font-semibold text-slate-800">ไม่มีงานค้างประเมิน</h3>
                    <p class="text-[12px] text-slate-500 mt-1">คุณได้ประเมินงานซ่อมเสร็จสิ้นทั้งหมดแล้ว</p>
                </div>
            @else
                {{-- ===== Table Desktop ===== --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full text-[13px]">
                        <thead class="bg-white">
                            <tr class="text-slate-600 border-b border-slate-200">
                                <th class="p-3 text-left font-semibold whitespace-nowrap">เลขที่ใบงาน</th>
                                <th class="p-3 text-left font-semibold">เรื่อง / สถานที่</th>
                                <th class="p-3 text-left font-semibold whitespace-nowrap hidden lg:table-cell">ผู้ดำเนินการ</th>
                                <th class="p-3 text-left font-semibold whitespace-nowrap hidden lg:table-cell">ปิดงานเมื่อ</th>
                                <th class="p-3 text-center font-semibold whitespace-nowrap">ประเมินได้อีก</th>
                                <th class="p-3 text-center font-semibold whitespace-nowrap">การดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pendingRequests as $req)
                                @php
                                    [$daysText, $daysTone] = $daysBadge($req->rating_days_left);
                                    $closedOn = $req->closed_at ?? $req->resolved_at ?? $req->completed_date;
                                @endphp
                                <tr class="border-b border-slate-100 hover:bg-slate-50/60 transition-colors">
                                    <td class="p-3 align-middle whitespace-nowrap">
                                        <a href="{{ route('maintenance.requests.show', $req) }}"
                                            class="font-semibold text-[#0F2D5C] hover:underline">#{{ $req->request_no }}</a>
                                    </td>
                                    <td class="p-3 align-middle min-w-[220px]">
                                        <div class="font-medium text-slate-900 break-words line-clamp-2">{{ $req->title ?? 'ไม่ระบุหัวข้อ' }}</div>
                                        <div class="mt-0.5 flex items-center gap-1 text-[12px] text-slate-500">
                                            <span class="material-symbols-outlined text-[14px] opacity-60" aria-hidden="true">location_on</span>
                                            <span class="break-words min-w-0">{{ $req->location_text ?: '-' }}</span>
                                        </div>
                                    </td>
                                    <td class="p-3 align-middle text-slate-700 hidden lg:table-cell">{{ $req->technician?->name ?? '-' }}</td>
                                    <td class="p-3 align-middle text-slate-700 whitespace-nowrap hidden lg:table-cell">
                                        {{ $closedOn ? \App\Support\ThaiDate::short($closedOn) : '-' }}</td>
                                    <td class="p-3 align-middle text-center whitespace-nowrap">
                                        <span class="inline-flex items-center rounded-full px-[8px] py-[2px] text-[11px] font-semibold ring-1 {{ $daysTone }}">{{ $daysText }}</span>
                                    </td>
                                    <td class="p-3 align-middle">
                                        <div class="flex items-center justify-center gap-[8px]">
                                            <x-ui.button :href="route('maintenance.requests.show', $req)" size="sm">รายละเอียด</x-ui.button>
                                            {{-- same "rate" action as the post-close dialog: amber + star --}}
                                            <x-ui.button :href="route('maintenance.requests.show', $req) . '?rate=1'" variant="warning" size="sm"
                                                icon="star">ประเมินงาน</x-ui.button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- ===== Mobile Cards ===== --}}
                <div class="md:hidden grid gap-3 px-4 py-4">
                    @foreach ($pendingRequests as $req)
                        @php
                            [$daysText, $daysTone] = $daysBadge($req->rating_days_left);
                            $closedOn = $req->closed_at ?? $req->resolved_at ?? $req->completed_date;
                        @endphp
                        <div class="rounded-md border border-slate-200 bg-white p-4">
                            <div class="flex items-start justify-between gap-3 mb-2">
                                <a href="{{ route('maintenance.requests.show', $req) }}"
                                    class="text-[13px] font-semibold text-[#0F2D5C]">#{{ $req->request_no }}</a>
                                <span class="shrink-0 inline-flex items-center rounded-full px-[8px] py-[2px] text-[11px] font-semibold ring-1 {{ $daysTone }}">{{ $daysText }}</span>
                            </div>
                            <div class="text-[14px] font-semibold text-slate-900 break-words line-clamp-2">{{ $req->title ?? 'ไม่ระบุหัวข้อ' }}</div>
                            <div class="mt-2 grid gap-1 text-[12px] text-slate-500">
                                <div class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px] opacity-60" aria-hidden="true">location_on</span>
                                    <span class="break-words min-w-0">{{ $req->location_text ?: '-' }}</span>
                                </div>
                                <div>ผู้ดำเนินการ: <span class="font-medium text-slate-700">{{ $req->technician?->name ?? '-' }}</span></div>
                                <div>ปิดงานเมื่อ: <span class="font-medium text-slate-700">{{ $closedOn ? \App\Support\ThaiDate::short($closedOn) : '-' }}</span></div>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center justify-end gap-[8px]">
                                <x-ui.button :href="route('maintenance.requests.show', $req)" size="sm">รายละเอียด</x-ui.button>
                                <x-ui.button :href="route('maintenance.requests.show', $req) . '?rate=1'" variant="warning" size="sm"
                                    icon="star">ประเมินงาน</x-ui.button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($pendingRequests->hasPages())
                <div class="px-4 md:px-6 lg:px-8 py-4">
                    {{ $pendingRequests->links() }}
                </div>
            @endif
        </section>

        {{-- ===== ประวัติการประเมิน ===== --}}
        <section id="history" class="mt-6 border-t border-slate-200">
            <div class="px-4 md:px-6 lg:px-8 py-3 border-b border-slate-200 flex items-center gap-2">
                <h2 class="text-[13px] font-semibold text-slate-800">ประวัติการประเมิน</h2>
                <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700 ring-1 ring-indigo-200">{{ number_format($totalRatedCount) }}</span>
            </div>

            @if ($ratedRequests->isEmpty())
                <div class="py-12 text-center">
                    <p class="text-[12px] text-slate-400">ยังไม่มีประวัติการให้คะแนน</p>
                </div>
            @else
                {{-- ===== Table Desktop ===== --}}
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full text-[13px]">
                        <thead class="bg-white">
                            <tr class="text-slate-600 border-b border-slate-200">
                                <th class="p-3 text-left font-semibold whitespace-nowrap">เลขที่ใบงาน</th>
                                <th class="p-3 text-left font-semibold">เรื่อง / ความคิดเห็น</th>
                                <th class="p-3 text-left font-semibold whitespace-nowrap hidden lg:table-cell">ผู้ดำเนินการ</th>
                                <th class="p-3 text-center font-semibold whitespace-nowrap">คะแนน</th>
                                <th class="p-3 text-left font-semibold whitespace-nowrap hidden lg:table-cell">ประเมินเมื่อ</th>
                                <th class="p-3 text-center font-semibold whitespace-nowrap">การดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($ratedRequests as $req)
                                @php $score = (int) $req->rating->score; @endphp
                                <tr class="border-b border-slate-100 hover:bg-slate-50/60 transition-colors">
                                    <td class="p-3 align-middle whitespace-nowrap">
                                        <a href="{{ route('maintenance.requests.show', $req) }}"
                                            class="font-semibold text-[#0F2D5C] hover:underline">#{{ $req->request_no }}</a>
                                    </td>
                                    <td class="p-3 align-middle min-w-[220px]">
                                        <div class="font-medium text-slate-900 break-words line-clamp-2">{{ $req->title }}</div>
                                        @if ($req->rating->comment)
                                            <div class="mt-1 text-[12px] italic text-slate-500 break-words line-clamp-2">“{{ $req->rating->comment }}”</div>
                                        @endif
                                    </td>
                                    <td class="p-3 align-middle text-slate-700 hidden lg:table-cell">{{ $req->technician?->name ?? '-' }}</td>
                                    <td class="p-3 align-middle text-center">
                                        <div class="flex flex-col items-center gap-[4px]">
                                            <x-rating.stars :score="$score" size="xs" />
                                            <span class="text-[12px] font-semibold {{ $scoreTone($score) }}">{{ number_format($score, 1) }} · {{ \App\Support\RatingLevel::scoreLabel($score) }}</span>
                                        </div>
                                    </td>
                                    <td class="p-3 align-middle text-slate-700 whitespace-nowrap hidden lg:table-cell">
                                        {{ \App\Support\ThaiDate::short($req->rating->created_at) }}</td>
                                    <td class="p-3 align-middle text-center">
                                        <x-ui.button :href="route('maintenance.requests.show', $req)" size="sm"
                                            icon="visibility">ดูรายการ</x-ui.button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- ===== Mobile Cards ===== --}}
                <div class="md:hidden grid gap-3 px-4 py-4">
                    @foreach ($ratedRequests as $req)
                        @php $score = (int) $req->rating->score; @endphp
                        <div class="rounded-md border border-slate-200 bg-white p-4">
                            <div class="flex items-start justify-between gap-3 mb-2">
                                <a href="{{ route('maintenance.requests.show', $req) }}"
                                    class="text-[13px] font-semibold text-[#0F2D5C]">#{{ $req->request_no }}</a>
                                <div class="shrink-0 flex items-center gap-[6px]">
                                    <x-rating.stars :score="$score" size="xs" />
                                    <span class="text-[12px] font-semibold {{ $scoreTone($score) }}">{{ number_format($score, 1) }}</span>
                                </div>
                            </div>
                            <div class="text-[14px] font-semibold text-slate-900 break-words line-clamp-2">{{ $req->title }}</div>
                            @if ($req->rating->comment)
                                <div class="mt-2 rounded-md border border-slate-100 bg-slate-50 p-3 text-[12px] italic text-slate-600 break-words">“{{ $req->rating->comment }}”</div>
                            @endif
                            <div class="mt-2 grid gap-1 text-[12px] text-slate-500">
                                <div>ผู้ดำเนินการ: <span class="font-medium text-slate-700">{{ $req->technician?->name ?? '-' }}</span></div>
                                <div>ประเมินเมื่อ: <span class="font-medium text-slate-700">{{ \App\Support\ThaiDate::short($req->rating->created_at) }}</span></div>
                            </div>
                            <div class="mt-3 flex justify-end">
                                <x-ui.button :href="route('maintenance.requests.show', $req)" size="sm"
                                    icon="visibility">ดูรายการ</x-ui.button>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($ratedRequests->hasPages())
                    <div class="px-4 md:px-6 lg:px-8 py-4 mb-8">
                        {{ $ratedRequests->links() }}
                    </div>
                @endif
            @endif
        </section>

    </div>
@endsection

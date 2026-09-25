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

    // One list at a time: with a hundred jobs two lists on one page cannot both be read, or paged
    $tabs = ['pending' => ['รอประเมิน', $pendingCount], 'rated' => ['ประเมินแล้ว', $totalRatedCount]];

    // How much time a job has left, as coloured text like every other status: green while there is plenty, amber inside a
    // week, red inside three days
    $daysLabel = function (?int $days) use ($expiringDays) {
        if ($days === null) {
            return ['-', 'text-slate-400'];
        }

        $tone = $days <= 3 ? 'text-rose-600' : ($days <= $expiringDays ? 'text-amber-600' : 'text-emerald-700');

        return [$days === 0 ? 'วันสุดท้าย' : "เหลือ {$days} วัน", $tone];
    };

    $scoreTone = fn (int $score) => $score >= 4 ? 'text-emerald-700' : ($score === 3 ? 'text-amber-600' : 'text-rose-600');

    // No horizontal padding in here: the search box needs `pl-10` (room for its icon) and Bootstrap, loaded next to Tailwind, makes
    // `px-3` !important — a shared `px-3` here beat `pl-10` and put the magnifier on top of the text. The box says `pl-10 pr-3`
    // (as the users page does), the selects `px-3`.
    $input = 'w-full rounded-md border border-slate-200 bg-white py-2 text-[13px] text-slate-800 placeholder:text-slate-400 '
        . 'focus:outline-none focus:ring-2 focus:ring-emerald-600/30 focus:border-emerald-600/30';
@endphp

@section('page-header')
    <div class="w-full bg-white border-b border-slate-200" x-data="{ showFilters: window.innerWidth >= 768 }">
        <div class="px-4 md:px-6 lg:px-8 pt-4">
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

                <div class="flex items-center gap-x-5">
                    <div class="hidden md:flex items-center gap-x-5 text-[13px]">
                        @foreach ($stats as $i => [$label, $value, $tone])
                            <div class="flex items-center gap-2 {{ $i ? 'pl-3 border-l border-slate-200' : '' }}">
                                <span class="text-slate-500 font-medium">{{ $label }}:</span>
                                <span class="font-semibold {{ $tone }}">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>

                    {{-- Filter Toggle (Mobile Only) --}}
                    <button type="button" @click="showFilters = !showFilters"
                        class="md:hidden inline-flex justify-center items-center gap-1.5 h-11 px-4 rounded-md border text-[13px] font-medium transition-colors"
                        :class="showFilters ? 'bg-slate-100 border-slate-300 text-slate-800' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'">
                        <span class="material-symbols-outlined text-[16px]">filter_list</span>
                        <span x-text="showFilters ? 'ซ่อนตัวกรอง' : 'ตัวกรอง'"></span>
                    </button>
                </div>
            </div>

            {{-- The two lists --}}
            <nav class="mt-4 flex gap-[24px]" aria-label="รายการประเมิน">
                @foreach ($tabs as $key => [$name, $count])
                    <a href="{{ route('maintenance.requests.rating.evaluate', ['tab' => $key]) }}"
                        @if ($tab === $key) aria-current="page" @endif
                        class="-mb-px border-b-2 pb-[12px] text-[13px] font-semibold whitespace-nowrap transition-colors {{ $tab === $key ? 'border-[#0F2D5C] text-[#0F2D5C]' : 'border-transparent text-slate-500 hover:text-slate-800' }}">
                        {{ $name }}
                        <span class="ml-1 font-medium {{ $tab === $key ? 'text-[#0F2D5C]/70' : 'text-slate-400' }}">{{ number_format($count) }}</span>
                    </a>
                @endforeach
            </nav>
        </div>

        {{-- Search + the one filter of the list on show --}}
        <form method="GET" action="{{ route('maintenance.requests.rating.evaluate') }}" x-show="showFilters" x-collapse x-cloak
            class="px-4 md:px-6 lg:px-8 py-3 border-t border-slate-100 grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end md:!grid"
            onsubmit="showLoader()">
            <input type="hidden" name="tab" value="{{ $tab }}">

            <div class="md:col-span-6 min-w-0">
                <label for="q" class="mb-1 block text-[12px] text-slate-600">คำค้นหา</label>
                <div class="relative">
                    <input id="q" name="q" value="{{ $filters['q'] }}" maxlength="100"
                        placeholder="เช่น เลขที่ใบงาน, เรื่อง, สถานที่, ชื่อเจ้าหน้าที่"
                        class="{{ $input }} pl-10 pr-3">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex w-9 items-center justify-center text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.6"
                                d="M21 21l-4.3-4.3M17 10a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                </div>
            </div>

            <div class="md:col-span-4 min-w-0">
                @if ($tab === 'pending')
                    <label for="urgency" class="mb-1 block text-[12px] text-slate-600">เวลาที่เหลือ</label>
                    <select id="urgency" name="urgency" class="{{ $input }} px-3">
                        <option value="">ทุกงานที่รอประเมิน</option>
                        <option value="soon" @selected($filters['urgency'] === 'soon')>ใกล้หมดเวลา (เหลือไม่เกิน {{ $expiringDays }} วัน)</option>
                    </select>
                @else
                    <label for="score" class="mb-1 block text-[12px] text-slate-600">คะแนนที่ให้</label>
                    <select id="score" name="score" class="{{ $input }} px-3">
                        <option value="">ทุกคะแนน</option>
                        @foreach ([5, 4, 3, 2, 1] as $s)
                            <option value="{{ $s }}" @selected($filters['score'] === $s)>{{ $s }} ดาว - {{ \App\Support\RatingLevel::scoreLabel($s) }}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div class="md:col-span-2 flex items-end justify-end gap-2">
                <a href="{{ route('maintenance.requests.rating.evaluate', ['tab' => $tab]) }}" onclick="showLoader()"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-emerald-600/30 focus:ring-offset-1"
                    title="ล้างค่า" aria-label="ล้างค่า">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </a>
                <button type="submit"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-full bg-emerald-700 text-white hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600/40 focus:ring-offset-1"
                    title="ค้นหา" aria-label="ค้นหา">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.3-4.3M17 10a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </button>
            </div>
        </form>
    </div>
@endsection

@section('content')
    <div class="w-full flex flex-col">

        {{-- How many, and what the window is --}}
        <div class="px-4 md:px-6 lg:px-8 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-[12px] text-slate-500">
            <span>
                {{ $filtered ? 'พบ' : 'ทั้งหมด' }} <span class="font-semibold text-slate-800">{{ number_format($requests->total()) }}</span> รายการ
                @if ($requests->hasPages())
                    - หน้า {{ $requests->currentPage() }} จาก {{ $requests->lastPage() }}
                @endif
            </span>
            @if ($tab === 'pending')
                <span>ประเมินได้ภายใน {{ $deadlineDays }} วันหลังปิดงาน - งานที่ใกล้หมดเวลาอยู่บนสุด</span>
            @endif
        </div>

        @if ($tab === 'pending' && $expiringCount > 0)
            <div class="px-4 md:px-6 lg:px-8 py-2.5 bg-amber-50 border-b border-amber-100 flex flex-wrap items-center gap-x-2 gap-y-1 text-[12px] text-amber-800">
                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">schedule</span>
                <span>มี {{ number_format($expiringCount) }} งานที่เหลือเวลาประเมินไม่เกิน {{ $expiringDays }} วัน — งานที่เลยกำหนดจะไม่อยู่ในรายการนี้อีก</span>
                @if ($filters['urgency'] !== 'soon')
                    <a href="{{ route('maintenance.requests.rating.evaluate', ['tab' => 'pending', 'urgency' => 'soon']) }}"
                        class="font-semibold underline underline-offset-2 hover:text-amber-900">ดูเฉพาะงานเหล่านี้</a>
                @endif
            </div>
        @endif

        @if ($requests->isEmpty())
            <div class="px-4 py-16">
                @if ($filtered)
                    <x-ui.empty-state icon="search_off">
                        ไม่พบรายการที่ตรงกับคำค้นหาหรือตัวกรอง
                        <x-slot:action>
                            <a href="{{ route('maintenance.requests.rating.evaluate', ['tab' => $tab]) }}"
                                class="text-[12px] font-semibold text-[#0F2D5C] hover:underline">ล้างค่าทั้งหมด</a>
                        </x-slot:action>
                    </x-ui.empty-state>
                @elseif ($tab === 'pending')
                    <x-ui.empty-state icon="task_alt" hint="คุณได้ประเมินงานซ่อมเสร็จสิ้นทั้งหมดแล้ว">ไม่มีงานค้างประเมิน</x-ui.empty-state>
                @else
                    <x-ui.empty-state icon="history">ยังไม่มีประวัติการให้คะแนน</x-ui.empty-state>
                @endif
            </div>
        @elseif ($tab === 'pending')
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
                        @foreach ($requests as $req)
                            @php
                                [$daysText, $daysTone] = $daysLabel($req->rating_days_left);
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
                                    <span class="text-[12px] font-semibold {{ $daysTone }}">{{ $daysText }}</span>
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
                @foreach ($requests as $req)
                    @php
                        [$daysText, $daysTone] = $daysLabel($req->rating_days_left);
                        $closedOn = $req->closed_at ?? $req->resolved_at ?? $req->completed_date;
                    @endphp
                    <div class="rounded-md border border-slate-200 bg-white p-4">
                        <div class="flex items-start justify-between gap-3 mb-2">
                            <a href="{{ route('maintenance.requests.show', $req) }}"
                                class="text-[13px] font-semibold text-[#0F2D5C]">#{{ $req->request_no }}</a>
                            <span class="shrink-0 text-[12px] font-semibold {{ $daysTone }}">{{ $daysText }}</span>
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
                        @foreach ($requests as $req)
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
                                        <span class="text-[12px] font-semibold {{ $scoreTone($score) }}">{{ number_format($score, 1) }} - {{ \App\Support\RatingLevel::scoreLabel($score) }}</span>
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
                @foreach ($requests as $req)
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
        @endif

        @if ($requests->hasPages())
            <div class="px-4 md:px-6 lg:px-8 mt-6 mb-12">
                {{ $requests->links() }}
            </div>
        @endif

    </div>
@endsection

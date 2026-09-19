@extends('layouts.app')

@section('title', 'Satisfaction Ratings')

@section('header-wrap-class', 'z-[30] bg-white')

@section('page-header')
    <div class="w-full bg-white border-b border-slate-200">
        <div class="px-4 md:px-6 lg:px-8 py-4">
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

                {{-- Desktop Stats Summary (Matching Technician Dashboard Style) --}}
                <div class="hidden md:flex items-center gap-x-5 text-[13px]">
                    <div class="flex items-center gap-2">
                        <span class="text-slate-500 font-medium">อัตราประเมิน:</span>
                        <span class="font-semibold text-slate-900">{{ $submissionRate }}%</span>
                    </div>
                    <div class="flex items-center gap-2 pl-3 border-l border-slate-200">
                        <span class="text-slate-500 font-medium">คะแนนเฉลี่ย:</span>
                        <span class="font-semibold text-emerald-700">{{ number_format($avgScore, 1) }}</span>
                    </div>
                    <div class="flex items-center gap-2 pl-3 border-l border-slate-200">
                        <span class="text-slate-500 font-medium">รอประเมิน:</span>
                        <span class="font-semibold text-amber-600">{{ number_format($pendingCount) }}</span>
                    </div>
                    <div class="flex items-center gap-2 pl-3 border-l border-slate-200">
                        <span class="text-slate-500 font-medium">ประเมินแล้ว:</span>
                        <span class="font-semibold text-indigo-700">{{ number_format($totalRatedCount) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="w-full flex flex-col" x-data="{ ratingOpen: false, ratingReq: {} }">

        {{-- Stats Summary is now in the header --}}

        <div class="px-4 md:px-6 lg:px-8 py-8 w-full">
            <div class="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-10 items-start">

                {{-- Left Column: งานที่รอการให้คะแนน --}}
                <div class="flex flex-col gap-8">
                    <section>
                        <div class="flex items-center gap-3 mb-6">
                            <h2 class="text-[1.15rem] font-black text-[#0F2D5C] tracking-tight">งานที่รอการให้คะแนน
                            </h2>
                        </div>

                        @if ($pendingRequests->isEmpty())
                            <div
                                class="flex flex-col items-center justify-center py-24 bg-slate-50/50 border border-slate-200 border-dashed rounded-sm">
                                <span class="material-symbols-outlined text-slate-300 text-[48px] mb-4">task_alt</span>
                                <h3 class="text-[14px] font-bold text-slate-800">ไม่มีงานค้างประเมิน</h3>
                                <p class="text-[12px] text-slate-500 mt-1">คุณได้ประเมินงานซ่อมเสร็จสิ้นทั้งหมดแล้ว</p>
                            </div>
                        @else
                            <div class="grid grid-cols-1 gap-4">
                                @foreach ($pendingRequests as $req)
                                    <div
                                        class="group relative bg-white border border-slate-200 rounded-sm p-5 hover:border-[#0F2D5C] transition-all">
                                        <div
                                            class="absolute top-0 left-0 w-1 h-full bg-transparent group-hover:bg-[#0F2D5C] rounded-l-sm transition-all pointer-events-none">
                                        </div>

                                        <div
                                            class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 mb-2">
                                                    <span
                                                        class="text-[11px] font-semibold text-slate-400 uppercase tracking-wide">เลขที่ใบงาน</span>
                                                    <span
                                                        class="text-[13px] font-bold text-[#0F2D5C]">#{{ $req->request_no }}</span>
                                                </div>
                                                <h3
                                                    class="text-[17px] font-bold text-slate-800 mb-3 break-words line-clamp-2 group-hover:text-[#0F2D5C] transition-colors leading-relaxed">
                                                    {{ $req->title ?? 'ไม่ระบุหัวข้อ' }}
                                                </h3>
                                                <div
                                                    class="flex flex-wrap items-center gap-y-2 gap-x-6 text-[13px] text-slate-500">
                                                    <div class="flex items-center gap-2">
                                                        <span
                                                            class="material-symbols-outlined text-[18px] opacity-40">location_on</span>
                                                        <span
                                                            class="break-words min-w-0">{{ $req->location_text ?? '-' }}</span>
                                                    </div>
                                                    @if ($req->technician)
                                                        <div class="flex items-center gap-2">
                                                            <span
                                                                class="material-symbols-outlined text-[18px] opacity-40">engineering</span>
                                                            <span class="text-slate-600 text-[13px]">เจ้าหน้าที่: <span
                                                                    class="font-bold text-slate-800">{{ $req->technician->name }}</span></span>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="flex flex-wrap items-center justify-end gap-[12px] w-full md:w-auto mt-4 md:mt-0">
                                                <x-ui.button :href="route('maintenance.requests.show', $req)">รายละเอียด</x-ui.button>
                                                {{-- same "rate" action as the post-close dialog: amber + star --}}
                                                <x-ui.button :href="route('maintenance.requests.show', $req) . '?rate=1'" variant="warning"
                                                    icon="star">ประเมินงาน</x-ui.button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($pendingRequests->hasPages())
                            <div class="mt-8">
                                {{ $pendingRequests->links() }}
                            </div>
                        @endif
                    </section>
                </div>

                {{-- Right Column: ประวัติการประเมิน (Sidebar Style) --}}
                <div class="lg:sticky lg:top-40">
                    <section>
                        <div class="flex items-center gap-3 mb-6">
                            <h2 class="text-[1.15rem] font-black text-[#0F2D5C] tracking-tight">ประวัติการประเมิน</h2>
                        </div>

                        @if ($ratedRequests->isEmpty())
                            <div class="bg-slate-50 border border-slate-200 border-dashed rounded-sm p-10 text-center">
                                <p class="text-[12px] text-slate-400">ยังไม่มีประวัติการให้คะแนน</p>
                            </div>
                        @else
                            <div class="flex flex-col gap-4">
                                @foreach ($ratedRequests as $req)
                                    <div
                                        class="bg-white border border-slate-200 rounded-sm p-5 hover:border-slate-300 transition-all">
                                        <div class="flex justify-between items-start gap-4 mb-3">
                                            <span
                                                class="text-[11px] font-bold text-slate-400 uppercase tracking-wide">เลขที่ใบงาน
                                                #{{ $req->request_no }}</span>
                                            <div
                                                class="px-2 py-0.5 rounded-sm text-[10px] font-black tracking-wider flex items-center gap-1.5
                                                @if ($req->rating->score >= 4) bg-emerald-50 text-emerald-600 border border-emerald-100
                                                @elseif($req->rating->score >= 3) bg-amber-50 text-amber-600 border border-amber-100
                                                @else bg-rose-50 text-rose-600 border border-rose-100 @endif">
                                                <span class="material-symbols-outlined text-[12px] fill-current">star</span>
                                                {{ number_format($req->rating->score, 1) }}
                                            </div>
                                        </div>

                                        <h4 class="text-[13px] font-bold text-slate-700 mb-2 line-clamp-2 break-words">
                                            {{ $req->title }}</h4>

                                        @if ($req->rating && $req->rating->comment)
                                            <div
                                                class="text-[12px] text-slate-500 italic bg-slate-50 p-3 rounded-sm border-l-2 border-slate-200 break-words overflow-hidden">
                                                "{{ $req->rating->comment }}"
                                            </div>
                                        @endif

                                        <div class="mt-4 flex justify-end">
                                            <x-ui.button :href="route('maintenance.requests.show', $req)" size="sm"
                                                icon="visibility">ดูรายการ</x-ui.button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            @if ($ratedRequests->hasPages())
                                <div class="mt-6">
                                    {{ $ratedRequests->links() }}
                                </div>
                            @endif
                        @endif
                    </section>
                </div>
            </div>
        </div>

    </div>
@endsection

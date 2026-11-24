{{--
  Told to a reporter whose repair is done and waits for their approval (see App\View\Components\ResolvedRequestsBanner): how many, and a link to
  the list already filtered. Green, as the status is. Paddings and gaps are written in pixels: the CDN Bootstrap's !important p-*/gap-* utilities
  beat Tailwind's.
--}}
<div data-resolved-banner role="status"
    {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-[12px] rounded-xl border border-emerald-200 bg-emerald-50 px-[16px] py-[12px] text-emerald-900']) }}>
    <div class="flex min-w-0 items-start gap-[12px]">
        <span class="material-symbols-outlined mt-[2px] text-[24px] text-emerald-600" aria-hidden="true">task_alt</span>
        <div class="min-w-0">
            <div class="text-[14px] font-semibold">มีใบงานของคุณซ่อมบำรุงเสร็จสิ้นแล้ว {{ $waiting }} รายการ รอคุณอนุมัติปิดงาน</div>
            <div class="mt-[2px] text-[12px] leading-relaxed">
                ตรวจสอบผลการซ่อมที่ใบงาน แล้วกด "อนุมัติปิดงาน" หลังจากนั้นให้คะแนนการซ่อมได้
            </div>
        </div>
    </div>
    <a href="{{ route('maintenance.requests.index', ['status' => 'resolved']) }}"
        class="inline-flex items-center rounded-md bg-white px-[12px] py-[6px] text-[12px] font-semibold shadow-sm ring-1 ring-black/10 hover:bg-slate-50">
        ดูใบงานที่รออนุมัติ
    </a>
</div>

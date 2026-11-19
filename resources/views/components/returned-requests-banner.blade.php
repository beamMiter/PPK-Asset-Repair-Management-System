{{--
  Told to a reporter whose requests were sent back to them to fix (see App\View\Components\ReturnedRequestsBanner): how many, how many are close
  to being cancelled automatically, and links to the list already filtered. Red when some are close, orange when not.
  Paddings and gaps are written in pixels: the CDN Bootstrap's !important p-*/gap-* utilities beat Tailwind's.
--}}
<div data-returned-banner role="status"
    {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-[12px] rounded-xl border px-[16px] py-[12px] '.($expiring > 0 ? 'border-rose-200 bg-rose-50 text-rose-900' : 'border-orange-200 bg-orange-50 text-orange-900')]) }}>
    <div class="flex min-w-0 items-start gap-[12px]">
        <span class="material-symbols-outlined mt-[2px] text-[24px] {{ $expiring > 0 ? 'text-rose-600' : 'text-orange-600' }}" aria-hidden="true">undo</span>
        <div class="min-w-0">
            <div class="text-[14px] font-semibold">มีใบงานของคุณถูกส่งกลับให้แก้ไข {{ $returned }} รายการ</div>
            <div class="mt-[2px] text-[12px] leading-relaxed">
                เจ้าหน้าที่ขอให้แก้ไขรายละเอียดก่อนดำเนินการต่อ แก้ไขแล้วกด "ส่งใบงานอีกครั้ง"
                @if ($expiresAfterDays > 0)
                    หากไม่ส่งอีกครั้งภายใน {{ $expiresAfterDays }} วัน ระบบจะยกเลิกอัตโนมัติ
                @endif
                @if ($expiring > 0)
                    <b>{{ $expiring }} รายการใกล้หมดอายุ</b> (ใกล้ที่สุดเหลือ {{ $soonestDaysLeft }} วัน)
                @endif
            </div>
        </div>
    </div>
    <div class="flex flex-wrap items-center gap-[8px]">
        <a href="{{ route('maintenance.requests.index', ['status' => 'returned']) }}"
            class="inline-flex items-center rounded-md bg-white px-[12px] py-[6px] text-[12px] font-semibold shadow-sm ring-1 ring-black/10 hover:bg-slate-50">
            ดูใบงานที่ต้องแก้ไข
        </a>
        @if ($expiring > 0)
            <a href="{{ route('maintenance.requests.index', ['status' => 'returned_expiring']) }}"
                class="inline-flex items-center rounded-md bg-white px-[12px] py-[6px] text-[12px] font-semibold text-rose-700 shadow-sm ring-1 ring-rose-200 hover:bg-rose-50">
                เฉพาะที่ใกล้หมดอายุ
            </a>
        @endif
    </div>
</div>

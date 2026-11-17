{{--
  The days a request that was sent back to its reporter has left before the nightly sweep cancels it ("เหลือ 12 วัน"), red when it is close
  (repair.return_warning_days or fewer). Nothing for any other request. <x-returned-countdown :req="$row" />
--}}
@props(['req'])
@php($left = $req->returnDaysLeft())
@if ($left !== null)
    <span data-returned-countdown
        class="inline-flex items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-semibold {{ $req->returnIsExpiring() ? 'bg-rose-50 text-rose-700' : 'bg-orange-50 text-orange-700' }}"
        title="ถ้าผู้แจ้งไม่แก้ไขและส่งใบงานอีกครั้ง ระบบจะยกเลิกอัตโนมัติ">
        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">timer</span>
        {{ $left === 0 ? 'ครบกำหนดวันนี้' : "เหลือ {$left} วัน" }}
    </span>
@endif

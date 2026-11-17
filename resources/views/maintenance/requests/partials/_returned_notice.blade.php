{{-- A request sent back to its reporter: what to fix, and what happens next. Shown to everybody who can open the job. --}}
@if (($status ?? $req->status) === \App\Models\MaintenanceRequest::STATUS_RETURNED)
    <div id="returned-notice" class="mb-4 rounded-md border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-900">
        <div class="flex items-center gap-2 font-semibold">
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">undo</span>
            ใบงานนี้ถูกส่งกลับให้แก้ไข
        </div>
        @if (! empty($returnReason))
            <div class="mt-2 whitespace-pre-line text-[13px]">{{ $returnReason }}</div>
        @endif
        @php($expiresOn = $req->returnExpiresOn())
        @if ($expiresOn)
            <div class="mt-2 flex flex-wrap items-center gap-2 text-[12px] text-orange-900">
                <x-returned-countdown :req="$req" />
                <span>ถ้าไม่ส่งใบงานอีกครั้ง ระบบจะ<b>ยกเลิกอัตโนมัติ</b>ในวันที่ {{ \App\Support\ThaiDate::long($expiresOn) }}</span>
            </div>
        @endif
        <div class="mt-2 text-[12px] text-orange-800">
            @if (! empty($canResubmit))
                แก้ไขใบงานนี้ (ปุ่ม "แก้ไข" หรือแนบไฟล์เพิ่ม) แล้วกด "ส่งใบงานอีกครั้ง" ใบงานยังเป็นเลขเดิมและประวัติยังอยู่
            @else
                รอผู้แจ้งแก้ไขแล้วส่งใบงานอีกครั้ง ระหว่างนี้ยังไม่นับเวลา SLA
            @endif
        </div>
    </div>
@endif

        {{-- Reject Modal --}}
        @if ($canReject)
            <div id="rejectModal"
                class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
                <div class="relative z-[10000] w-full max-w-xl rounded-2xl border {{ $line }} bg-white ">
                    <div class="flex items-center justify-between border-b {{ $line }} px-4 py-3">
                        <div class="text-sm font-semibold text-rose-600">ไม่รับเรื่อง</div>
                        <x-ui.button id="closeRejectModalBtn" variant="ghost" size="icon" icon="close" aria-label="ปิด" />
                    </div>
                    <form method="POST" action="{{ route('maintenance.requests.reject', $req->id) }}"
                        class="px-4 py-4 space-y-4" data-dirty-check="true">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-slate-700">ระบุเหตุผลที่ไม่รับเรื่อง <span
                                    class="text-rose-600">*</span></label>
                            <textarea name="reject_reason" rows="3" required style="min-height:unset;height:auto;"
                                class="mt-2 w-full rounded-md border {{ $line }} bg-white px-3 py-2 text-sm resize-none overflow-hidden
                               focus:border-rose-500 focus:ring-2 focus:ring-rose-100"
                                placeholder="เช่น ข้อมูลไม่ครบถ้วน, แจ้งซ้ำ, หรือไม่ใช่หน้าที่ของทีมเจ้าหน้าที่..."></textarea>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <x-ui.button id="cancelRejectModalBtn">ยกเลิก</x-ui.button>
                            <x-ui.button type="submit" variant="danger">ยืนยันการไม่รับเรื่อง</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Cancel Modal --}}
        @if ($canCancel)
            <div id="cancelModal"
                class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
                <div class="relative z-[10000] w-full max-w-xl rounded-2xl border {{ $line }} bg-white ">
                    <div class="flex items-center justify-between border-b {{ $line }} px-4 py-3">
                        <div class="text-sm font-semibold text-slate-600">ยกเลิกการซ่อมบำรุง</div>
                        <x-ui.button id="closeCancelModalBtn" variant="ghost" size="icon" icon="close" aria-label="ปิด" />
                    </div>
                    <form method="POST" action="{{ route('maintenance.requests.cancel', $req->id) }}"
                        class="px-4 py-4 space-y-4" data-dirty-check="true">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-slate-700">ระบุเหตุผลการยกเลิก <span
                                    class="text-rose-600">*</span></label>
                            <textarea name="cancel_reason" rows="3" required style="min-height:unset;height:auto;"
                                class="mt-2 w-full rounded-md border {{ $line }} bg-white px-3 py-2 text-sm resize-none overflow-hidden
                               focus:border-slate-500 focus:ring-2 focus:ring-slate-100"
                                placeholder="เช่น แจ้งผิดหน่วยงาน, ซ่อมเองได้แล้ว, หรือขอยกเลิกรายการนี้..."></textarea>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <x-ui.button id="cancelCancelModalBtn">ปิด</x-ui.button>
                            <x-ui.button type="submit" variant="neutral">ยืนยันการยกเลิกการซ่อมบำรุง</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Hold Modal --}}
        @if ($canHold)
            <div id="holdModal"
                class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
                <div class="relative z-[10000] w-full max-w-xl rounded-2xl border {{ $line }} bg-white ">
                    <div class="flex items-center justify-between border-b {{ $line }} px-4 py-3">
                        <div class="text-sm font-semibold text-slate-900">พักชั่วคราว</div>
                        <x-ui.button id="closeHoldModalBtn" variant="ghost" size="icon" icon="close" aria-label="ปิด" />
                    </div>
                    <form method="POST" action="{{ route('maintenance.requests.hold', $req->id) }}"
                        class="px-4 py-4 space-y-4" data-dirty-check="true">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-slate-700">ระบุเหตุผลในการพักชั่วคราว <span
                                    class="text-amber-600">*</span></label>
                            <textarea name="note" rows="3" required style="min-height:unset;height:auto;"
                                class="mt-2 w-full rounded-md border {{ $line }} bg-white px-3 py-2 text-sm resize-none overflow-hidden
                           focus:border-amber-500 focus:ring-2 focus:ring-amber-100"
                                placeholder="เช่น รออะไหล่, รอเบิกเครื่องมือ, หรือเหตุผลอื่น ๆ..."></textarea>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <x-ui.button id="cancelHoldModalBtn">ยกเลิก</x-ui.button>
                            <x-ui.button type="submit" variant="warning">ยืนยันการพักชั่วคราว</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Resolve Modal --}}
        @if ($canResolve)
            <div id="resolveModal"
                class="fixed inset-0 z-[9999] hidden items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4">
                <div class="relative z-[10000] w-full max-w-xl rounded-2xl border {{ $line }} bg-white ">
                    <div class="flex items-center justify-between border-b {{ $line }} px-4 py-3">
                        <div class="text-sm font-semibold text-slate-900">ซ่อมบำรุงเสร็จสิ้น</div>
                        <x-ui.button id="closeResolveModalBtn" variant="ghost" size="icon" icon="close" aria-label="ปิด" />
                    </div>
                    <form method="POST" action="{{ route('maintenance.requests.resolve', $req->id) }}"
                        class="px-4 py-4 space-y-4" data-dirty-check="true">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-slate-700">ระบุรายละเอียดการแก้ปัญหา <span
                                    class="text-emerald-600">*</span></label>
                            <textarea name="resolution_note" rows="3" required style="min-height:unset;height:auto;"
                                class="mt-2 w-full rounded-md border {{ $line }} bg-white px-3 py-2 text-sm resize-none overflow-hidden
                               focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100"
                                placeholder="เช่น เปลี่ยนอะไหล่, ซ่อมแผงวงจรสำเร็จ, ผ่านการสอบเทียบแล้ว..."></textarea>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <x-ui.button id="cancelResolveModalBtn">ยกเลิก</x-ui.button>
                            <x-ui.button type="submit" variant="primary">ยืนยันซ่อมบำรุงเสร็จสิ้น</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

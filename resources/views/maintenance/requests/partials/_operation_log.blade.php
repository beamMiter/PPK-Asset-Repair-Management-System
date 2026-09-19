<section class="flex flex-col">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 {{ $headCls }}">
        <div class="flex items-start gap-3 min-w-0">
            <div class="{{ $noCls }}">6</div>
            <div class="{{ $accentWrap }}">
                <span class="{{ $accentBar }}"></span>
                <div class="{{ $titleCls }}">รายงานการปฏิบัติงานและค่าใช้จ่าย <span
                        class="text-slate-500 text-[13px] font-normal">(บันทึกภายหลังได้)</span></div>
                <div class="{{ $subCls }}">สำหรับทีมเจ้าหน้าที่: ระบุวิธีคิดค่าใช้จ่าย, รพจ.
                    และรายละเอียดประกอบ</div>
            </div>
        </div>

        {{-- Header Spacer to match Assigned Team's Button height --}}
        <div class="hidden sm:block h-[38px] shrink-0"></div>
    </div>

    <div>

        @can('updateOperationLog', $req)
            <form method="post" action="{{ $opLogUrl }}" class="space-y-4" novalidate>
                @csrf
                <div class="max-w-[240px]">
                    <label for="operation_date"
                        class="block text-sm font-medium text-slate-700">รายการซ่อมสำหรับวันที่</label>
                    <input id="operation_date" type="date" name="operation_date"
                        value="{{ old('operation_date', optional($opLog?->operation_date)->format('Y-m-d')) }}"
                        class="ui-input" onclick="this.showPicker()">
                </div>
                <div>
                    <div class="block text-sm font-medium text-slate-700">วิธีการปฏิบัติ / การคิดค่าใช้จ่าย</div>
                    @php $method = old('operation_method', $opLog->operation_method ?? null); @endphp
                    <div class="mt-2 space-y-2 text-sm">
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="operation_method" value="requisition" @checked($method === 'requisition')
                                class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span>ตามใบเบิกครุภัณฑ์ / วัสดุ</span>
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="operation_method" value="service_fee" @checked($method === 'service_fee')
                                class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span>ค่าบริการ / ค่าแรงเจ้าหน้าที่</span>
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="operation_method" value="other" @checked($method === 'other')
                                class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span>อื่น ๆ</span>
                        </label>
                    </div>
                </div>
                <div>
                    <label for="property_code" class="block text-sm font-medium text-slate-700">ระบุรพจ.
                        (รหัสครุภัณฑ์)</label>
                    <input id="property_code" type="text" name="property_code"
                        value="{{ old('property_code', $opLog->property_code ?? ($assetCode ?? '')) }}"
                        class="ui-input" placeholder="เช่น 68101068718">
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="require_precheck" value="1" @checked(old('require_precheck', $opLog->require_precheck ?? false))
                        class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    ยืนยันว่าได้แจ้งผู้ใช้งาน / หน่วยงาน และขออนุญาตก่อนปฏิบัติงาน/ปิดเครื่อง
                </label>
                <div>
                    <div class="text-sm font-medium text-slate-700">ประเภทงานที่ปฏิบัติ</div>
                    <div class="mt-2 space-y-2 text-sm">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="issue_software" value="1" @checked(old('issue_software', $opLog->issue_software ?? false))
                                class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span>Software</span>
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="issue_hardware" value="1" @checked(old('issue_hardware', $opLog->issue_hardware ?? false))
                                class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            <span>Hardware</span>
                        </label>
                    </div>
                </div>
                <div>
                    <label for="remark" class="block text-sm font-medium text-slate-700">หมายเหตุ /
                        รายละเอียดประกอบ</label>
                    <textarea id="remark" name="remark" rows="4" style="{{ $textareaStyle }}" class="ui-textarea resize-none overflow-hidden"
                        placeholder="เช่น ตรวจเช็คแล้วพบว่า..., ผู้ใช้ทดสอบแล้วเรียบร้อย">{{ old('remark', $opLog->remark ?? '') }}</textarea>
                </div>
                <div class="flex justify-end">
                    <x-ui.button type="submit" variant="primary" icon="check" split>บันทึกรายงานการปฏิบัติงาน</x-ui.button>
                </div>
            </form>
        @else
            <div class="rounded-md border {{ $line }} bg-white px-4 py-3 text-sm text-slate-600 italic">
                <div class="flex items-center gap-2 text-rose-600 font-semibold mb-1">
                    <span class="material-symbols-outlined text-[18px]">lock</span>
                    สิทธิ์การบันทึกรายงานถูกระงับ
                </div>
                เนื่องจากใบงานอยู่ในสถานะ "{{ $req->status_label }}"
                ซึ่งไม่ได้รับอนุญาตให้แก้ไขรายงานการปฏิบัติงานในขณะนี้
            </div>
        @endcan

        @if ($opLog)
            <p class="mt-3 text-xs text-slate-500">
                บันทึกล่าสุดโดย {{ $opLog->user?->name ?? '-' }} ·
                {{ $opLog->updated_at?->format('Y-m-d H:i') ?? '-' }}
            </p>
        @endif
    </div>
</section>

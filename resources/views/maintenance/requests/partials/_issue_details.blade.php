<section class="flex flex-col">
    <div class="{{ $headCls }}">
        <div class="{{ $noCls }}">2</div>
        <div class="{{ $accentWrap }}">
            <span class="{{ $accentBar }}"></span>
            <div class="{{ $titleCls }}">รายละเอียดปัญหา</div>
            <div class="{{ $subCls }}">หัวข้อและอาการเสีย</div>
        </div>
    </div>

    <div class="space-y-4 text-sm">
        @php
            $types = collect($types ?? []);
        $select = "ts-basic w-full";
        @endphp

        <div>
            <div class="flex items-center justify-between">
                <div class="text-sm font-medium text-slate-700">ประเภทงาน (Report Type)</div>
                @cannot('setType', $req)
                    <span class="text-[12px] text-slate-400">* เฉพาะเจ้าหน้าที่เท่านั้น</span>
                @endcannot
            </div>

            @can('setType', $req)
                <style>
                    .type-update-form .ts-control {
                        min-height: 40px !important;
                        display: flex;
                        align-items: center;
                    }
                </style>
                <form method="POST" action="{{ route('maintenance.requests.type.update', $req->id) }}"
                    class="type-update-form mt-2 flex gap-2 items-stretch h-11">
                    @csrf
                    <div class="flex-1 min-w-0">
                        <select name="type_id" class="{{ $select }}" data-placeholder="— ยังไม่ระบุประเภท —">
                            <option value="">— ยังไม่ระบุประเภท —</option>
                            @foreach ($types as $t)
                                <option value="{{ $t->id }}" @selected((int) $req->type_id === (int) $t->id)>
                                    {{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-ui.button type="submit" title="บันทึกประเภท" variant="primary" icon="check" split>บันทึก</x-ui.button>
                </form>
            @else
                <div
                    class="mt-2 rounded-md border {{ $line }} bg-white px-3 py-2 text-sm text-slate-700 min-h-[44px] flex items-center">
                    {{ $req->type?->name ?? '— ยังไม่ระบุประเภท —' }}
                </div>
            @endcan
        </div>

        <div>
            <div class="text-sm font-medium text-slate-700">หัวข้อ <span class="text-rose-600">*</span>
            </div>
            <div
                class="mt-2 rounded-md border {{ $line }} bg-white px-3 py-2 font-semibold text-slate-900 min-h-[44px]">
                {{ $req->title ?: '-' }}
            </div>
        </div>

        <div>
            <div class="text-sm font-medium text-slate-700">รายละเอียด / อาการเสีย</div>
            <div
                class="mt-2 rounded-md border {{ $line }} bg-white px-3 py-2 text-slate-800 whitespace-pre-line min-h-[120px]">
                {{ $req->description ?: '—' }}
            </div>
        </div>
    </div>
</section>

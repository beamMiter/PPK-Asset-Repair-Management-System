<section class="flex flex-col">
    <div class="{{ $headCls }}">
        <div class="{{ $noCls }}">1</div>
        <div class="{{ $accentWrap }}">
            <span class="{{ $accentBar }}"></span>
            <div class="{{ $titleCls }}">ข้อมูลหลัก</div>
            <div class="{{ $subCls }}">ทรัพย์สิน / หน่วยงาน / สถานที่</div>
        </div>
    </div>

    <div class="space-y-4 text-sm">
        <div>
            <div class="text-sm font-medium text-slate-700">ทรัพย์สิน</div>
            <div class="mt-2 rounded-md border {{ $line }} bg-white px-3 py-2">
                <div class="font-semibold text-slate-900">{{ $assetName }}</div>
                @if ($assetCode)
                    <div class="mt-1 text-xs text-slate-500">รหัสครุภัณฑ์: {{ $assetCode }}</div>
                @endif
            </div>
        </div>

        <div>
            <div class="text-sm font-medium text-slate-700">หน่วยงาน</div>
            <div class="mt-2 rounded-md border {{ $line }} bg-white px-3 py-2">
                <div class="font-semibold text-slate-900">
                    {{ $req->department?->name_th ?? ($req->department?->name_en ?? '—') }}
                </div>
                @if ($req->department?->code)
                    <div class="mt-1 text-xs text-slate-500">รหัสหน่วยงาน: {{ $req->department->code }}
                    </div>
                @endif
            </div>
        </div>

        <div>
            <div class="text-sm font-medium text-slate-700">สถานที่ / ตำแหน่งงาน</div>
            <div class="mt-2 rounded-md border {{ $line }} bg-white px-3 py-2">
                <div class="font-semibold text-slate-900">{{ $location }}</div>
            </div>
        </div>
    </div>
</section>

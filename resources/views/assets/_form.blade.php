@php
    /** @var \App\Models\Asset|null $asset */
    $asset = $asset ?? new \App\Models\Asset();
    $readonly = $readonly ?? false;

    $line = 'border-slate-200';

    $input = "mt-2 w-full h-11 rounded-md border $line bg-white px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100 outline-none transition-all";

    $textarea = "mt-2 w-full min-h-[120px] rounded-md border $line bg-white px-3 py-2.5 text-sm text-slate-900
               placeholder:text-slate-400
               focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100 outline-none transition-all";

    $displayBox = "mt-2 w-full min-h-[44px] rounded-md border $line bg-white px-3 py-2.5 flex items-center text-sm text-slate-900 font-medium";
    $displayBoxTextarea = "mt-2 w-full min-h-[44px] rounded-md border $line bg-white px-3 py-2.5 text-sm text-slate-900 font-medium whitespace-pre-line";

    $headCls = 'flex items-start gap-3 pb-3 min-h-[56px]';
    $noCls =
        'w-8 h-8 shrink-0 rounded-full border border-emerald-600 bg-emerald-600 flex items-center justify-center text-sm font-bold text-white leading-none';
    $titleCls = 'text-base font-semibold text-slate-900 leading-tight';
    $subCls = 'text-sm text-slate-500 leading-snug';
    $accentWrap = 'min-w-0 relative pl-3 pt-[1px]';
    $accentBar = 'absolute left-0 top-[2px] w-[3px] h-9 rounded-full bg-emerald-600/90';

    $labelCls = 'block text-sm font-medium text-slate-700 mb-1';
    $hintCls = 'ml-1 text-[11px] text-slate-500 font-normal italic';

    $v = fn($key, $default = '') => old($key, data_get($asset, $key, $default));
@endphp

<div class="mx-auto max-w-screen-2xl px-3 sm:px-6 lg:px-8 pt-6">
    {{-- ════════════════════════════════════════════════════════
    FLAT 3-COLUMN RESPONSIVE LAYOUT (Matching Maintenance System)
    ════════════════════════════════════════════════════════ --}}
    <div class="space-y-12">

        {{-- MAIN DATA GRID: 1, 2, 3 --}}
        <div class="relative grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-x-12 gap-y-12">

            {{-- Vertical Dividers --}}
            <div class="hidden lg:block xl:hidden absolute inset-y-0 left-1/2 w-px bg-slate-200"></div>
            <div class="hidden xl:block absolute inset-y-0 left-1/3 w-px bg-slate-200"></div>
            <div class="hidden xl:block absolute inset-y-0 left-2/3 w-px bg-slate-200"></div>

            {{-- STEP 1: ข้อมูลหลัก --}}
            <section>
                <div class="{{ $headCls }}">
                    <div class="{{ $noCls }}">1</div>
                    <div class="{{ $accentWrap }}">
                        <span class="{{ $accentBar }}"></span>
                        <div class="{{ $titleCls }}">ข้อมูลหลัก</div>
                        <div class="{{ $subCls }}">ชื่อ รหัส และการเชื่อมต่อ HIS</div>
                    </div>
                </div>

                <div class="space-y-5 pt-1">
                    <div>
                        <label class="{{ $labelCls }}">ชื่อครุภัณฑ์ @if (!$readonly)
                                <span class="text-rose-600 font-bold">*</span>
                            @endif
                        </label>
                        @if ($readonly)
                            <div class="{{ $displayBox }} font-semibold">{{ $asset->name ?? '—' }}</div>
                        @else
                            <input type="text" id="name" name="name" value="{{ $v('name') }}"
                                class="{{ $input }}" placeholder="ระบุชื่อเรียกครุภัณฑ์" required>
                            @error('name')
                                <p class="mt-1 text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
                        @endif
                    </div>

                    <div>
                        <label class="{{ $labelCls }}">รหัสครุภัณฑ์ @if (!$readonly)
                                <span class="text-rose-600 font-bold">*</span>
                            @endif
                        </label>
                        @if ($readonly)
                            <div class="{{ $displayBox }} font-semibold">{{ $asset->asset_code ?? '—' }}</div>
                        @else
                            <input type="text" id="asset_code" name="asset_code" value="{{ $v('asset_code') }}"
                                class="{{ $input }}" placeholder="รหัสภายในโรงพยาบาล" required>
                            @error('asset_code')
                                <p class="mt-1 text-[11px] text-rose-600 font-medium">{{ $message }}</p>
                            @enderror
                        @endif
                    </div>

                    <div>
                        <label class="{{ $labelCls }}">ประเภทครุภัณฑ์</label>
                        @if ($readonly)
                            <div class="{{ $displayBox }}">{{ $asset->type ?? '—' }}</div>
                        @else
                            <input id="type" type="text" name="type" value="{{ $v('type') }}"
                                class="{{ $input }}" placeholder="เช่น การแพทย์, เทคโนโลยีสารสนเทศ">
                        @endif
                    </div>

                    <div>
                        <label class="{{ $labelCls }}">รหัสทะเบียน รพจ <span class="{{ $hintCls }}">(HIS
                                ID)</span></label>
                        @if ($readonly)
                            <div class="{{ $displayBox }} text-blue-700 font-semibold">
                                {{ $asset->his_asset_id ?? '—' }}</div>
                        @else
                            <div class="flex gap-2">
                                <input type="text" id="his_asset_id" name="his_asset_id"
                                    value="{{ $v('his_asset_id') }}" class="{{ $input }} flex-1"
                                    placeholder="RPJ-XXXXXX">
                                <button type="button" id="btn-fetch-his"
                                    class="mt-2 inline-flex items-center justify-center gap-1.5 h-11 px-4 rounded-md border border-sky-600 bg-sky-600 text-white text-sm font-medium hover:bg-sky-700 focus:ring-2 focus:ring-sky-200 transition-all whitespace-nowrap">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2">
                                        <path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                    ดึงข้อมูล HIS
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            {{-- STEP 2: รายละเอียดทางเทคนิค --}}
            <section>
                <div class="{{ $headCls }}">
                    <div class="{{ $noCls }}">2</div>
                    <div class="{{ $accentWrap }}">
                        <span class="{{ $accentBar }}"></span>
                        <div class="{{ $titleCls }}">รายละเอียดทางเทคนิค</div>
                        <div class="{{ $subCls }}">ยี่ห้อ รุ่น Serial และที่ตั้ง</div>
                    </div>
                </div>

                <div class="space-y-5 pt-1">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="{{ $labelCls }}">ยี่ห้อ</label>
                            @if ($readonly)
                                <div class="{{ $displayBox }}">{{ $asset->brand ?? '—' }}</div>
                            @else
                                <input id="brand" type="text" name="brand" value="{{ $v('brand') }}"
                                    class="{{ $input }}">
                            @endif
                        </div>
                        <div>
                            <label class="{{ $labelCls }}">รุ่น</label>
                            @if ($readonly)
                                <div class="{{ $displayBox }}">{{ $asset->model ?? '—' }}</div>
                            @else
                                <input id="model" type="text" name="model" value="{{ $v('model') }}"
                                    class="{{ $input }}">
                            @endif
                        </div>
                    </div>

                    <div>
                        <label class="{{ $labelCls }}">หมายเลขเครื่อง <span class="{{ $hintCls }}">(Serial
                                No.)</span></label>
                        @if ($readonly)
                            <div class="{{ $displayBox }} font-mono">{{ $asset->serial_number ?? '—' }}</div>
                        @else
                            <input id="serial_number" type="text" name="serial_number"
                                value="{{ $v('serial_number') }}" class="{{ $input }}">
                        @endif
                    </div>

                    <div>
                        <label class="{{ $labelCls }}">หมายเลขโทรศัพท์ภายใน <span
                                class="{{ $hintCls }}">(ป้ายเหลือง)</span></label>
                        @if ($readonly)
                            <div class="{{ $displayBox }}">{{ $asset->internal_phone ?? '—' }}</div>
                        @else
                            <input id="internal_phone" type="text" name="internal_phone"
                                value="{{ $v('internal_phone') }}" class="{{ $input }}"
                                placeholder="เช่น 02-xxx-xxxx">
                        @endif
                    </div>

                    <div>
                        <label class="{{ $labelCls }}">สถานที่ตั้ง / ห้อง / บริเวณ</label>
                        @if ($readonly)
                            <div class="{{ $displayBox }} text-emerald-700">{{ $asset->location ?? '—' }}</div>
                        @else
                            <input id="location" type="text" name="location" value="{{ $v('location') }}"
                                class="{{ $input }}" placeholder="ระบุตำแหน่งที่ตั้ง">
                        @endif
                    </div>
                </div>
            </section>

            {{-- STEP 3: ข้อมูลการจัดซื้อ --}}
            <section>
                <div class="{{ $headCls }}">
                    <div class="{{ $noCls }}">3</div>
                    <div class="{{ $accentWrap }}">
                        <span class="{{ $accentBar }}"></span>
                        <div class="{{ $titleCls }}">ข้อมูลการจัดซื้อ</div>
                        <div class="{{ $subCls }}">ผู้ขาย ราคา และวันจัดซื้อ</div>
                    </div>
                </div>

                <div class="space-y-5 pt-1">
                    <div>
                        <label class="{{ $labelCls }}">บริษัทผู้ขาย / ตัวแทนจำหน่าย</label>
                        @if ($readonly)
                            <div class="{{ $displayBox }}">{{ $asset->vendor_name ?? '—' }}</div>
                        @else
                            <input id="vendor_name" type="text" name="vendor_name"
                                value="{{ $v('vendor_name') }}" class="{{ $input }}">
                        @endif
                    </div>

                    <div>
                        <label class="{{ $labelCls }}">หมายเลขโทรศัพท์ผู้ขาย</label>
                        @if ($readonly)
                            <div class="{{ $displayBox }}">{{ $asset->vendor_phone ?? '—' }}</div>
                        @else
                            <input id="vendor_phone" type="text" name="vendor_phone"
                                value="{{ $v('vendor_phone') }}" class="{{ $input }}"
                                placeholder="081-xxx-xxxx">
                        @endif
                    </div>

                    <div>
                        <label class="{{ $labelCls }}">ราคาจัดซื้อจัดจ้าง <span
                                class="{{ $hintCls }}">(บาท)</span></label>
                        @if ($readonly)
                            <div class="{{ $displayBox }}">
                                {{ $asset->formatted_price ?? '—' }}
                            </div>
                        @else
                            <div class="relative mt-2">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <span class="text-slate-900 font-medium sm:text-sm">฿</span>
                                </div>
                                <input id="price" type="number" step="0.01" name="price"
                                    value="{{ $v('price') }}" class="{{ str_replace('mt-2 ', '', $input) }} !pl-6"
                                    placeholder="0.00">
                            </div>
                        @endif
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="{{ $labelCls }}">วันที่จัดซื้อจัดจ้าง</label>
                            @if ($readonly)
                                <div class="{{ $displayBox }}">
                                    {{ optional($asset->purchase_date)->format('d/m/Y') ?? '—' }}</div>
                            @else
                                <input id="purchase_date" type="date" name="purchase_date"
                                    value="{{ $v('purchase_date', optional($asset->purchase_date)->format('Y-m-d')) }}"
                                    class="{{ $input }}">
                            @endif
                        </div>
                        <div>
                            <label class="{{ $labelCls }}">วันเริ่มประกัน</label>
                            @if ($readonly)
                                <div class="{{ $displayBox }}">
                                    {{ optional($asset->warranty_start)->format('d/m/Y') ?? '—' }}</div>
                            @else
                                <input id="warranty_start" type="date" name="warranty_start"
                                    value="{{ $v('warranty_start', optional($asset->warranty_start)->format('Y-m-d')) }}"
                                    class="{{ $input }}">
                            @endif
                        </div>
                        <div>
                            <label class="{{ $labelCls }}">วันหมดประกัน</label>
                            @if ($readonly)
                                <div class="{{ $displayBox }} text-orange-700">
                                    {{ optional($asset->warranty_expire)->format('d/m/Y') ?? '—' }}</div>
                            @else
                                <input id="warranty_expire" type="date" name="warranty_expire"
                                    value="{{ $v('warranty_expire', optional($asset->warranty_expire)->format('Y-m-d')) }}"
                                    class="{{ $input }}">
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            {{-- Horizontal Divider --}}
            <div class="col-span-full border-t border-slate-200"></div>

            {{-- STEP 4: การจัดกลุ่ม & สถานะ --}}
            <section>
                <div class="{{ $headCls }}">
                    <div class="{{ $noCls }}">4</div>
                    <div class="{{ $accentWrap }}">
                        <span class="{{ $accentBar }}"></span>
                        <div class="{{ $titleCls }}">หมวดหมู่ และสถานะ</div>
                        <div class="{{ $subCls }}">จัดกลุ่ม / ระบุเจ้าของ / สถานะ</div>
                    </div>
                </div>

                <div class="space-y-5 pt-1">
                    <div>
                        <label class="{{ $labelCls }}">หมวดหมู่ครุภัณฑ์</label>
                        @if ($readonly)
                            <div class="{{ $displayBox }}">{{ optional($asset->categoryRef)->name ?? '—' }}</div>
                        @else
                            <select id="category_id" name="category_id" class="ts-basic mt-2 w-full">
                                <option value="">— เลือกหมวดหมู่ —</option>
                                @foreach ($categories ?? collect() as $cat)
                                    <option value="{{ $cat->id }}" @selected((string) $v('category_id') === (string) $cat->id)>
                                        {{ $cat->name ?? ($cat->name_th ?? '—') }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <div>
                        <label class="{{ $labelCls }}">หน่วยงานที่ครอบครอง</label>
                        @if ($readonly)
                            <div class="{{ $displayBox }}">
                                {{ optional($asset->department)->name_th ?? (optional($asset->department)->name_en ?? '—') }}
                            </div>
                        @else
                            <select id="department_id" name="department_id" class="ts-basic mt-2 w-full">
                                <option value="">— เลือกหน่วยงาน —</option>
                                @foreach ($departments ?? collect() as $d)
                                    <option value="{{ $d->id }}" @selected((string) $d->id === (string) $v('department_id'))>
                                        {{ ($d->code ? $d->code . ' - ' : '') . ($d->name_th ?: $d->name_en) }}
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <div>
                        <label class="{{ $labelCls }}">สถานะครุภัณฑ์</label>
                        @if ($readonly)
                            <div class="{{ $displayBox }}">{{ $asset->status_label ?? '—' }}</div>
                        @else
                            <select id="status" name="status" class="ts-basic mt-2 w-full">
                                @foreach (['active' => 'ใช้งานปกติ', 'in_repair' => 'อยู่ระหว่างซ่อมบำรุง', 'disposed' => 'ตัดจำหน่าย'] as $k => $lbl)
                                    <option value="{{ $k }}" @selected($v('status', 'active') === $k)>
                                        {{ $lbl }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <div>
                        <label class="{{ $labelCls }}">หมายเหตุเพิ่มเติม</label>
                        @if ($readonly)
                            <div class="{{ $displayBoxTextarea }} min-h-[100px]">{{ $asset->note ?? '—' }}</div>
                        @else
                            <textarea name="note" rows="5" class="{{ $textarea }} bg-slate-50/50"
                                placeholder="ระบุรายละเอียดเพิ่มเติม (ถ้ามี)">{{ $v('note') }}</textarea>
                        @endif
                    </div>
                </div>
            </section>

            {{-- STEP 5: รูปครุภัณฑ์ --}}
            <section>
                <div class="{{ $headCls }}">
                    <div class="{{ $noCls }}">5</div>
                    <div class="{{ $accentWrap }}">
                        <span class="{{ $accentBar }}"></span>
                        <div class="{{ $titleCls }}">ภาพประกอบครุภัณฑ์</div>
                        <div class="{{ $subCls }}">ภาพถ่ายหรือภาพประกอบหลัก</div>
                    </div>
                </div>

                <div class="space-y-5 pt-1">
                    @if (!$readonly)
                        <label class="{{ $labelCls }}">เลือกรูปภาพครุภัณฑ์</label>
                        <div class="flex items-center gap-2">
                            <input id="hero_image_any" type="file" name="hero_image" accept="image/*"
                                class="hidden">
                            <input id="hero_image_camera" type="file" name="hero_image" accept="image/*"
                                capture="environment" class="hidden">

                            <button type="button" id="hero_image_any_btn"
                                class="inline-flex items-center justify-center h-11 px-4 rounded-md border {{ $line }} bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition focus:ring-2 focus:ring-emerald-100">
                                <svg class="h-4 w-4 mr-2 text-slate-600" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2">
                                    <path
                                        d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48" />
                                </svg>
                                เลือกรูปภาพ
                            </button>

                            <button type="button" id="hero_image_camera_btn"
                                class="inline-flex items-center justify-center h-11 w-11 rounded-md border {{ $line }} bg-white hover:bg-slate-50 transition focus:ring-2 focus:ring-emerald-100">
                                <svg class="h-5 w-5 text-emerald-700" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2">
                                    <path
                                        d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
                                    <circle cx="12" cy="13" r="4" />
                                </svg>
                            </button>

                            <button type="button" id="hero_image_remove_btn"
                                class="inline-flex items-center justify-center h-11 px-3 rounded-md border {{ $line }} bg-white text-xs font-semibold text-rose-600 hover:bg-rose-50 transition hidden">
                                ล้างรูปภาพ
                            </button>
                        </div>

                        <div class="mt-3 p-3 rounded-md bg-amber-50 border border-amber-200">
                            <div class="flex gap-2">
                                <svg class="h-5 w-5 text-amber-600 shrink-0" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2">
                                    <path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="text-[11px] text-amber-800 space-y-1">
                                    <p class="font-bold">เงื่อนไขการอัปโหลดรูปภาพหลัก:</p>
                                    <ul class="list-disc ml-4 space-y-0.5">
                                        <li>รองรับไฟล์รูปภาพ (.jpg, .jpeg, .png) เท่านั้น</li>
                                        <li>จำกัดขนาดไฟล์ไม่เกิน 5 MB</li>
                                        <li>รูปภาพนี้จะแสดงเป็นภาพหลักในระบบและหน้ารายการ</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        {{-- Preview Hero Image --}}
                        <div id="hero_image_preview_box" class="mt-4 {{ !$asset->hero_image_url ? 'hidden' : '' }}">
                            <p class="text-[11px] text-slate-500 mb-2" id="hero_preview_label">
                                {{ $asset->hero_image_url ? 'รูปภาพปัจจุบัน:' : 'รูปภาพที่เลือก:' }}
                            </p>
                            <div class="relative inline-block">
                                <img id="hero_image_preview_img" src="{{ $asset->hero_image_url }}"
                                    class="h-32 w-auto rounded-lg border border-slate-200 object-cover ">
                                <button type="button" id="hero_image_remove_btn"
                                    class="absolute -top-2 -right-2 h-6 w-6 rounded-full bg-rose-600 text-white flex items-center justify-center hover:bg-rose-700 transition">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="3">
                                        <path d="M18 6L6 18M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @else
                        @php
                            $heroUrl = $asset->hero_image_url;
                            $fallbackUrl = asset('images/equipment/default.svg');
                            $heroFinal = $heroUrl ?: $fallbackUrl;
                        @endphp
                        <div
                            class="mt-2 group relative aspect-video w-full overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                            <img src="{{ $heroFinal }}"
                                class="h-full w-full object-contain p-4 transition-transform duration-300 group-hover:scale-105"
                                alt="Asset Image">
                            @if (!$heroUrl)
                                <div
                                    class="absolute inset-0 flex items-center justify-center bg-slate-900/5 backdrop-blur-[1px]">
                                    <span
                                        class="text-xs font-semibold text-slate-400 bg-white/80 px-3 py-1 rounded-full border border-slate-200">ภาพจำลองระบบ</span>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </section>

            {{-- STEP 6: ไฟล์แนบ --}}
            <section>
                <div class="{{ $headCls }}">
                    <div class="{{ $noCls }}">6</div>
                    <div class="{{ $accentWrap }}">
                        <span class="{{ $accentBar }}"></span>
                        <div class="{{ $titleCls }}">ไฟล์แนบ</div>
                        <div class="{{ $subCls }}">เอกสาร คู่มือ หรือรูปภาพเพิ่มเติม</div>
                    </div>
                </div>

                <div class="space-y-5 pt-1">
                    @if (!$readonly)
                        <label class="{{ $labelCls }}">เลือกไฟล์เอกสารเพิ่มเติม</label>
                        <div class="flex items-center gap-2">
                            <input id="att_files_submit" type="file" name="files[]" multiple class="hidden">
                            <input id="att_files_any" type="file" multiple accept="image/*,application/pdf"
                                class="hidden">
                            <input id="att_files_camera" type="file" accept="image/*" capture="environment"
                                class="hidden">

                            <button type="button" id="att_files_any_btn"
                                class="inline-flex items-center justify-center h-11 px-4 rounded-md border {{ $line }} bg-white text-sm font-medium text-slate-700 hover:bg-slate-50 transition focus:ring-2 focus:ring-emerald-100">
                                <svg class="h-4 w-4 mr-2 text-slate-600" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2">
                                    <path
                                        d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48" />
                                </svg>
                                เลือกไฟล์แนบ
                            </button>

                            <button type="button" id="att_files_camera_btn"
                                class="inline-flex items-center justify-center h-11 w-11 rounded-md border {{ $line }} bg-white hover:bg-slate-50 transition focus:ring-2 focus:ring-emerald-100">
                                <svg class="h-5 w-5 text-emerald-700" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2">
                                    <path
                                        d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
                                    <circle cx="12" cy="13" r="4" />
                                </svg>
                            </button>
                        </div>

                        <div class="mt-3 p-3 rounded-md bg-amber-50 border border-amber-200">
                            <div class="flex gap-2">
                                <svg class="h-5 w-5 text-amber-600 shrink-0" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2">
                                    <path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="text-[11px] text-amber-800 space-y-1">
                                    <p class="font-bold">เงื่อนไขการแนบไฟล์เพิ่มเติม:</p>
                                    <ul class="list-disc ml-4 space-y-0.5">
                                        <li>รองรับไฟล์รูปภาพ และ PDF (.pdf)</li>
                                        <li>จำกัดขนาดไฟล์ไม่เกิน 10 MB ต่อไฟล์</li>
                                        <li>สามารถแนบได้พร้อมกันหลายไฟล์</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        {{-- Preview Attachments --}}
                        <div id="att_files_preview" class="mt-3 hidden">
                            <div class="text-[11px] font-medium text-slate-600 mb-2">ไฟล์ที่เลือก:</div>
                            <div id="att_files_list" class="space-y-2"></div>
                        </div>

                        @php $attached = $asset->attachments()->where('order_column', '!=', \App\Models\Attachment::HERO_ORDER)->get() ?? collect(); @endphp
                        @if ($attached->count())
                            <div class="mt-6 p-4 rounded-xl bg-slate-50 border border-slate-200">
                                <p class="text-[12px] font-semibold text-slate-700 mb-3 flex items-center gap-2">
                                    <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none"
                                        stroke="currentColor" stroke-width="2">
                                        <path d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                    </svg>
                                    ไฟล์ที่มีอยู่แล้ว ({{ $attached->count() }})
                                </p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    @foreach ($attached as $att)
                                        <div
                                            class="flex items-center justify-between gap-3 p-2.5 rounded-lg border border-white bg-white transition hover: group">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <div
                                                    class="w-8 h-8 shrink-0 rounded bg-slate-50 flex items-center justify-center text-slate-400">
                                                    @if (str_starts_with($att->mime_type ?? '', 'image/'))
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                                            stroke="currentColor">
                                                            <path
                                                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    @else
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                                            stroke="currentColor">
                                                            <path
                                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                        </svg>
                                                    @endif
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="text-[12px] font-medium text-slate-700 truncate"
                                                        title="{{ $att->original_name }}">{{ $att->original_name }}
                                                    </div>
                                                    <div class="text-[10px] text-slate-400">
                                                        {{ number_format(($att->file_size ?? 0) / 1024, 1) }} KB</div>
                                                </div>
                                            </div>
                                            <label class="flex items-center gap-1.5 cursor-pointer">
                                                <input type="checkbox" name="remove_attachments[]"
                                                    value="{{ $att->id }}"
                                                    class="w-4 h-4 rounded border-slate-300 text-rose-600 focus:ring-rose-100">
                                                <span
                                                    class="text-[11px] font-medium text-rose-600 group-hover:text-rose-700">ลบ</span>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @else
                        @php $attached = $asset->attachments()->where('order_column', '!=', \App\Models\Attachment::HERO_ORDER)->get() ?? collect(); @endphp
                        @if ($attached->count())
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2">
                                @foreach ($attached as $att)
                                    <a href="{{ Storage::url($att->file_path) }}" target="_blank"
                                        class="flex items-center gap-3 p-3 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 transition-colors group">
                                        <div
                                            class="w-10 h-10 shrink-0 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-500 group-hover:bg-indigo-100 group-hover:text-indigo-600 transition-colors">
                                            @if (str_starts_with($att->mime_type ?? '', 'image/'))
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            @else
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="text-[13px] font-medium text-slate-900 truncate"
                                                title="{{ $att->original_name }}">{{ $att->original_name }}</div>
                                            <div class="text-[11px] text-slate-500 mt-0.5">
                                                {{ number_format(($att->file_size ?? 0) / 1024, 2) }} KB</div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div
                                class="mt-2 min-h-[140px] rounded-xl border border-dashed border-slate-300 bg-slate-50/50 flex flex-col items-center justify-center p-6 text-center">
                                <span class="text-[13px] text-slate-400 font-medium italic">ยังไม่มีไฟล์แนบ</span>
                            </div>
                        @endif
                    @endif
                </div>
            </section>
        </div>
    </div>
</div>
@if (!$readonly)
    <script>
        (function() {
            // --- HIS Fetch Logic (Same as before) ---
            const btn = document.getElementById('btn-fetch-his');
            const hisInput = document.getElementById('his_asset_id');
            const statusEl = document.getElementById('his-fetch-status');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

            if (btn && hisInput) {
                function showToast(msg, type = 'info') {
                    window.dispatchEvent(new CustomEvent('app:toast', {
                        detail: {
                            type: type,
                            message: msg
                        }
                    }));
                }

                const fieldMap = {
                    name: 'name',
                    asset_code: 'asset_code',
                    brand: 'brand',
                    model: 'model',
                    serial_number: 'serial_number',
                    vendor_name: 'vendor_name',
                    vendor_phone: 'vendor_phone',
                    internal_phone: 'internal_phone',
                    price: 'price',
                    purchase_date: 'purchase_date',
                    warranty_start: 'warranty_start',
                    warranty_expire: 'warranty_expire',
                    type: 'type',
                    category_id: 'category_id',
                    department_id: 'department_id',
                    status: 'status',
                    note: 'note'
                };

                btn.addEventListener('click', async function() {
                    const hisId = hisInput.value.trim();
                    if (!hisId) {
                        showToast('กรุณาระบุเลข รพจ ก่อน', 'warning');
                        return;
                    }
                    btn.disabled = true;
                    const originalText = btn.innerHTML;
                    btn.innerHTML =
                        '<svg class="animate-spin h-4 w-4 mr-2" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> กำลังดึง...';

                    try {
                        const res = await fetch(`/assets/fetch-his?his_id=${encodeURIComponent(hisId)}`, {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            }
                        });
                        const json = await res.json();
                        if (!res.ok || json.status !== 'found') {
                            showToast('ไม่พบข้อมูล HIS สำหรับเลขนี้', 'warning');
                            return;
                        }
                        Object.entries(fieldMap).forEach(([key, id]) => {
                            if (json.data[key] == null) return;
                            const el = document.getElementById(id) || document.getElementsByName(
                                id)[0];
                            if (el) {
                                if (el.tomselect) {
                                    el.tomselect.setValue(json.data[key]);
                                } else {
                                    el.value = json.data[key];
                                }
                            }
                        });
                        showToast('ดึงข้อมูล HIS สำเร็จ', 'success');
                    } catch {
                        showToast('เกิดข้อผิดพลาดในการดึงข้อมูล', 'error');
                    } finally {
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }
                });
            }

            // --- Standardized Upload Logic (Helper Function) ---
            function setupUploader({
                anyId,
                camId,
                anyBtnId,
                camBtnId,
                previewId,
                listId,
                removeBtnId,
                previewImgId,
                labelId,
                isSingle = false,
                maxMB = 10
            }) {
                const anyInput = document.getElementById(anyId);
                const camInput = document.getElementById(camId);
                const anyBtn = document.getElementById(anyBtnId);
                const camBtn = document.getElementById(camBtnId);
                const preview = document.getElementById(previewId);
                const list = document.getElementById(listId);
                const removeBtn = document.getElementById(removeBtnId);
                const previewImg = document.getElementById(previewImgId);
                const label = document.getElementById(labelId);

                if (!anyInput || !camInput) return;

                let filesBag = [];
                const maxBytes = maxMB * 1024 * 1024;

                const showToast = (msg, type = 'warning') => {
                    window.dispatchEvent(new CustomEvent('app:toast', {
                        detail: {
                            type,
                            message: msg
                        }
                    }));
                };

                const sync = () => {
                    const dt = new DataTransfer();
                    filesBag.forEach(f => dt.items.add(f));

                    // If it's the attachments section, submit via the dedicated input
                    const submitInput = document.getElementById(anyId.replace('_any', '_submit'));
                    if (submitInput) {
                        submitInput.files = dt.files;
                    } else {
                        anyInput.files = dt.files;
                    }
                };

                const renderSingle = () => {
                    if (!filesBag.length) {
                        return;
                    }
                    const f = filesBag[0];
                    if (previewImg) previewImg.src = URL.createObjectURL(f);
                    if (label) label.textContent = 'รูปภาพที่เลือกใหม่:';
                    if (preview) preview.classList.remove('hidden');
                };

                const renderMultiple = () => {
                    if (!list) return;
                    list.innerHTML = '';
                    if (!filesBag.length) {
                        preview.classList.add('hidden');
                        return;
                    }
                    preview.classList.remove('hidden');
                    filesBag.forEach((f, idx) => {
                        const item = document.createElement('div');
                        item.className =
                            'p-2.5 rounded-lg border border-slate-200 bg-white flex justify-between items-center ';
                        item.innerHTML =
                            `<div class="flex items-center gap-2 min-w-0 transition-all">
                            <span class="truncate text-[12px] font-medium text-slate-700">${f.name}</span>
                            <span class="text-[10px] text-slate-400">${(f.size/1024).toFixed(1)}KB</span>
                        </div>
                        <button type="button" class="text-rose-600 hover:text-rose-700 text-[11px] font-semibold">ลบ</button>`;
                        item.querySelector('button').onclick = () => {
                            filesBag.splice(idx, 1);
                            sync();
                            renderMultiple();
                        };
                        list.appendChild(item);
                    });
                };

                const handleFiles = (files) => {
                    const incoming = Array.from(files || []);
                    if (!incoming.length) return;

                    // Size Check
                    const oversized = incoming.filter(f => f.size > maxBytes);
                    if (oversized.length > 0) {
                        showToast(`ไฟล์มีขนาดใหญ่เกิน ${maxMB}MB (${oversized[0].name})`);
                        return;
                    }

                    if (isSingle) {
                        filesBag = [incoming[0]];
                        renderSingle();
                    } else {
                        const map = new Map(filesBag.map(f => [f.name + f.size, f]));
                        incoming.forEach(f => map.set(f.name + f.size, f));
                        filesBag = Array.from(map.values());
                        renderMultiple();
                    }
                    sync();
                };

                if (anyBtn) anyBtn.onclick = () => anyInput.click();
                if (camBtn) camBtn.onclick = () => camInput.click();
                if (removeBtn) removeBtn.onclick = () => {
                    filesBag = [];
                    sync();
                    if (preview) preview.classList.add('hidden');
                    if (previewImg && previewImg.dataset.original) {
                        previewImg.src = previewImg.dataset.original;
                        if (label) label.textContent = 'รูปภาพปัจจุบัน:';
                        preview.classList.remove('hidden');
                    }
                };

                anyInput.onchange = () => {
                    handleFiles(anyInput.files);
                    anyInput.value = '';
                };
                camInput.onchange = () => {
                    handleFiles(camInput.files);
                    camInput.value = '';
                };

                if (isSingle && previewImg) {
                    previewImg.dataset.original = previewImg.src;
                }
            }

            // Step 5: Hero Image (Single, 5MB)
            setupUploader({
                anyId: 'hero_image_any',
                camId: 'hero_image_camera',
                anyBtnId: 'hero_image_any_btn',
                camBtnId: 'hero_image_camera_btn',
                previewId: 'hero_image_preview_box',
                removeBtnId: 'hero_image_remove_btn',
                previewImgId: 'hero_image_preview_img',
                labelId: 'hero_preview_label',
                isSingle: true,
                maxMB: 5
            });

            // Step 6: Attachments (Multiple, 10MB)
            setupUploader({
                anyId: 'att_files_any',
                camId: 'att_files_camera',
                anyBtnId: 'att_files_any_btn',
                camBtnId: 'att_files_camera_btn',
                previewId: 'att_files_preview',
                listId: 'att_files_list',
                isSingle: false,
                maxMB: 10
            });
        })();
    </script>
@endif

@php
    /** @var \App\Models\MaintenanceRequest|null $req */
    $req = $req ?? null;

    $assets = is_iterable($assets ?? null) ? collect($assets) : collect();
    $depts = is_iterable($depts ?? null) ? collect($depts) : collect();
    $types = is_iterable($types ?? null) ? collect($types) : collect();

    $user = auth()->user();
    $isEdit = (bool) optional($req)->exists;

    $line = 'border-slate-200';




    $v = function ($key, $default = '') use ($req) {
        $old = old($key);
        if (!is_null($old)) {
            return $old;
        }

        $modelVal = data_get($req, $key, null);
        if (!is_null($modelVal)) {
            return $modelVal;
        }

        $queryVal = request()->query($key, null);
        if (!is_null($queryVal)) {
            return $queryVal;
        }

        return $default;
    };
@endphp

<div class="mx-auto max-w-screen-2xl px-3 sm:px-6 lg:px-8">
    <div class="space-y-10">

        <div class="relative grid grid-cols-1 lg:grid-cols-2 gap-10 items-start">
            <div class="hidden lg:block absolute inset-y-0 left-1/2 w-px bg-slate-200"></div>

            <section>
                <x-ui.section-head no="1" title="ข้อมูลหลัก" subtitle="ทรัพย์สิน / หน่วยงาน / สถานที่" />

                <label class="block text-sm font-medium text-slate-700">
                    ทรัพย์สิน <span class="text-rose-500 font-bold">*</span>
                    <span
                        class="ml-1 text-[11px] font-normal text-slate-500 italic">(ไม่จำเป็นต้องระบุหากไม่ได้ซ่อมครุภัณฑ์ในระบบ)</span>
                </label>
                <select name="asset_id" class="ts-basic mt-2 w-full" data-placeholder="— เลือกทรัพย์สิน —">
                    <option value="">— ไม่ระบุ —</option>
                    @foreach ($assets as $a)
                        @php
                            $label = trim(($a->asset_code ? $a->asset_code.' - ' : '').($a->name ?? ''));
                            // the HIS registry number (รหัสทะเบียน รพจ) is part of the option's text — that is what the picker
                            // searches — unless it is the asset code itself (an asset registered from HIS takes it as its code)
                            $showHis = $a->his_asset_id && (string) $a->his_asset_id !== (string) $a->asset_code;
                            if ($showHis) {
                                $label .= ' (รพจ. '.$a->his_asset_id.')';
                            }
                        @endphp
                        {{-- data-his: the picker draws that part of the text in the table's colour (layout/widgets.js) --}}
                        <option value="{{ $a->id }}" @if ($showHis) data-his="{{ $a->his_asset_id }}" @endif
                            @selected((string) $v('asset_id') === (string) $a->id)>
                            {{ $label ?: '—' }}
                        </option>
                    @endforeach
                </select>

                <label class="block text-sm font-medium text-slate-700 mt-4">หน่วยงาน</label>
                <select name="department_id" class="ts-basic mt-2 w-full" data-placeholder="— เลือกหน่วยงาน —">
                    <option value="">— ไม่ระบุ —</option>
                    @foreach ($depts as $d)
                        @php
                            $deptName = $d->name_th ?: $d->name_en ?? '';
                            $label = trim(($d->code ? $d->code . ' - ' : '') . $deptName);
                        @endphp
                        <option value="{{ $d->id }}" @selected((string) $v('department_id') === (string) $d->id)>
                            {{ $label ?: '—' }}
                        </option>
                    @endforeach
                </select>

                <label class="block text-sm font-medium text-slate-700 mt-4">สถานที่ / ตำแหน่งงาน</label>
                <input type="text" name="location_text" value="{{ $v('location_text') }}" autocomplete="off"
                    class="ui-input">
            </section>

            <section>
                <x-ui.section-head no="2" title="รายละเอียดปัญหา" subtitle="หัวข้อและอาการเสีย" />

                <label class="block text-sm font-medium text-slate-700">
                    ประเภทงาน (Report Type)
                </label>
                <select name="type_id" class="ts-basic mt-2 w-full" data-placeholder="— เลือกประเภทงาน —">
                    <option value="">— ไม่ระบุ —</option>
                    @foreach ($types as $t)
                        <option value="{{ $t->id }}" @selected((string) $v('type_id') === (string) $t->id)>
                            {{ $t->name }}
                        </option>
                    @endforeach
                </select>

                <label class="block text-sm font-medium text-slate-700 mt-4">
                    หัวข้อ <span class="text-rose-600">*</span>
                </label>
                <input type="text" name="title" value="{{ $v('title') }}" autocomplete="off"
                    class="ui-input" required>

                <label class="block text-sm font-medium text-slate-700 mt-4">รายละเอียด / อาการเสีย</label>
                <textarea name="description" rows="6" class="ui-textarea">{{ $v('description') }}</textarea>
            </section>
        </div>

        <div class="border-t {{ $line }}"></div>

        <div class="relative grid grid-cols-1 lg:grid-cols-2 gap-10 items-start">
            <div class="hidden lg:block absolute inset-y-0 left-1/2 w-px bg-slate-200"></div>

            <section>
                <x-ui.section-head no="3" title="ข้อมูลผู้แจ้ง" subtitle="ระบุข้อมูลและรายละเอียดการติดต่อของผู้แจ้ง" />

                <div class="grid grid-cols-1 gap-4">
                    @if (!$isEdit && $user)
                        <div>
                            <label class="block text-sm font-medium text-slate-700">ผู้แจ้ง</label>
                            <div
                                class="mt-2 h-11 rounded-md border {{ $line }} bg-slate-50 px-3 flex items-center text-sm text-slate-700">
                                {{ $user->name }}
                            </div>
                            <input type="hidden" name="reporter_name" value="{{ $user->name }}">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700">เบอร์โทร (ถ้ามี)</label>
                            <input type="text" name="reporter_phone" value="{{ $v('reporter_phone') }}"
                                class="ui-input">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700">อีเมล (ถ้ามี)</label>
                            <input type="email" name="reporter_email"
                                value="{{ $v('reporter_email', $user->email) }}" class="ui-input">
                        </div>
                    @else
                        <div>
                            <label class="block text-sm font-medium text-slate-700">ชื่อผู้แจ้ง</label>
                            <input type="text" name="reporter_name" value="{{ $v('reporter_name') }}"
                                class="ui-input">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700">เบอร์โทร</label>
                            <input type="text" name="reporter_phone" value="{{ $v('reporter_phone') }}"
                                class="ui-input">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700">อีเมล</label>
                            <input type="email" name="reporter_email" value="{{ $v('reporter_email') }}"
                                class="ui-input">
                        </div>
                    @endif
                </div>

                @php
                    $isTeam = $user && ($user->isAdmin() || $user->isSupervisor() || $user->isTechnician());
                @endphp
            </section>

            <section>
                <x-ui.section-head no="4" title="ไฟล์แนบ" subtitle="แนบไฟล์ / ถ่ายรูปจากมือถือ" />

                @php $attachments = is_iterable($attachments ?? null) ? $attachments : []; @endphp

                @if ($isEdit && !empty($attachments) && count($attachments))
                    <div class="mb-4 p-4 rounded-xl bg-slate-50 border border-slate-200">
                        <p class="text-[13px] font-semibold text-slate-700 mb-4 flex items-center gap-2">
                            <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2">
                                <path d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            ไฟล์ที่มีอยู่แล้ว ({{ count($attachments) }})
                        </p>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($attachments as $att)
                                @php
                                    $file = $att->file;
                                    $name = $att->original_name ?? ($file?->path ?? 'file');
                                    $isPrivate = (bool) ($att->is_private ?? false);
                                    $mime = $file?->mime ?? '';
                                    $isImg = $mime && str_starts_with($mime, 'image/');
                                    $publicUrl = null;
                                    if ($file && ($file->disk ?? null) && ($file->path ?? null)) {
                                        try {
                                            $disk = \Illuminate\Support\Facades\Storage::disk($file->disk);
                                            $publicUrl = $disk->url($file->path);
                                        } catch (\Throwable $e) {
                                            $publicUrl = null;
                                        }
                                    }
                                    $canOpenPrivate =
                                        auth()->check() &&
                                        auth()
                                            ->user()
                                            ->can('update', $req ?? null);
                                    $canOpen = !$isPrivate || $canOpenPrivate;
                                    $openUrl = $publicUrl;
                                    try {
                                        $openUrl = route('attachments.show', $att);
                                    } catch (\Throwable $e) {
                                        $openUrl = $publicUrl;
                                    }
                                @endphp
                                <figure
                                    class="overflow-hidden rounded-md border {{ $line }} bg-white text-xs flex flex-col hover: transition">
                                    @if ($canOpen && $openUrl)
                                        <a href="{{ $openUrl }}" target="_blank" rel="noopener"
                                            class="block overflow-hidden group border-b border-slate-100">
                                            @if ($isImg)
                                                <img src="{{ $openUrl }}" alt="{{ $att->alt_text ?? $name }}"
                                                    class="h-24 w-full object-cover transition-transform duration-300 group-hover:scale-105">
                                            @else
                                                <div
                                                    class="grid h-24 w-full place-items-center bg-slate-50 text-slate-500 text-[15px] font-bold group-hover:bg-slate-100 transition-colors">
                                                    {{ strtoupper(pathinfo($name, PATHINFO_EXTENSION) ?: 'FILE') }}
                                                </div>
                                            @endif
                                        </a>
                                    @else
                                        <div
                                            class="grid h-24 w-full place-items-center bg-slate-50 text-slate-400 text-[13px] font-bold border-b border-slate-100">
                                            LOCKED
                                        </div>
                                    @endif
                                    <figcaption class="px-3 py-2 space-y-1.5 flex-1 flex flex-col justify-between">
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-1.5">
                                                <span
                                                    class="inline-flex items-center rounded-md px-1 py-0.5 text-[8.5px] font-bold uppercase tracking-wide {{ $isPrivate ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600' }}">
                                                    {{ $isPrivate ? 'Private' : 'Public' }}
                                                </span>
                                            </div>
                                            <div class="text-[11px] font-medium text-slate-700 line-clamp-1"
                                                title="{{ $name }}">{{ $name }}</div>
                                        </div>
                                        <div class="pt-2 mt-2 border-t border-slate-50 flex items-center justify-end">
                                            <label
                                                class="inline-flex items-center gap-1.5 cursor-pointer bg-rose-50 px-2 py-1 rounded-md border border-rose-100 hover:bg-rose-100 transition">
                                                <input type="checkbox" name="remove_attachments[]"
                                                    value="{{ $att->id }}"
                                                    class="w-3.5 h-3.5 rounded border-slate-300 text-rose-600 focus:ring-rose-200">
                                                <span class="text-[10px] font-semibold text-rose-700">เลือกลบ</span>
                                            </label>
                                        </div>
                                    </figcaption>
                                </figure>
                            @endforeach
                        </div>
                    </div>
                @endif

                <input id="mr_files_submit" type="file" name="files[]" multiple class="hidden">
                <input id="mr_files_any" type="file" multiple accept="image/*,application/pdf" class="hidden">
                <input id="mr_files_camera" type="file" accept="image/*" capture="environment" class="hidden">

                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        {{-- ปุ่มแนบไฟล์ (Icon Only) --}}
                        <x-ui.button id="mr_files_any_btn" size="square" icon="attach_file"
                            aria-label="แนบไฟล์เอกสาร" title="แนบไฟล์เอกสาร" />

                        {{-- ปุ่มกล้อง (Icon Only) --}}
                        <x-ui.button id="mr_files_camera_btn" size="square" icon="photo_camera"
                            aria-label="ถ่ายรูป" title="ถ่ายรูปจากกล้อง" />

                        <div class="text-[11px] sm:text-[12px] text-slate-500 font-medium leading-tight">
                            รองรับรูปภาพ / PDF <br class="sm:hidden"> (แนบไฟล์ หรือ ถ่ายรูป)
                        </div>
                    </div>
                </div>

                {{-- Preview list --}}
                <div id="mr_files_preview" class="mt-3 hidden">
                    <div class="text-xs font-medium text-slate-600">ไฟล์ที่เลือก</div>
                    <div id="mr_files_list"
                        class="mt-2 divide-y divide-slate-200 rounded-md border {{ $line }} bg-white"></div>
                </div>

                <div class="mt-3 p-3 rounded-md bg-amber-50 border border-amber-200">
                    <div class="flex gap-2">
                        <svg class="h-5 w-5 text-amber-600 shrink-0" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2">
                            <path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                        <div class="text-xs text-amber-800 space-y-1">
                            <p class="font-bold">เงื่อนไขการแนบไฟล์และแก้ไขข้อมูล:</p>
                            <ul class="list-disc ml-4 space-y-0.5">
                                <li>จำกัดจำนวนไฟล์แนบสูงสุด 3 ไฟล์ต่อใบงาน</li>
                                <li>ผู้แจ้งสามารถแก้ไขข้อมูลและไฟล์แนบได้เฉพาะก่อนเจ้าหน้าที่รับทราบงาน</li>
                                <li>เมื่อใบงานถูกปิดหรือเจ้าหน้าที่เริ่มดำเนินการแล้ว จะไม่สามารถแก้ไขข้อมูลพื้นฐานได้
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>


                <script>
                    (function() {
                        const anyInput = document.getElementById('mr_files_any');
                        const camInput = document.getElementById('mr_files_camera');
                        const anyBtn = document.getElementById('mr_files_any_btn');
                        const camBtn = document.getElementById('mr_files_camera_btn');
                        const preview = document.getElementById('mr_files_preview');
                        const list = document.getElementById('mr_files_list');

                        if (!anyInput || !camInput || !anyBtn || !camBtn || !preview || !list) return;

                        let filesBag = [];

                        const keyOf = (f) => `${f.name}|${f.size}|${f.type}|${f.lastModified || 0}`;

                        const syncToInputs = () => {
                            // สร้าง FileList ใหม่ให้ mr_files_submit เพื่อ submit ทีเดียว (รวมทั้งจากกล้องด้วย)
                            const dt = new DataTransfer();
                            filesBag.forEach(f => dt.items.add(f));
                            document.getElementById('mr_files_submit').files = dt.files;
                        };

                        const render = () => {
                            list.innerHTML = '';
                            if (!filesBag.length) {
                                preview.classList.add('hidden');
                                return;
                            }
                            preview.classList.remove('hidden');

                            filesBag.forEach((f, idx) => {
                                const card = document.createElement('div');
                                card.className =
                                    'p-2.5 rounded-md border border-slate-200 bg-white flex justify-between items-center ';
                                card.innerHTML = `<div class="flex items-center gap-2 min-w-0 transition-all">
                    <span class="truncate text-[12px] font-medium text-slate-700">${f.name}</span>
                    <span class="text-[10px] text-slate-400">${(f.size/1024).toFixed(1)}KB</span>
                </div>
                <button type="button" class="text-rose-600 hover:text-rose-700 text-[11px] font-semibold">ลบ</button>`;

                                card.querySelector('button').addEventListener('click', () => {
                                    filesBag.splice(idx, 1);
                                    syncToInputs();
                                    render();
                                });

                                list.appendChild(card);
                            });
                        };

                        const addFiles = (files) => {
                            const arr = Array.from(files || []);
                            if (!arr.length) return;

                            const map = new Map(filesBag.map(f => [keyOf(f), f]));
                            arr.forEach(f => map.set(keyOf(f), f));

                            const existingCount =
                                {{ isset($attachments) && is_iterable($attachments) ? count($attachments) : 0 }};
                            const maxAllowed = Math.max(0, 3 - existingCount);

                            if (map.size > maxAllowed) {
                                window.dispatchEvent(new CustomEvent('app:toast', {
                                    detail: {
                                        type: 'warning',
                                        message: 'สามารถแนบไฟล์เพิ่มได้อีก ' + maxAllowed +
                                            ' ไฟล์ (สูงสุด 3 ไฟล์ต่อใบงาน)'
                                    }
                                }));
                            }
                            filesBag = Array.from(map.values()).slice(0, maxAllowed); // กันเกินโควต้า
                            syncToInputs();
                            render();
                        };

                        anyBtn.addEventListener('click', () => {
                            anyInput.click();
                        });

                        camBtn.addEventListener('click', () => {
                            camInput.click();
                        });

                        anyInput.addEventListener('change', () => {
                            addFiles(anyInput.files);
                            // reset ค่า เพื่อเลือกไฟล์เดิมซ้ำได้
                            anyInput.value = '';
                        });

                        camInput.addEventListener('change', () => {
                            addFiles(camInput.files);
                            camInput.value = '';
                        });

                        // ถ้ามีไฟล์จาก old() (กรณี validation fail) เราไม่สามารถ restore file ได้ด้วยเบราว์เซอร์
                    })();
                </script>
            </section>
        </div>

    </div>
</div>

<x-ui.form-actions :cancel-href="route('maintenance.requests.index')"
    :submit-label="$isEdit ? 'บันทึกการแก้ไข' : 'ส่งใบแจ้งซ่อมบำรุง'"
    :submit-icon="$isEdit ? 'save' : 'send'" />

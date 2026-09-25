<section class="flex flex-col">
    <div class="flex items-start justify-between gap-4 {{ $headCls }}">
        <div class="flex items-start gap-3 min-w-0">
            <div class="{{ $noCls }}">4</div>
            <div class="{{ $accentWrap }}">
                <span class="{{ $accentBar }}"></span>
                <div class="{{ $titleCls }}">ไฟล์แนบ</div>
                <div class="{{ $subCls }}">รูป / เอกสารประกอบ</div>
            </div>
        </div>

        {{-- paperclip + camera: top right of the section, where the assign-staff icon of section 5 sits (-mt-1 puts the 40px icon's centre on the centre of the number circle) --}}
        @can('attach', $req)
            <div class="flex items-center gap-1 shrink-0 -mt-1">
                <x-ui.attach-buttons any="mr_files_any_btn" camera="mr_files_camera_btn" any-label="เลือกไฟล์เพิ่ม" />
            </div>
        @endcan
    </div>

    <div>

    @can('attach', $req)
        <form method="post" enctype="multipart/form-data" action="{{ $attachUploadUrl }}" class="space-y-4" novalidate>
            @csrf
            <input id="mr_files_submit" type="file" name="files[]" multiple class="hidden">
            <input id="mr_files_any" type="file" multiple accept="image/*,application/pdf" class="hidden">
            <input id="mr_files_camera" type="file" accept="image/*" capture="environment" class="hidden">

            <div class="p-3 rounded-md bg-amber-50 border border-amber-200">
                <div class="flex gap-2">
                    <svg class="h-5 w-5 text-amber-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                    <div class="text-xs text-amber-800">
                        <p class="font-bold">เงื่อนไขการแนบไฟล์:</p>
                        <p>จำกัดสูงสุด 3 ไฟล์ต่อใบงาน และสามารถเพิ่ม/ลบได้ตามสิทธิ์และสถานะของใบงานเท่านั้น</p>
                    </div>
                </div>
            </div>

            {{-- Preview list --}}
            <div id="mr_files_preview" class="mt-3 hidden">
                <div class="text-xs font-medium text-slate-600">ไฟล์ที่เลือก</div>
                <div id="mr_files_list"
                    class="mt-2 divide-y divide-slate-200 rounded-md border {{ $line }} bg-white"></div>

                {{-- the upload belongs to the chosen files: nothing to upload, no button --}}
                <div class="flex justify-end pt-3">
                    <x-ui.button type="submit" variant="primary" icon="upload_file">แนบไฟล์</x-ui.button>
                </div>
            </div>
        </form>

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
                    const dt = new DataTransfer();
                    filesBag.forEach(f => dt.items.add(f));
                    document.getElementById('mr_files_submit').files = dt.files;
                };
                const escapeHtml = (str) => String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g,
                    '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
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
                            'p-2.5 rounded-lg border border-slate-200 bg-white flex justify-between items-center ';
                        card.innerHTML =
                            `<div class="flex items-center gap-2 min-w-0 transition-all">
                                        <span data-file-name class="truncate text-[12px] font-medium text-slate-700"></span>
                                        <span class="text-[10px] text-slate-400">${(f.size/1024).toFixed(1)}KB</span>
                                    </div>
                                    <button type="button" class="text-rose-600 hover:text-rose-700 text-[11px] font-semibold">ลบ</button>`;
                        card.querySelector('[data-file-name]').textContent = f.name;   // a file name is text, never markup

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
                    const currentCount = {{ $atts->count() }};
                    const maxAllowed = Math.max(0, 3 - currentCount);

                    if (map.size > maxAllowed) {
                        window.dispatchEvent(new CustomEvent('app:toast', {
                            detail: {
                                type: 'warning',
                                message: 'สามารถแนบไฟล์เพิ่มได้อีก ' + maxAllowed +
                                    ' ไฟล์ (สูงสุด 3 ไฟล์ต่อใบงาน)'
                            }
                        }));
                    }
                    filesBag = Array.from(map.values()).slice(0, maxAllowed);
                    syncToInputs();
                    render();
                };
                anyBtn.addEventListener('click', () => anyInput.click());
                camBtn.addEventListener('click', () => camInput.click());
                anyInput.addEventListener('change', () => {
                    addFiles(anyInput.files);
                    anyInput.value = '';
                });
                camInput.addEventListener('change', () => {
                    addFiles(camInput.files);
                    camInput.value = '';
                });
            })();
        </script>
    @else
        <div class="rounded-md border {{ $line }} bg-white px-3 py-2 text-sm text-slate-600">
            คุณไม่มีสิทธิ์แนบไฟล์ในใบงานนี้
        </div>
    @endcan

    @can('attach', $req)
        <div class="mt-6 border-t {{ $line }} pt-6"></div>
    @endcan

    @if ($atts->count())
        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200">
            <p class="text-[13px] font-semibold text-slate-700 mb-4 flex items-center gap-2">
                <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <path d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                ไฟล์ที่แนบไว้แล้ว ({{ $atts->count() }})
            </p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($atts as $att)
                    @php
                        $file = $att->file;
                        $name = $att->original_name ?? ($file?->path ?? 'file');
                        $isPrivate = (bool) ($att->is_private ?? false);
                        $mime = $file?->mime ?? '';
                        $isImg = $mime && str_starts_with($mime, 'image/');
                        $publicUrl = null;
                        if ($file && ($file->disk ?? null) && ($file->path ?? null)) {
                            try {
                                $disk = Storage::disk($file->disk);
                                $publicUrl = $disk->url($file->path);
                            } catch (\Throwable $e) {
                                $publicUrl = null;
                            }
                        }
                        $canOpenPrivate = auth()->check() && auth()->user()->can('update', $req);
                        $canOpen = !$isPrivate || $canOpenPrivate;
                        $openUrl = $publicUrl;
                        try {
                            $openUrl = route('attachments.show', $att);
                        } catch (\Throwable $e) {
                            $openUrl = $publicUrl;
                        }
                        $deleteUrl = null;
                        try {
                            $deleteUrl = route('maintenance.requests.attachments.destroy', [
                                'maintenanceRequest' => $req->id,
                                'attachment' => $att->id,
                            ]);
                        } catch (\Throwable $e) {
                            $deleteUrl = null;
                        }
                    @endphp
                    <figure
                        class="overflow-hidden rounded-lg border {{ $line }} bg-white text-xs flex flex-col hover: transition">
                        @if ($canOpen && $openUrl)
                            <a href="{{ $openUrl }}" target="_blank" rel="noopener"
                                class="block overflow-hidden group border-b border-slate-100">
                                @if ($isImg)
                                    <img src="{{ $openUrl }}" alt="{{ $att->alt_text ?? $name }}"
                                        class="h-28 w-full object-cover transition-transform duration-300 group-hover:scale-105">
                                @else
                                    <div
                                        class="grid h-28 w-full place-items-center bg-slate-50 text-slate-500 text-[15px] font-bold group-hover:bg-slate-100 transition-colors">
                                        {{ strtoupper(pathinfo($name, PATHINFO_EXTENSION) ?: 'FILE') }}
                                    </div>
                                @endif
                            </a>
                        @else
                            <div
                                class="grid h-28 w-full place-items-center bg-slate-50 text-slate-400 text-[13px] font-bold border-b border-slate-100">
                                LOCKED
                            </div>
                        @endif
                        <figcaption class="px-3 py-2.5 space-y-2 flex-1 flex flex-col justify-between">
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between gap-1">
                                    <span
                                        class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide
                                                    {{ $isPrivate ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600' }}">
                                        {{ $isPrivate ? 'Private' : 'Public' }}
                                    </span>
                                    <span
                                        class="text-[10px] text-slate-400">{{ number_format(($file?->size ?? 0) / 1024, 1) }}
                                        KB</span>
                                </div>
                                <div class="text-[11.5px] font-medium text-slate-700 line-clamp-2"
                                    title="{{ $name }}">{{ $name }}</div>
                                @if ($att->caption)
                                    <div class="text-[11px] text-slate-500 font-medium line-clamp-2 italic"
                                        title="{{ $att->caption }}">
                                        "{{ $att->caption }}"
                                    </div>
                                @endif
                            </div>
                            <div class="pt-2 mt-auto border-t border-slate-50 flex items-center justify-end">
                                @can('deleteAttachment', $req)
                                    @if ($deleteUrl)
                                        <form method="POST" action="{{ $deleteUrl }}"
                                            onsubmit="return confirm('ยืนยันลบไฟล์แนบนี้?');">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button type="submit" variant="danger-outline" size="icon" icon="delete" aria-label="ลบไฟล์แนบ" />
                                        </form>
                                    @endif
                                @endcan
                            </div>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    @else
        <div
            class="mt-4 p-5 rounded-xl border border-dashed border-slate-300 bg-slate-50/50 flex flex-col items-center justify-center text-center">
            <svg class="h-8 w-8 text-slate-300 mb-2" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
            <span class="text-[13px] text-slate-500 font-medium tracking-wide">ยังไม่มีไฟล์แนบในใบงานนี้</span>
        </div>
    @endif
    </div>
</section>

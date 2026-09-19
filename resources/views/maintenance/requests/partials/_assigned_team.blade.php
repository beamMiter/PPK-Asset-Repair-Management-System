<section class="flex flex-col">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 {{ $headCls }}">
        <div class="flex items-start gap-3 min-w-0">
            <div class="{{ $noCls }}">5</div>
            <div class="{{ $accentWrap }}">
                <span class="{{ $accentBar }}"></span>
                <div class="{{ $titleCls }}">เจ้าหน้าที่รับผิดชอบ</div>
                <div class="{{ $subCls }}">รายชื่อทีมงานและเจ้าหน้าที่ควบคุมงาน</div>
            </div>
        </div>

        @can('assign', $req)
            <x-ui.button id="openAssignModalBtn" icon="group_add" class="shrink-0">
                มอบหมายทีมเจ้าหน้าที่
            </x-ui.button>
        @endcan
    </div>

    <div class="flex flex-col space-y-4">
        <div class="flex flex-col">
            <div class="flex items-center justify-between mb-2">
                <div class="text-[14px] font-semibold text-slate-800">รายชื่อทีมเจ้าหน้าที่</div>
                <div class="text-[12px] text-slate-500">{{ $workers->count() }} คน</div>
            </div>

            <div
                class="rounded-lg border {{ $line }} bg-white overflow-y-auto divide-y divide-slate-200 min-h-[76px] lg:max-h-72">
                @if ($workers->isEmpty())
                    <div class="px-4 py-3 text-[13px] text-slate-500">ยังไม่ได้มอบหมายงานให้ทีมเจ้าหน้าที่</div>
                @else
                    @foreach ($workers as $index => $worker)
                        @php
                            $avatar = $worker->avatar_thumb_url ?? null;
                        @endphp
                        <div class="worker-item-row flex items-center justify-between gap-3 px-4 py-3.5 {{ $index >= 5 ? 'hidden' : '' }}">
                            <div class="flex min-w-0 items-center gap-4">
                                <div
                                    class="h-11 w-11 flex-shrink-0 overflow-hidden rounded-full border {{ $line }} bg-white">
                                    @if ($avatar)
                                        <img src="{{ $avatar }}" alt="{{ $worker->name }}"
                                            class="h-full w-full object-cover">
                                    @else
                                        <div
                                            class="grid h-full w-full place-items-center text-[12px] text-slate-500 bg-slate-50">
                                            —</div>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-[15px] font-bold text-slate-900"
                                        title="{{ $worker->name }}">
                                        {{ $worker->name }}
                                    </div>
                                    <div class="truncate text-[13px] text-slate-500 mt-0.5">
                                        {{ $worker->role_label ?? ($fallbackRoleLabels[$worker->role ?? 'unknown'] ?? ($worker->role ?? 'unknown')) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    @if ($workers->count() > 5)
                        <button type="button" 
                            onclick="this.parentElement.querySelectorAll('.worker-item-row.hidden').forEach(el => el.classList.remove('hidden')); this.remove();"
                            class="w-full py-3 text-[13px] font-bold text-indigo-600 hover:bg-indigo-50 transition-colors border-t border-slate-100">
                            + ดูเพิ่มเติมอีก {{ $workers->count() - 5 }} คน
                        </button>
                    @endif
                @endif
            </div>
        </div>
</section>
